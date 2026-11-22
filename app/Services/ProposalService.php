<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientProposalStatusModel;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalModel;
use App\Models\ProposalStatusHistoryModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Proposal (quotation) operations. Every write runs in one transaction:
 * number from document_sequence (QUO), item lines copied from products, totals computed with
 * DocumentTotals, and each status change logged in proposal_status_history.
 */
final class ProposalService
{
    /**
     * Header fields accepted from the form.
     *
     * @var list<string>
     */
    public const HEADER_FIELDS = [
        'customer_id', 'contact_person_id', 'cpl_proposal_title', 'funding_source_id', 'sales_rep_id', 'valid_until', 'cpl_note',
        'payment_term_id', 'shipment_id', 'delivery_date',
    ];

    /**
     * Item fields accepted from the form (see DocumentLines).
     *
     * @var list<string>
     */
    public const ITEM_FIELDS = DocumentLines::ITEM_FIELDS;

    public function __construct(
        private readonly ProposalModel $proposals = new ProposalModel(),
        private readonly ProposalItemModel $items = new ProposalItemModel(),
        private readonly ProposalStatusHistoryModel $history = new ProposalStatusHistoryModel(),
        private readonly ClientProposalStatusModel $statuses = new ClientProposalStatusModel(),
    ) {
    }

    /**
     * Create a DRAFT proposal with its items.
     *
     * @param array<string, mixed>       $header Header fields (HEADER_FIELDS).
     * @param list<array<string, mixed>> $items  Posted item rows (ITEM_FIELDS).
     * @param int                        $userId Creating user.
     *
     * @return int New proposal id.
     *
     * @throws ValidationFailed
     */
    public function create(array $header, array $items, int $userId): int
    {
        $header = $this->cleanHeader($header);
        $lines  = $this->validateInput($header, $items, [], null);

        return Transaction::run(function () use ($header, $lines, $userId): int {
            $draftId = $this->statuses->idForCode(ProposalWorkflow::DRAFT);
            $now     = date('Y-m-d H:i:s');
            $totals  = DocumentTotals::calculate($lines);

            $id = $this->proposals->insert($header + [
                'cpl_id'            => (new DocumentSequence())->next('QUO'),
                'status_id'         => $draftId,
                'status_updated_by' => $userId,
                'status_updated_at' => $now,
                'created_by'        => $userId,
            ] + $this->headerTotals($totals));

            if ($id === false) {
                throw new ValidationFailed($this->proposals->errors());
            }

            DocumentLines::sync($this->items, 'proposal_id', 'cpi_quantity', (int) $id, $lines, $totals, []);
            $this->logStatus((int) $id, null, $draftId, $userId, 'Created');
            (new CustomerService())->assignRep((int) $header['customer_id'], (int) ($header['sales_rep_id'] ?? 0) ?: null);

            return (int) $id;
        });
    }

    /**
     * Update header and items of a proposal that is still editable (DRAFT / UNDER_REVIEW).
     *
     * @param int                        $id     Proposal id.
     * @param array<string, mixed>       $header Header fields.
     * @param list<array<string, mixed>> $items  Posted item rows.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the proposal is locked.
     */
    public function update(int $id, array $header, array $items): void
    {
        $header   = $this->cleanHeader($header);
        $existing = [];

        foreach ($this->items->where('proposal_id', $id)->findAll() as $row) {
            $existing[(int) $row['id']] = $row;
        }

        $lines = $this->validateInput($header, $items, $existing, $id);

        Transaction::run(function (BaseConnection $db) use ($id, $header, $lines, $existing): void {
            $code = $this->lockedStatusCode($db, $id);

            if (! ProposalWorkflow::isEditable($code)) {
                throw new RuntimeException(ProposalWorkflow::lockedMessage($code));
            }

            $totals = DocumentTotals::calculate($lines);

            if (! $this->proposals->update($id, $header + $this->headerTotals($totals) + ['id' => $id])) {
                throw new ValidationFailed($this->proposals->errors());
            }

            DocumentLines::sync($this->items, 'proposal_id', 'cpi_quantity', $id, $lines, $totals, $existing);
            (new CustomerService())->assignRep((int) $header['customer_id'], (int) ($header['sales_rep_id'] ?? 0) ?: null);
        });
    }

    /**
     * Move a proposal to another status (see ProposalWorkflow) and log it.
     *
     * @param int                   $id     Proposal id.
     * @param string                $to     Target status code.
     * @param string                $note   Reason / remark (required for some transitions).
     * @param callable(string):bool $can    Permission checker for the current user.
     * @param int                   $userId Current user id.
     *
     * @return string Label of the new status.
     *
     * @throws ValidationFailed When a required note is missing.
     * @throws RuntimeException When the transition is not allowed.
     */
    public function changeStatus(int $id, string $to, string $note, callable $can, int $userId): string
    {
        $note = trim($note);

        return Transaction::run(function (BaseConnection $db) use ($id, $to, $note, $can, $userId): string {
            $from       = $this->lockedStatusCode($db, $id);
            $transition = ProposalWorkflow::transition($from, $to);

            if ($transition === null) {
                throw new RuntimeException(lang('Messages.transitionInvalid', [mb_strtolower(ProposalWorkflow::label($from)), mb_strtolower(ProposalWorkflow::label($to))]));
            }

            if (! $can($transition['permission'])) {
                throw new RuntimeException(lang('Messages.notAllowedAction', [$transition['label']]));
            }

            if ($transition['note'] === 'required' && $note === '') {
                throw new ValidationFailed(['note' => lang('Messages.enterReason')]);
            }

            if (mb_strlen($note) > 2000) {
                throw new ValidationFailed(['note' => lang('Messages.noteTooLong')]);
            }

            if (! empty($transition['needsItems']) && $this->items->where('proposal_id', $id)->countAllResults() === 0) {
                throw new RuntimeException(lang('Messages.addItemBefore', [$transition['label']]));
            }

            if ($from === ProposalWorkflow::STORE_APPROVE && $this->proposals->hasInvoice($id)) {
                throw new RuntimeException(lang('Messages.approvalHasInvoice'));
            }

            $fromId = $this->statuses->idForCode($from);
            $toId   = $this->statuses->idForCode($to);

            $this->proposals->update($id, [
                'status_id'         => $toId,
                'status_updated_by' => $userId,
                'status_updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logStatus($id, $fromId, $toId, $userId, $note);

            // Negotiation history: every version sent is a revision; the approved version too if it was changed after sending.
            if ($to === ProposalWorkflow::PROPOSE || $to === ProposalWorkflow::STORE_APPROVE) {
                (new ProposalRevisions())->record($id, $to, $note, $userId, $to === ProposalWorkflow::STORE_APPROVE);
            }

            return ProposalWorkflow::label($to);
        });
    }

    /**
     * Validate header and items together, so the form shows all errors at once.
     *
     * @param array<string, mixed>             $header   Clean header values.
     * @param list<array<string, mixed>>       $items    Posted item rows.
     * @param array<int, array<string, mixed>> $existing Current lines by id.
     * @param int|null                         $id       Proposal id when editing.
     *
     * @return list<array<string, mixed>> Prepared lines.
     *
     * @throws ValidationFailed
     */
    private function validateInput(array $header, array $items, array $existing, ?int $id): array
    {
        $errors = [];
        $lines  = [];

        try {
            $this->assertHeader($header, $id);
        } catch (ValidationFailed $e) {
            $errors = $e->errors;
        }

        try {
            $lines = DocumentLines::prepare($items, $existing, date('Y-m-d'));
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return $lines;
    }

    /**
     * Normalize header values; only HEADER_FIELDS are kept.
     *
     * @param array<string, mixed> $header Raw values.
     *
     * @return array<string, mixed>
     */
    private function cleanHeader(array $header): array
    {
        $clean = [];

        foreach (self::HEADER_FIELDS as $field) {
            $value         = $header[$field] ?? '';
            $clean[$field] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /**
     * Header checks the model rules cannot express.
     *
     * @param array<string, mixed> $header Header values.
     * @param int|null             $id     Proposal id when editing.
     *
     * @return void
     *
     * @throws ValidationFailed
     */
    private function assertHeader(array $header, ?int $id): void
    {
        // Model rules for the submitted header fields only (cleanValidationRules skips the others).
        $errors = $this->proposals->validate($header) ? [] : $this->proposals->errors();

        $customerId = (int) $header['customer_id'];
        $customer   = $customerId > 0 ? (new CustomerModel())->find($customerId) : null;

        if ($customerId > 0 && $customer !== null && ! $customer['is_active']) {
            $current = $id !== null ? $this->proposals->find($id) : null;

            if ($current === null || (int) $current['customer_id'] !== $customerId) {
                $errors['customer_id'] = lang('Messages.customerInactive');
            }
        }

        $contactId = (int) $header['contact_person_id'];

        if ($contactId > 0 && ! isset($errors['customer_id'])) {
            $contact = (new CustomerContactModel())->find($contactId);

            if ($contact === null || (int) $contact['customer_id'] !== $customerId) {
                $errors['contact_person_id'] = lang('Messages.contactNotOfCustomer');
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
    }

    /**
     * Header total columns from a DocumentTotals result.
     *
     * @param array{subtotal: string, discount_total: string, tax_total: string, grand_total: string} $totals
     *
     * @return array<string, string>
     */
    private function headerTotals(array $totals): array
    {
        return [
            'subtotal'       => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total'      => $totals['tax_total'],
            'grand_total'    => $totals['grand_total'],
        ];
    }

    /**
     * Lock the proposal row (SELECT ... FOR UPDATE) and return its current status code.
     *
     * @param BaseConnection $db Connection inside the running transaction.
     * @param int            $id Proposal id.
     *
     * @return string
     *
     * @throws RuntimeException When the proposal does not exist.
     */
    private function lockedStatusCode(BaseConnection $db, int $id): string
    {
        $row = $db->query(
            'SELECT s.code FROM client_proposal_list p JOIN client_proposal_status s ON s.id = p.status_id'
            . ' WHERE p.id = ? AND p.deleted_at IS NULL FOR UPDATE',
            [$id],
        )->getRowArray();

        if ($row === null) {
            throw new RuntimeException(lang('Messages.notFoundProposal'));
        }

        return $row['code'];
    }

    /**
     * Append a status history row.
     *
     * @param int         $proposalId Proposal id.
     * @param int|null    $fromId     Previous status id (null on creation).
     * @param int         $toId       New status id.
     * @param int         $userId     User who made the change.
     * @param string|null $note       Remark.
     *
     * @return void
     */
    private function logStatus(int $proposalId, ?int $fromId, int $toId, int $userId, ?string $note): void
    {
        $this->history->insert([
            'proposal_id'    => $proposalId,
            'from_status_id' => $fromId,
            'to_status_id'   => $toId,
            'changed_by'     => $userId,
            'note'           => $note ?? '',
        ]);
    }
}
