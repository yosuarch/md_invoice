<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditNoteModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\ProposalModel;

/**
 * The whole order of a customer in one view: proposal → invoice → payment → delivery.
 * Used by the order tracker on the proposal, invoice and delivery note pages; 'files' (OrderFiles) feeds the
 * "Documents & files" card.
 *
 * Steps have a state: done | current | pending | cancelled | skipped, a short detail text and a link.
 * The "active" invoice of a proposal is the latest one that is not void.
 */
final class OrderFlow
{
    /**
     * Order flow starting from a proposal.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array{steps: list<array{key: string, label: string, icon: string, state: string, detail: string, url: ?string}>, documents: list<array{type: string, number: string, date: ?string, status: string, url: string, amount: ?string}>}
     */
    public function forProposal(int $proposalId): array
    {
        $proposal = (new ProposalModel())->withDetails()->find($proposalId);
        $invoices = (new InvoiceModel())->withDetails()->where('invoice.proposal_id', $proposalId)->orderBy('invoice.id')->findAll();

        return $this->build($proposal, $invoices);
    }

    /**
     * Whether the order is finished: proposal approved, invoice issued, paid in full and every good delivered
     * (or no goods to deliver). Used to offer the combined order report (OrderBundle).
     *
     * @param array{steps: list<array{key: string, state: string}>} $flow Result of forProposal() / forInvoice().
     *
     * @return bool
     */
    public static function isComplete(array $flow): bool
    {
        $states = array_column($flow['steps'] ?? [], 'state', 'key');

        return ($states['proposal'] ?? null) === 'done'
            && ($states['invoice'] ?? null) === 'done'
            && ($states['payment'] ?? null) === 'done'
            && in_array($states['delivery'] ?? null, ['done', 'skipped'], true);
    }

    /**
     * Order flow of an invoice (and the proposal it came from, with its other invoices).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return array{steps: list<array<string, mixed>>, documents: list<array<string, mixed>>}
     */
    public function forInvoice(int $invoiceId): array
    {
        $invoice = (new InvoiceModel())->find($invoiceId);

        if ($invoice !== null && $invoice['proposal_id'] !== null) {
            return $this->forProposal((int) $invoice['proposal_id']);
        }

        return $this->build(null, $invoice !== null ? [(new InvoiceModel())->withDetails()->find($invoiceId)] : []);
    }

    /**
     * @param array<string, mixed>|null  $proposal Proposal with details, or null.
     * @param list<array<string, mixed>> $invoices Invoices with details (all, incl. void), oldest first.
     *
     * @return array{steps: list<array<string, mixed>>, documents: list<array<string, mixed>>}
     */
    private function build(?array $proposal, array $invoices): array
    {
        $active = null;

        foreach ($invoices as $inv) {
            if ($inv['status_code'] !== InvoiceWorkflow::VOID) {
                $active = $inv; // latest non-void wins
            }
        }

        $delivery = $active !== null && $active['status_code'] !== InvoiceWorkflow::DRAFT
            ? (new DeliveryService())->deliverableLines((int) $active['id'])
            : [];

        $steps = [
            $this->proposalStep($proposal),
            $this->invoiceStep($proposal, $active),
            $this->paymentStep($active),
            $this->deliveryStep($active, $delivery),
        ];

        return [
            'steps'     => $steps,
            'documents' => $this->documents($proposal, $invoices),
            'files'     => (new OrderFiles())->collect($proposal, $invoices),
        ];
    }

    /**
     * @param array<string, mixed>|null $proposal
     *
     * @return array<string, mixed>
     */
    private function proposalStep(?array $proposal): array
    {
        $step = ['key' => 'proposal', 'label' => lang('Orders.stepProposal'), 'icon' => 'file-earmark-text'];

        if ($proposal === null) {
            return $step + ['state' => 'skipped', 'detail' => lang('Orders.noProposal'), 'url' => null];
        }

        $code  = $proposal['status_code'];
        $state = match (true) {
            $code === ProposalWorkflow::STORE_APPROVE            => 'done',
            in_array($code, ProposalWorkflow::OPEN, true)        => 'current',
            default                                              => 'cancelled',
        };

        return $step + [
            'state'  => $state,
            'detail' => $proposal['cpl_id'] . ' · ' . ProposalWorkflow::label($code),
            'url'    => site_url('proposals/' . $proposal['id']),
        ];
    }

    /**
     * @param array<string, mixed>|null $proposal
     * @param array<string, mixed>|null $invoice Active invoice.
     *
     * @return array<string, mixed>
     */
    private function invoiceStep(?array $proposal, ?array $invoice): array
    {
        $step = ['key' => 'invoice', 'label' => lang('Orders.stepInvoice'), 'icon' => 'receipt'];

        if ($invoice === null) {
            $approved = $proposal !== null && $proposal['status_code'] === ProposalWorkflow::STORE_APPROVE;

            return $step + ['state' => $approved ? 'current' : 'pending', 'detail' => $approved ? lang('Orders.toInvoice') : lang('Orders.notYet'), 'url' => null];
        }

        $code  = $invoice['status_code'];
        $state = match ($code) {
            InvoiceWorkflow::DRAFT     => 'current',
            InvoiceWorkflow::CANCELLED => 'cancelled',
            default                    => 'done',
        };

        return $step + [
            'state'  => $state,
            'detail' => InvoiceModel::displayNumber($invoice) . ' · ' . ($code === InvoiceWorkflow::DRAFT ? InvoiceWorkflow::label($code) : ($code === InvoiceWorkflow::CANCELLED ? InvoiceWorkflow::label($code) : lang('Orders.issuedOn', [format_date($invoice['issue_date'])]))),
            'url'    => site_url('invoices/' . $invoice['id']),
        ];
    }

    /**
     * @param array<string, mixed>|null $invoice Active invoice.
     *
     * @return array<string, mixed>
     */
    private function paymentStep(?array $invoice): array
    {
        $step = ['key' => 'payment', 'label' => lang('Orders.stepPayment'), 'icon' => 'cash-coin'];

        if ($invoice === null || $invoice['status_code'] === InvoiceWorkflow::DRAFT) {
            return $step + ['state' => 'pending', 'detail' => lang('Orders.notYet'), 'url' => null];
        }

        $url        = site_url('invoices/' . $invoice['id']);
        $refundable = InvoiceModel::refundable($invoice);
        $balance    = InvoiceModel::balance($invoice);

        if (bccomp($refundable, '0', 2) > 0) {
            return $step + ['state' => 'current', 'detail' => lang('Orders.refundDue', [format_idr($refundable, 0)]), 'url' => $url];
        }

        return $step + match ($invoice['status_code']) {
            InvoiceWorkflow::PAID      => ['state' => 'done', 'detail' => lang('Orders.paidInFull'), 'url' => $url],
            InvoiceWorkflow::CANCELLED => ['state' => 'cancelled', 'detail' => lang('Orders.orderCancelled'), 'url' => $url],
            InvoiceWorkflow::OVERDUE   => ['state' => 'current', 'detail' => lang('Orders.overdueOpen', [format_idr($balance, 0)]), 'url' => $url],
            InvoiceWorkflow::PARTIALLY_PAID => ['state' => 'current', 'detail' => lang('Orders.partlyPaidOpen', [format_idr($balance, 0)]), 'url' => $url],
            default                    => ['state' => 'current', 'detail' => lang('Orders.dueOn', [format_date($invoice['due_date']), format_idr($balance, 0)]), 'url' => $url],
        };
    }

    /**
     * @param array<string, mixed>|null  $invoice Active invoice.
     * @param list<array<string, mixed>> $lines   Goods lines with shipped / delivered.
     *
     * @return array<string, mixed>
     */
    private function deliveryStep(?array $invoice, array $lines): array
    {
        $step = ['key' => 'delivery', 'label' => lang('Orders.stepDelivery'), 'icon' => 'truck'];

        if ($invoice === null || $invoice['status_code'] === InvoiceWorkflow::DRAFT) {
            return $step + ['state' => 'pending', 'detail' => lang('Orders.notYet'), 'url' => null];
        }

        if ($lines === []) {
            return $step + ['state' => 'skipped', 'detail' => lang('Orders.noGoods'), 'url' => null];
        }

        $ordered = $shipped = $delivered = '0';

        foreach ($lines as $l) {
            $ordered   = bcadd($ordered, (string) $l['quantity'], 3);
            $shipped   = bcadd($shipped, (string) $l['shipped'], 3);
            $delivered = bcadd($delivered, (string) $l['delivered'], 3);
        }

        $url    = site_url('deliveries?q=' . rawurlencode((string) $invoice['invoice_number']));
        $detail = lang('Orders.deliveredOf', [format_qty($delivered), format_qty($ordered)]);

        if ($invoice['status_code'] === InvoiceWorkflow::CANCELLED && bccomp($shipped, '0', 3) === 0) {
            return $step + ['state' => 'cancelled', 'detail' => lang('Orders.orderCancelled'), 'url' => null];
        }

        return $step + match (true) {
            bccomp($delivered, $ordered, 3) >= 0 => ['state' => 'done', 'detail' => lang('Orders.allDelivered'), 'url' => $url],
            bccomp($shipped, $delivered, 3) > 0  => ['state' => 'current', 'detail' => $detail . ' · ' . lang('Orders.onTheWay', [format_qty(bcsub($shipped, $delivered, 3))]), 'url' => $url],
            default                              => ['state' => 'current', 'detail' => $detail, 'url' => $url],
        };
    }

    /**
     * All linked documents in order: proposal, invoices, credit notes, delivery notes.
     *
     * @param array<string, mixed>|null  $proposal
     * @param list<array<string, mixed>> $invoices
     *
     * @return list<array{type: string, number: string, date: ?string, status: string, url: string, amount: ?string}>
     */
    private function documents(?array $proposal, array $invoices): array
    {
        $docs = [];

        if ($proposal !== null) {
            $docs[] = ['type' => lang('Orders.stepProposal'), 'number' => $proposal['cpl_id'], 'date' => $proposal['created_at'], 'status' => proposal_status_badge($proposal['status_code']), 'url' => site_url('proposals/' . $proposal['id']), 'amount' => $proposal['grand_total']];
        }

        foreach ($invoices as $inv) {
            $docs[] = ['type' => lang('Orders.stepInvoice'), 'number' => InvoiceModel::displayNumber($inv), 'date' => $inv['status_code'] === InvoiceWorkflow::DRAFT ? $inv['created_at'] : $inv['issue_date'], 'status' => invoice_status_badge($inv['status_code']), 'url' => site_url('invoices/' . $inv['id']), 'amount' => $inv['grand_total']];

            foreach ((new CreditNoteModel())->where('invoice_id', $inv['id'])->orderBy('id')->findAll() as $cn) {
                $docs[] = ['type' => lang('Orders.creditNote'), 'number' => $cn['credit_note_number'], 'date' => $cn['issue_date'], 'status' => '<span class="badge bg-secondary-subtle text-secondary-emphasis border">' . esc(code_label('reasonType', $cn['reason_type'], CreditNoteModel::REASON_TYPES[$cn['reason_type']] ?? null)) . '</span>', 'url' => site_url('credit-notes/' . $cn['id']), 'amount' => '-' . $cn['grand_total']];
            }

            foreach ((new DeliveryNoteModel())->where('invoice_id', $inv['id'])->orderBy('id')->findAll() as $dn) {
                $docs[] = ['type' => lang('Orders.deliveryNote'), 'number' => $dn['delivery_number'], 'date' => $dn['ship_date'], 'status' => DeliveryNoteModel::badge($dn), 'url' => site_url('deliveries/' . $dn['id']), 'amount' => null];
            }
        }

        return $docs;
    }
}
