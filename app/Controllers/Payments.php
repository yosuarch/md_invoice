<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankAccountModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentMethodModel;
use App\Models\PaymentModel;
use App\Models\PaymentWithholdingModel;
use App\Services\InvoiceWorkflow;
use App\Services\PaymentService;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Payments (permission payments.view / payments.record): list, record a payment for an invoice
 * (cash + withheld taxes), and complete withholding proofs later.
 */
class Payments extends BaseController
{
    /**
     * Payment list with search and month filter.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'q'     => trim((string) $this->request->getGet('q')),
            'month' => (string) $this->request->getGet('month'),
        ];

        $model    = new PaymentModel();
        $payments = $this->scope()->invoices($model->search($filters))->paginate(25);

        return view('payments/index', [
            'payments' => $payments,
            'pager'    => $model->pager,
            'filters'  => $filters,
            'monthSum' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filters['month']) === 1 ? $model->receivedInMonth($filters['month']) : null,
        ]);
    }

    /**
     * Record-payment form for an invoice.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse|string
     */
    public function new(int $invoiceId): RedirectResponse|string
    {
        $this->guardInvoice($invoiceId);

        $invoice = $this->findOr404((new InvoiceModel())->withDetails(), $invoiceId);

        if (! in_array($invoice['status_code'], InvoiceWorkflow::PAYABLE, true)) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', lang('Messages.noPaymentsAccepted', [InvoiceWorkflow::label($invoice['status_code'])]));
        }

        $defaults = PaymentService::defaults($invoice, (new InvoiceWithholdingModel())->forInvoice($invoiceId));
        $old      = old('withholdings', null, false);

        if (is_array($old)) {
            // Keep the posted values but restore the labels of the expected rows.
            foreach ($defaults['withholdings'] as $i => $row) {
                $defaults['withholdings'][$i] = array_merge($row, array_intersect_key($old[$i] ?? [], array_flip(['amount', 'document_number', 'document_date'])));
            }

            $defaults['extra'] = $old['extra'] ?? [];
        }

        return view('payments/form', [
            'invoice'  => $invoice,
            'defaults' => $defaults,
            'methods'  => (new PaymentMethodModel())->options('name'),
            'banks'    => (new BankAccountModel())->options(),
            'kinds'    => InvoiceWithholdingModel::KINDS,
            'transferId' => $this->transferId(),
        ]);
    }

    /**
     * Store a payment.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse
     */
    public function create(int $invoiceId): RedirectResponse
    {
        $this->guardInvoice($invoiceId);

        $this->findOr404(new InvoiceModel(), $invoiceId);

        $posted = $this->request->getPost('withholdings');
        $rows   = [];

        if (is_array($posted)) {
            foreach ($posted as $key => $row) {
                if (is_array($row)) {
                    $rows[] = $key === 'extra' ? array_merge($row, ['invoice_withholding_id' => '']) : $row;
                }
            }
        }

        try {
            (new PaymentService())->record(
                $invoiceId,
                $this->postFields(PaymentService::FIELDS),
                $rows,
                $this->userId(),
                (string) $this->request->getPost('accept_overpayment') === '1',
            );
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Payment record');
        }

        return redirect()->to("invoices/{$invoiceId}")->with('message', lang('Messages.paymentRecorded'));
    }

    /**
     * Complete the proof (bukti potong / NTPN) of a withheld tax.
     *
     * @param int $id payment_withholding id.
     *
     * @return RedirectResponse
     */
    public function proof(int $id): RedirectResponse
    {
        $this->guardInvoice((int) (new PaymentModel())->select('payment.invoice_id')->join('payment_withholding pw', 'pw.payment_id = payment.id')->where('pw.id', $id)->first()['invoice_id'] ?? 0);

        $row     = $this->findOr404(new PaymentWithholdingModel(), $id);
        $payment = $this->findOr404(new PaymentModel(), $row['payment_id']);
        $target  = 'invoices/' . $payment['invoice_id'];

        try {
            (new PaymentService())->updateProof($id, (string) $this->request->getPost('document_number'), (string) $this->request->getPost('document_date'));
        } catch (ValidationFailed $e) {
            return redirect()->to($target)->with('error', implode(' ', $e->errors));
        } catch (Throwable $e) {
            return $this->failed($e, 'Withholding proof update');
        }

        return redirect()->to($target)->with('message', lang('Messages.proofSaved'));
    }

    /**
     * Reverse a failed or mistaken payment (permission payments.reverse; reason required). The record is kept.
     *
     * @param int $id Payment id.
     *
     * @return RedirectResponse
     */
    public function reverse(int $id): RedirectResponse
    {
        $this->guardInvoice((int) ((new PaymentModel())->find($id)['invoice_id'] ?? 0));

        $payment = $this->findOr404(new PaymentModel(), $id);
        $user    = auth()->user();
        $target  = 'invoices/' . $payment['invoice_id'];

        try {
            (new PaymentService())->reverse($id, (string) $this->request->getPost('reversal_reason'), static fn (string $p): bool => $user->can($p), $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to($target)->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to($target)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Payment reversal');
        }

        return redirect()->to($target)->with('message', lang('Messages.paymentReversed'));
    }

    /**
     * Id of the TRANSFER payment method (shows the bank account field), or null.
     *
     * @return int|null
     */
    private function transferId(): ?int
    {
        try {
            return (new PaymentMethodModel())->idForCode('TRANSFER');
        } catch (RuntimeException) {
            return null;
        }
    }
}
