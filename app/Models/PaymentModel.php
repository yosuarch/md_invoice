<?php

declare(strict_types=1);

namespace App\Models;

/**
 * payment: cash received for an invoice (recorded through App\Services\PaymentService, never edited or deleted;
 * a failed or wrong payment is reversed: reversed_at / reversed_by / reversal_reason).
 */
class PaymentModel extends BaseModel
{
    protected $table         = 'payment';
    protected $allowedFields = [
        'invoice_id', 'receipt_number', 'payment_date', 'amount', 'payment_method_id', 'bank_account_id', 'reference_number', 'note', 'recorded_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];
    protected array $nullable    = ['bank_account_id', 'reference_number', 'note'];
    protected array $fieldLabels = [
        'payment_date'      => 'Payment date',
        'payment_method_id' => 'Payment method',
        'bank_account_id'   => 'Received on bank account',
        'reference_number'  => 'Reference',
    ];

    protected $validationRules = [
        'payment_date'      => 'required|valid_date[Y-m-d]',
        'payment_method_id' => 'required|is_natural_no_zero|is_not_unique[payment_method.id]',
        'bank_account_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[bank_account_list.id]',
        'reference_number'  => 'permit_empty|max_length[64]',
        'note'              => 'permit_empty|max_length[2000]',
    ];

    /**
     * Payments with method, bank, invoice, customer, recording/reversing user and the withheld total.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select(
            'payment.*, payment_method.name AS method_name, bank_account_list.bank_name, bank_account_list.bank_account_number,'
            . ' invoice.invoice_number, invoice.customer_id, customer_list.cl_organization_name AS customer_name,'
            . ' users.username, users.full_name, rv.username AS reversed_username, rv.full_name AS reversed_full_name,'
            . ' (SELECT COALESCE(SUM(pw.amount), 0) FROM payment_withholding pw WHERE pw.payment_id = payment.id AND pw.deleted_at IS NULL) AS withheld',
        )
            ->join('payment_method', 'payment_method.id = payment.payment_method_id')
            ->join('bank_account_list', 'bank_account_list.id = payment.bank_account_id', 'left')
            ->join('invoice', 'invoice.id = payment.invoice_id')
            ->join('customer_list', 'customer_list.id = invoice.customer_id')
            ->join('users', 'users.id = payment.recorded_by', 'left')
            ->join('users rv', 'rv.id = payment.reversed_by', 'left');
    }

    /**
     * Payment list with search (invoice number, customer, reference) and an optional month (Y-m).
     *
     * @param array{q?: string, month?: string} $filters
     *
     * @return $this
     */
    public function search(array $filters): self
    {
        $this->withDetails()->orderBy('payment.payment_date', 'DESC')->orderBy('payment.id', 'DESC');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('invoice.invoice_number', $q)
                ->orLike('customer_list.cl_organization_name', $q)
                ->orLike('payment.reference_number', $q)
                ->groupEnd();
        }

        $month = (string) ($filters['month'] ?? '');

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            $this->where('payment.payment_date >=', $month . '-01')
                ->where('payment.payment_date <=', date('Y-m-t', strtotime($month . '-01')));
        }

        return $this;
    }

    /**
     * Payments of one invoice, oldest first.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>>
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->withDetails()
            ->where('payment.invoice_id', $invoiceId)
            ->orderBy('payment.payment_date')
            ->orderBy('payment.id')
            ->findAll();
    }

    /**
     * Cash received in a month (reversed payments excluded).
     *
     * @param string $month Y-m.
     *
     * @return string Decimal string.
     */
    public function receivedInMonth(string $month): string
    {
        $row = $this->db->table('payment')
            ->select('COALESCE(SUM(amount), 0) AS total')
            ->where('deleted_at', null)
            ->where('reversed_at', null)
            ->where('payment_date >=', $month . '-01')
            ->where('payment_date <=', date('Y-m-t', strtotime($month . '-01')))
            ->get()->getRowArray();

        return (string) ($row['total'] ?? '0');
    }
}
