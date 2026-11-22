<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankAccountModel;
use App\Models\InvoiceModel;
use App\Models\PaymentMethodModel;
use App\Models\RefundModel;
use App\Services\RefundService;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Refunds to customers from an invoice's credit balance (permission payments.record);
 * reversing a failed refund needs payments.reverse.
 */
class Refunds extends BaseController
{
    /**
     * Refund form.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse|string
     */
    public function new(int $invoiceId): RedirectResponse|string
    {
        $this->guardInvoice($invoiceId);

        $invoice    = $this->findOr404((new InvoiceModel())->withDetails(), $invoiceId);
        $refundable = InvoiceModel::refundable($invoice);

        if (bccomp($refundable, '0', 2) <= 0) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', lang('Messages.nothingToRefund'));
        }

        return view('refunds/form', [
            'invoice'    => $invoice,
            'refundable' => $refundable,
            'methods'    => (new PaymentMethodModel())->options('name'),
            'banks'      => (new BankAccountModel())->options(),
        ]);
    }

    /**
     * Store a refund.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse
     */
    public function create(int $invoiceId): RedirectResponse
    {
        $this->guardInvoice($invoiceId);

        $this->findOr404(new InvoiceModel(), $invoiceId);

        try {
            (new RefundService())->record($invoiceId, $this->postFields(RefundService::FIELDS), $this->userId());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Refund record');
        }

        return redirect()->to("invoices/{$invoiceId}")->with('message', lang('Messages.refundRecorded'));
    }

    /**
     * Reverse a failed or mistaken refund (payments.reverse; reason required). The record is kept.
     *
     * @param int $id Refund id.
     *
     * @return RedirectResponse
     */
    public function reverse(int $id): RedirectResponse
    {
        $this->guardInvoice((int) ((new RefundModel())->find($id)['invoice_id'] ?? 0));

        $refund = $this->findOr404(new RefundModel(), $id);
        $user   = auth()->user();
        $target = 'invoices/' . $refund['invoice_id'];

        try {
            (new RefundService())->reverse($id, (string) $this->request->getPost('reversal_reason'), static fn (string $p): bool => $user->can($p), $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to($target)->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to($target)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Refund reversal');
        }

        return redirect()->to($target)->with('message', lang('Messages.refundReversed'));
    }
}
