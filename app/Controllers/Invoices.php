<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankAccountModel;
use App\Models\CreditNoteModel;
use App\Models\CustomerModel;
use App\Models\DeliveryNoteModel;
use App\Models\FundingSourceModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentMethodModel;
use App\Models\PaymentModel;
use App\Models\PaymentTermModel;
use App\Models\PaymentWithholdingModel;
use App\Models\RefundModel;
use App\Models\SalesRepModel;
use App\Models\ShipmentModel;
use App\Models\UserModel;
use App\Services\DeliveryService;
use App\Services\InvoiceService;
use App\Services\InvoiceWorkflow;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Invoices (permission invoices.view / invoices.manage; voiding needs invoices.void).
 * Created from approved proposals; editable and deletable only as DRAFT; issued invoices are locked.
 */
class Invoices extends BaseController
{
    private InvoiceModel $model;
    private InvoiceService $service;

    public function __construct()
    {
        $this->model   = new InvoiceModel();
        $this->service = new InvoiceService();
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

        $myRep    = (new SalesRepModel())->forUser($this->userId());
        $invoices = $this->scope()->invoices($this->model->search($filters, $myRep !== null ? (int) $myRep['id'] : null))->paginate(20);

        return view('invoices/index', [
            'invoices' => $invoices,
            'pager'    => $this->model->pager,
            'filters'  => $filters,
            'customer' => $filters['customer'] !== '' ? (new CustomerModel())->find((int) $filters['customer']) : null,
            'reps'     => $this->scope()->seesAll() ? (new SalesRepModel())->activeOptions() : [],
            'hasMyRep' => $myRep !== null,
        ]);
    }

    /**
     * Invoice detail: header, items, totals, withholding, payments, credit notes and actions.
     *
     * @param int $id Invoice id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $this->guardInvoice($id);

        $invoice = $this->findOr404($this->model->withDetails(), $id);
        $items   = (new InvoiceItemModel())->forInvoice($id);
        $isDraft = $invoice['status_code'] === InvoiceWorkflow::DRAFT;
        $user    = auth()->user();

        try {
            // Drafts show a preview with today's rules; issued invoices show the stored (copied) amounts.
            $withholding = $isDraft
                ? $this->service->calculateWithholding($invoice, $items, date('Y-m-d'))
                : (new InvoiceWithholdingModel())->forInvoice($id);
        } catch (Throwable $e) {
            log_message('error', 'Withholding preview failed: {message}', ['message' => $e->getMessage()]);
            $withholding = [];
        }

        return view('invoices/show', [
            'invoice'      => $invoice,
            'items'        => $items,
            'withholding'  => $withholding,
            'payments'     => (new PaymentModel())->forInvoice($id),
            'paymentTaxes' => (new PaymentWithholdingModel())->forInvoiceByPayment($id),
            'creditNotes'  => (new CreditNoteModel())->withDetails()->where('credit_note.invoice_id', $id)->findAll(),
            'refunds'      => (new RefundModel())->forInvoice($id),
            'deliveries'   => $isDraft ? [] : (new DeliveryNoteModel())->forInvoice($id),
            'goodsLines'   => $isDraft ? [] : (new DeliveryService())->deliverableLines($id),
            'flow'         => (new \App\Services\OrderFlow())->forInvoice($id),
            'activityPanel' => user_can('crm.view') ? \App\Services\ActivityPanel::forInvoice($invoice, $this->scope()) : null,
            'bank'         => $invoice['bank_account_id'] ? (new BankAccountModel())->withDeleted()->find($invoice['bank_account_id']) : null,
            'method'       => $invoice['payment_method_id'] ? (new PaymentMethodModel())->find($invoice['payment_method_id']) : null,
            'shipment'     => $invoice['shipment_id'] ? (new ShipmentModel())->find($invoice['shipment_id']) : null,
            'creator'      => $invoice['created_by'] ? (new UserModel())->find($invoice['created_by']) : null,
            'voidedBy'     => $invoice['voided_by'] ? (new UserModel())->find($invoice['voided_by']) : null,
            'can'          => [
                'manage'  => $user->can('invoices.manage'),
                'void'    => $user->can('invoices.void'),
                'pay'     => $user->can('payments.record'),
                'credit'  => $user->can('credit_notes.manage'),
                'reverse' => $user->can('payments.reverse'),
                'deliver' => $user->can('deliveries.manage'),
            ],
        ]);
    }

    /**
     * Create a DRAFT invoice from an approved proposal (POST proposals/{id}/invoice).
     *
     * @param int $proposalId Proposal id.
     *
     * @return RedirectResponse
     */
    public function createFromProposal(int $proposalId): RedirectResponse
    {
        $this->guardProposal($proposalId);

        try {
            $id = $this->service->createFromProposal($proposalId, $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to("proposals/{$proposalId}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("proposals/{$proposalId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Invoice create');
        }

        return redirect()->to("invoices/{$id}/edit")->with('message', lang('Messages.draftCreated'));
    }

    /**
     * Edit form (DRAFT only).
     *
     * @param int $id Invoice id.
     *
     * @return RedirectResponse|string
     */
    public function edit(int $id): RedirectResponse|string
    {
        $this->guardInvoice($id);

        $invoice = $this->findOr404($this->model->withDetails(), $id);

        if ($invoice['status_code'] !== InvoiceWorkflow::DRAFT) {
            return redirect()->to("invoices/{$id}")->with('error', lang('Messages.invoiceLockedShort'));
        }

        $oldItems = old('items', null, false);
        $items    = (new InvoiceItemModel())->forInvoice($id);

        return view('invoices/form', [
            'invoice'  => $invoice,
            'items'    => is_array($oldItems) ? array_values($oldItems) : array_map(self::formItem(...), $items),
            'taxes'    => \App\Services\LineTax::options(),
            'terms'    => array_map(
                static fn (array $t): string => $t['code'] . ' (' . $t['due_days'] . ' days)',
                array_column((new PaymentTermModel())->orderBy('due_days')->findAll(), null, 'id'),
            ),
            'methods'  => (new PaymentMethodModel())->options('name'),
            'shipments' => (new ShipmentModel())->options('name'),
            'banks'    => (new BankAccountModel())->options(),
            'funding'  => (new FundingSourceModel())->options(),
            'reps'     => (new SalesRepModel())->activeOptions(array_filter([(int) ($invoice['sales_rep_id'] ?? 0)])),
        ]);
    }

    /**
     * Save a DRAFT invoice.
     *
     * @param int $id Invoice id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->guardInvoice($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->update($id, $this->postFields(InvoiceService::HEADER_FIELDS), $this->postedItems());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Invoice update');
        }

        return redirect()->to("invoices/{$id}")->with('message', lang('Messages.draftSaved'));
    }

    /**
     * Issue a DRAFT invoice (number, due date, expected withholding, lock).
     *
     * @param int $id Invoice id.
     *
     * @return RedirectResponse
     */
    public function issue(int $id): RedirectResponse
    {
        $this->guardInvoice($id);

        $this->findOr404($this->model, $id);

        try {
            $number = $this->service->issue($id, $this->userId());
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Invoice issue');
        }

        return redirect()->to("invoices/{$id}")->with('message', lang('Messages.invoiceIssued', [$number]));
    }

    /**
     * Void an issued invoice (invoices.void; reason required).
     *
     * @param int $id Invoice id.
     *
     * @return RedirectResponse
     */
    public function void(int $id): RedirectResponse
    {
        $this->guardInvoice($id);

        $this->findOr404($this->model, $id);
        $user = auth()->user();

        try {
            $this->service->void($id, (string) $this->request->getPost('void_reason'), static fn (string $p): bool => $user->can($p), $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to("invoices/{$id}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Invoice void');
        }

        return redirect()->to("invoices/{$id}")->with('message', lang('Messages.invoiceVoided'));
    }

    /**
     * Delete a DRAFT invoice.
     *
     * @param int $id Invoice id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $this->guardInvoice($id);

        $invoice = $this->findOr404($this->model, $id);

        try {
            $this->service->deleteDraft($id);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Invoice delete');
        }

        $target = $invoice['proposal_id'] ? 'proposals/' . $invoice['proposal_id'] : 'invoices';

        return redirect()->to($target)->with('message', lang('Messages.draftDeleted'));
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
            'quantity'        => $item['quantity'],
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
