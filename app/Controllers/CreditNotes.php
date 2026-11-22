<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CreditNoteItemModel;
use App\Models\CreditNoteModel;
use App\Models\InvoiceModel;
use App\Services\CreditNoteService;
use App\Services\InvoiceWorkflow;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Credit notes (permission credit_notes.manage to list and create; detail visible with invoices.view).
 */
class CreditNotes extends BaseController
{
    /**
     * Credit note list with search.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = ['q' => trim((string) $this->request->getGet('q'))];
        $model   = new CreditNoteModel();

        return view('credit_notes/index', [
            'notes'   => $this->scope()->invoices($model->search($filters))->paginate(25),
            'pager'   => $model->pager,
            'filters' => $filters,
        ]);
    }

    /**
     * Credit note detail.
     *
     * @param int $id Credit note id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $this->guardInvoice((int) ((new CreditNoteModel())->find($id)['invoice_id'] ?? 0));

        $note = $this->findOr404((new CreditNoteModel())->withDetails(), $id);

        return view('credit_notes/show', [
            'note'  => $note,
            'items' => (new CreditNoteItemModel())->forCreditNote($id),
        ]);
    }

    /**
     * Credit note form for an invoice: one row per invoice line.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse|string
     */
    public function new(int $invoiceId): RedirectResponse|string
    {
        $this->guardInvoice($invoiceId);

        $invoice = $this->findOr404((new InvoiceModel())->withDetails(), $invoiceId);

        if (! in_array($invoice['status_code'], InvoiceWorkflow::CREDITABLE, true)) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', lang('Messages.creditOnlyCreditableShort'));
        }

        $service = new CreditNoteService();

        return view('credit_notes/form', [
            'invoice'  => $invoice,
            'lines'    => $service->creditableLines($invoiceId),
            'shipping' => $service->creditableShipping($invoice),
        ]);
    }

    /**
     * Store a credit note.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse
     */
    public function create(int $invoiceId): RedirectResponse
    {
        $this->guardInvoice($invoiceId);

        $this->findOr404(new InvoiceModel(), $invoiceId);
        $lines = $this->request->getPost('lines');

        try {
            $id = (new CreditNoteService())->create(
                $invoiceId,
                $this->postFields(['reason', 'reason_type', 'shipping_amount']),
                is_array($lines) ? $lines : [],
                $this->userId(),
            );
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Credit note create');
        }

        $number = (new CreditNoteModel())->find($id)['credit_note_number'];

        return redirect()->to("invoices/{$invoiceId}")->with('message', lang('Messages.creditCreated', [$number]));
    }
}
