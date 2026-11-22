<?php

declare(strict_types=1);

namespace App\Models;

/**
 * refund: money paid back to a customer from an invoice's credit balance (overpayment, or credit notes after
 * payment such as a cancellation). Recorded through App\Services\RefundService; never edited or deleted,
 * a failed refund transfer is reversed (reversed_at / reversed_by / reversal_reason).
 */
class RefundModel extends BaseModel
{
    protected $table         = 'refund';
    protected $allowedFields = [
        'invoice_id', 'refund_date', 'amount', 'payment_method_id', 'bank_account_id', 'reference_number', 'reason',
        'recorded_by', 'reversed_at', 'reversed_by', 'reversal_reason',
    ];
    protected array $nullable    = ['bank_account_id', 'reference_number'];
    protected array $fieldLabels = [
        'refund_date'       => 'Refund date',
        'payment_method_id' => 'Payment method',
        'bank_account_id'   => 'Paid from bank account',
        'reference_number'  => 'Reference',
    ];

    protected $validationRules = [
        'refund_date'       => 'required|valid_date[Y-m-d]',
        'payment_method_id' => 'required|is_natural_no_zero|is_not_unique[payment_method.id]',
        'bank_account_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[bank_account_list.id]',
        'reference_number'  => 'permit_empty|max_length[64]',
        'reason'            => 'required|max_length[2000]',
    ];

    /**
     * Refunds of one invoice with method, bank and user names, oldest first.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>>
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->select(
            'refund.*, payment_method.name AS method_name, bank_account_list.bank_name, users.username, users.full_name,'
            . ' rv.username AS reversed_username, rv.full_name AS reversed_full_name',
        )
            ->join('payment_method', 'payment_method.id = refund.payment_method_id')
            ->join('bank_account_list', 'bank_account_list.id = refund.bank_account_id', 'left')
            ->join('users', 'users.id = refund.recorded_by', 'left')
            ->join('users rv', 'rv.id = refund.reversed_by', 'left')
            ->where('refund.invoice_id', $invoiceId)
            ->orderBy('refund.refund_date')
            ->orderBy('refund.id')
            ->findAll();
    }
}
