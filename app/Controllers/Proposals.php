<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\CustomerSalesRepModel;
use App\Models\FundingSourceModel;
use App\Models\InvoiceModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalModel;
use App\Models\ProposalStatusHistoryModel;
use App\Models\SalesRepModel;
use App\Models\UserModel;
use App\Services\ProposalService;
use App\Services\ProposalWorkflow;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Documents;
use RuntimeException;
use Throwable;

/**
 * Proposals / quotations (permission proposals.view / proposals.manage; approval needs proposals.approve).
 */
class Proposals extends BaseController
{
    private ProposalModel $model;
    private ProposalService $service;

    public function __construct()
    {
        $this->model   = new ProposalModel();
        $this->service = new ProposalService();
    }

    /**
     * Searchable, filterable, paginated list.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'q'        => trim((string) $this->request->getGet('q')),
            'status'   => (string) $this->request->getGet('status'),
            'rep'      => (string) $this->request->getGet('rep'),
            'customer' => (string) (int) $this->request->getGet('customer') ?: '',
        ];

        $myRep     = (new SalesRepModel())->forUser($this->userId());
        $proposals = $this->scope()->proposals($this->model->search($filters, $myRep !== null ? (int) $myRep['id'] : null))->paginate(20);

        return view('proposals/index', [
            'proposals' => $proposals,
            'pager'     => $this->model->pager,
            'filters'   => $filters,
            'customer'  => $filters['customer'] !== '' ? (new CustomerModel())->find((int) $filters['customer']) : null,
            'reps'      => $this->scope()->seesAll() ? (new SalesRepModel())->activeOptions() : [],
            'hasMyRep'  => $myRep !== null,
        ]);
    }

    /**
     * Proposal detail: header, items, totals, status history and allowed status actions.
     *
     * @param int $id Proposal id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $this->guardProposal($id);

        $proposal = $this->findOr404($this->model->withDetails(), $id);
        $user     = auth()->user();

        return view('proposals/show', [
            'proposal' => $proposal,
            'items'    => (new ProposalItemModel())->forProposal($id),
            'history'  => (new ProposalStatusHistoryModel())->forProposal($id),
            'contact'  => $proposal['contact_person_id'] ? (new CustomerContactModel())->findWithPerson((int) $proposal['contact_person_id']) : null,
            'creator'  => $proposal['created_by'] ? (new UserModel())->find($proposal['created_by']) : null,
            'actions'  => ProposalWorkflow::available($proposal['status_code'], static fn (string $p): bool => $user->can($p)),
            'canEdit'  => $user->can('proposals.manage') && ProposalWorkflow::isEditable($proposal['status_code']),
            'invoices' => (new InvoiceModel())->activeForProposal($id),
            'canInvoice' => $user->can('invoices.manage'),
            'flow'     => (new \App\Services\OrderFlow())->forProposal($id),
            'agreement' => (new \App\Models\AgreementModel())->forProposal($id),
            'negotiation' => (new \App\Services\ProposalRevisions())->history($id),
            'activityPanel' => user_can('crm.view') ? \App\Services\ActivityPanel::forProposal($proposal, $this->scope()) : null,
        ]);
    }

    /**
     * Create form; ?customer=ID preselects a customer.
     *
     * @return string
     */
    public function new(): string
    {
        $days = config(Documents::class)->proposalValidDays;

        return $this->formView([
            'customer_id' => (int) $this->request->getGet('customer') ?: null,
            'valid_until' => date('Y-m-d', strtotime("+{$days} days")),
        ], []);
    }

    /**
     * Store a new DRAFT proposal (number from document_sequence).
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        try {
            $id = $this->service->create($this->scopedHeader(), $this->postedItems(), $this->userId());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Proposal create');
        }

        $number = $this->model->find($id)['cpl_id'];

        return redirect()->to("proposals/{$id}")->with('message', lang('Messages.proposalCreated', [$number]));
    }

    /**
     * Edit form (DRAFT / UNDER_REVIEW only).
     *
     * @param int $id Proposal id.
     *
     * @return RedirectResponse|string
     */
    public function edit(int $id): RedirectResponse|string
    {
        $this->guardProposal($id);

        $proposal = $this->findOr404($this->model->withDetails(), $id);

        if (! ProposalWorkflow::isEditable($proposal['status_code'])) {
            return redirect()->to("proposals/{$id}")->with('error', ProposalWorkflow::lockedMessage($proposal['status_code']));
        }

        return $this->formView($proposal, (new ProposalItemModel())->forProposal($id));
    }

    /**
     * Update header and items.
     *
     * @param int $id Proposal id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->guardProposal($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->update($id, $this->scopedHeader(), $this->postedItems());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("proposals/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Proposal update');
        }

        // "Save & send revision" (negotiation): saved first, then sent as a new revision.
        if ($this->request->getPost('send') === '1') {
            $user = auth()->user();

            try {
                $this->service->changeStatus($id, ProposalWorkflow::PROPOSE, (string) $this->request->getPost('revision_note'), static fn (string $p): bool => $user->can($p), $this->userId());
            } catch (ValidationFailed | RuntimeException $e) {
                return redirect()->to("proposals/{$id}")->with('error', lang('Messages.savedNotSent', [$e instanceof ValidationFailed ? implode(' ', $e->errors) : $e->getMessage()]));
            } catch (Throwable $e) {
                return $this->failed($e, 'Proposal send revision');
            }

            $no = (int) ($this->model->find($id)['revision_no'] ?? 0);

            return redirect()->to("proposals/{$id}")->with('message', lang('Messages.savedAndSent', [$no]));
        }

        return redirect()->to("proposals/{$id}")->with('message', lang('Messages.proposalSaved'));
    }

    /**
     * Change the status (POST to = status code, note = remark).
     *
     * @param int $id Proposal id.
     *
     * @return RedirectResponse
     */
    public function status(int $id): RedirectResponse
    {
        $this->guardProposal($id);

        $this->findOr404($this->model, $id);
        $user = auth()->user();

        try {
            $label = $this->service->changeStatus(
                $id,
                (string) $this->request->getPost('to'),
                (string) $this->request->getPost('note'),
                static fn (string $permission): bool => $user->can($permission),
                $this->userId(),
            );
        } catch (ValidationFailed $e) {
            return redirect()->to("proposals/{$id}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("proposals/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Proposal status change');
        }

        // Customer asked for changes: go straight to the editor.
        if ((string) $this->request->getPost('to') === ProposalWorkflow::UNDER_REVIEW && user_can('proposals.manage')) {
            return redirect()->to("proposals/{$id}/edit")->with('message', lang('Messages.requestRecorded'));
        }

        return redirect()->to("proposals/{$id}")->with('message', lang('Messages.statusChanged', [$label]));
    }

    /**
     * Render the form with the selected customer's contacts and reps.
     * After a failed submit the posted values (old input) win over the stored ones.
     *
     * @param array<string, mixed>       $proposal Stored proposal (or defaults on create).
     * @param list<array<string, mixed>> $items    Stored item lines.
     *
     * @return string
     */
    private function formView(array $proposal, array $items): string
    {
        $isEdit     = isset($proposal['id']);
        $customerId = (int) (old('customer_id', null, false) ?? $proposal['customer_id'] ?? 0);
        $customer   = $customerId > 0 && $this->scope()->canCustomer($customerId) ? (new CustomerModel())->find($customerId) : null;
        // People still at the school, plus the proposal's own contact if that person has left since.
        $contacts   = $customer !== null ? (new CustomerContactModel())->forCustomer($customerId, true) : [];
        $chosen     = (int) ($proposal['contact_person_id'] ?? 0);

        if ($chosen > 0 && ! in_array($chosen, array_map('intval', array_column($contacts, 'id')), true)) {
            $old = (new CustomerContactModel())->findWithPerson($chosen);

            if ($old !== null && (int) $old['customer_id'] === $customerId) {
                $contacts[] = $old;
            }
        }
        $repIds     = $customer !== null ? (new CustomerSalesRepModel())->repIdsFor($customerId) : [];

        if (! $isEdit && $customer !== null) {
            $proposal['contact_person_id'] ??= $contacts[0]['id'] ?? null; // primary first
            $proposal['sales_rep_id'] ??= $this->defaultRepId($repIds);
            $proposal['payment_term_id'] ??= $customer['default_payment_term_id'];
        } elseif (! $isEdit) {
            $proposal['sales_rep_id'] ??= (new SalesRepModel())->forUser($this->userId())['id'] ?? null;
        }

        $oldItems = old('items', null, false);

        return view('proposals/form', [
            'proposal'  => $proposal,
            'customer'  => $customer,
            'contacts'  => $contacts,
            'reps'      => $this->repOptions((int) ($proposal['sales_rep_id'] ?? 0)),
            'funding'   => (new FundingSourceModel())->options(),
            'terms'     => array_map(static fn (array $t): string => $t['code'] . ' (' . $t['due_days'] . ' days)', array_column((new \App\Models\PaymentTermModel())->orderBy('due_days')->findAll(), null, 'id')),
            'shipments' => (new \App\Models\ShipmentModel())->options('name'),
            'levels'    => array_column((new \App\Models\SchoolLevelModel())->orderBy('sort_order')->findAll(), 'name', 'id'),
            'regions'   => ['provinces' => (new \App\Models\RegionModel())->provinces(), 'cities' => []],
            'items'     => is_array($oldItems) ? array_values($oldItems) : array_map(self::formItem(...), $items),
            'taxes'     => \App\Services\LineTax::options(),
            'negotiation' => $isEdit ? $this->negotiationContext($proposal) : null,
        ]);
    }

    /**
     * Context for editing a proposal under negotiation: the customer's latest request and the last revision sent.
     *
     * @param array<string, mixed> $proposal Proposal with status_code.
     *
     * @return array{request: ?array<string, mixed>, revision: ?array<string, mixed>, next: int}|null Null when not under review.
     */
    private function negotiationContext(array $proposal): ?array
    {
        if (($proposal['status_code'] ?? '') !== ProposalWorkflow::UNDER_REVIEW) {
            return null;
        }

        $request = null;

        foreach ((new ProposalStatusHistoryModel())->forProposal((int) $proposal['id']) as $h) {
            if ($h['to_code'] === ProposalWorkflow::UNDER_REVIEW) {
                $request = $h; // newest first

                break;
            }
        }

        $revision = (new \App\Models\ProposalRevisionModel())->where('proposal_id', $proposal['id'])->orderBy('revision_no', 'DESC')->first();

        return ['request' => $request, 'revision' => $revision, 'next' => (int) ($revision['revision_no'] ?? 0) + 1];
    }

    /**
     * Posted header fields, limited to what the user may use: a user without records.view_all can only pick a
     * school assigned to them and is always the proposal's own sales rep.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationFailed When the school is not visible to the user.
     */
    private function scopedHeader(): array
    {
        $header = $this->postFields(ProposalService::HEADER_FIELDS);

        if ($this->scope()->seesAll()) {
            return $header;
        }

        if ((int) $header['customer_id'] > 0 && ! $this->scope()->canCustomer((int) $header['customer_id'])) {
            throw new ValidationFailed(['customer_id' => lang('Messages.selectOwnSchool')]);
        }

        $header['sales_rep_id'] = $this->scope()->repId() ?? '';

        return $header;
    }

    /**
     * Sales rep options: all active reps for users who see everything, otherwise only the user's own rep.
     *
     * @param int $current Rep stored on the proposal (kept in the list even if inactive).
     *
     * @return array<int, string>
     */
    private function repOptions(int $current): array
    {
        if ($this->scope()->seesAll()) {
            return (new SalesRepModel())->activeOptions(array_filter([$current]));
        }

        $rep = $this->scope()->repId() !== null ? (new SalesRepModel())->find($this->scope()->repId()) : null;

        return $rep !== null ? [(int) $rep['id'] => $rep['rep_name']] : [];
    }

    /**
     * Default sales rep for a customer: the current user's rep if assigned to it, else the first assigned rep.
     *
     * @param list<int> $repIds Reps assigned to the customer.
     *
     * @return int|null
     */
    private function defaultRepId(array $repIds): ?int
    {
        $myRep = (new SalesRepModel())->forUser($this->userId());

        if ($myRep !== null && in_array((int) $myRep['id'], $repIds, true)) {
            return (int) $myRep['id'];
        }

        return $repIds[0] ?? ($myRep !== null ? (int) $myRep['id'] : null);
    }

    /**
     * Stored line in the shape the item table script expects.
     *
     * @param array<string, mixed> $item Stored line.
     *
     * @return array<string, mixed>
     */
    private static function formItem(array $item): array
    {
        $incl = ($item['price_base'] ?? 'EXCL') === 'INCL'; // shown as typed (price incl. tax)

        return [
            'id'              => $item['id'],
            'product_id'      => $item['product_id'],
            'variant_id'      => $item['variant_id'] ?? '',
            'sku'             => $item['pl_sku'] ?? '',
            'item_title'      => up($item['item_title']),
            'item_description' => $item['item_description'] ?? '',
            'unit_code'       => $item['unit_code'],
            'item_type'       => $item['item_type'],
            'quantity'        => $item['cpi_quantity'],
            'unit_price'      => decimal_input($incl ? ($item['unit_price_incl'] ?? \App\Services\DocumentTotals::inclFromExcl($item['unit_price'], $item['tax_rate'])) : $item['unit_price']),
            'discount_amount' => decimal_input($incl ? ($item['discount_incl'] ?? \App\Services\DocumentTotals::inclFromExcl($item['discount_amount'], $item['tax_rate'])) : $item['discount_amount']),
            'tax_rate'        => $item['tax_rate'],
            'tax_code'        => $item['tax_code'] ?? '',
            'price_base'      => $incl ? 'INCL' : 'EXCL',
        ];
    }

    /**
     * Posted item rows ("items[n][field]").
     *
     * @return list<array<string, mixed>>
     */
    private function postedItems(): array
    {
        $items = $this->request->getPost('items');

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
