<?php

declare(strict_types=1);

namespace App\Models;

/**
 * payment_withholding: tax actually withheld/collected by the treasurer with a payment,
 * with its proof (bukti potong number or NTPN). Only the proof fields may be completed later.
 */
class PaymentWithholdingModel extends BaseModel
{
    protected $table         = 'payment_withholding';
    protected $allowedFields = ['payment_id', 'invoice_withholding_id', 'tax_kind', 'amount', 'document_number', 'document_date'];
    protected array $nullable    = ['invoice_withholding_id', 'document_number', 'document_date'];
    protected array $fieldLabels = ['document_number' => 'Proof number', 'document_date' => 'Proof date'];

    protected $validationRules = [
        'document_number' => 'permit_empty|max_length[64]',
        'document_date'   => 'permit_empty|valid_date[Y-m-d]',
    ];

    /**
     * Withholding rows of all payments of an invoice, grouped by payment id.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forInvoiceByPayment(int $invoiceId): array
    {
        $rows = $this->select('payment_withholding.*')
            ->join('payment', 'payment.id = payment_withholding.payment_id')
            ->where('payment.invoice_id', $invoiceId)
            ->orderBy('payment_withholding.id')
            ->findAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['payment_id']][] = $row;
        }

        return $grouped;
    }
}
