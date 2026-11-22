<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditNoteItemModel;
use App\Models\CreditNoteModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Credit notes (database.md §11.7): the way to correct an issued invoice.
 *
 * Each credited line refers to an invoice line and copies its title, unit, type and tax rate.
 * Per line: quantity <= invoiced quantity, unit price <= invoiced unit price, discount proportional to the quantity,
 * and everything credited on that line together <= the invoice line total. Shipping can be credited up to the
 * invoice shipping cost. Paid invoices can be credited too: if the credit exceeds what is still unpaid, the invoice
 * gets a credit balance that is paid back with a refund (RefundService). Crediting everything (e.g. reason
 * CANCEL_BY_CLIENT / CANCEL_BY_STORE) moves the invoice to CANCELLED. Number from document_sequence (CN);
 * invoice.credit_total and the invoice status are updated in the same transaction.
 */
final class CreditNoteService
{
    public function __construct(
        private readonly CreditNoteModel $notes = new CreditNoteModel(),
        private readonly CreditNoteItemModel $items = new CreditNoteItemModel(),
        private readonly InvoiceService $invoiceService = new InvoiceService(),
    ) {
    }

    /**
     * Create a credit note for an issued invoice (not void, not yet fully credited).
     *
     * @param int                                     $invoiceId Invoice id.
     * @param array{reason?: string, reason_type?: string, shipping_amount?: string} $header Reason (required),
     *        reason type (CreditNoteModel::REASON_TYPES) and shipping cost to credit.
     * @param array<int|string, array<string, mixed>> $posted    Lines keyed by invoice_item_id: [quantity, unit_price].
     * @param int                                     $userId    Creating user.
     *
     * @return int New credit note id.
     *
     * @throws ValidationFailed Field errors (reason, reason_type, shipping_amount, "lines.<invoice_item_id>", lines).
     * @throws RuntimeException When the invoice cannot be credited.
     */
    public function create(int $invoiceId, array $header, array $posted, int $userId): int
    {
        $reason     = trim((string) ($header['reason'] ?? ''));
        $reasonType = trim((string) ($header['reason_type'] ?? ''));
        $shipping   = trim((string) ($header['shipping_amount'] ?? ''));
        $shipping   = $shipping === '' ? '0' : $shipping;

        return Transaction::run(function (BaseConnection $db) use ($invoiceId, $reason, $reasonType, $shipping, $posted, $userId): int {
            $invoice = $this->invoiceService->lockInvoice($db, $invoiceId);

            if (! in_array($invoice['status_code'], InvoiceWorkflow::CREDITABLE, true)) {
                throw new RuntimeException(lang('Messages.creditOnlyCreditable', [InvoiceWorkflow::label($invoice['status_code'])]));
            }

            [$lines, $errors] = $this->prepareLines($invoiceId, $posted);

            if ($reason === '') {
                $errors['reason'] = lang('Messages.creditReason');
            } elseif (mb_strlen($reason) > 2000) {
                $errors['reason'] = lang('Messages.reasonTooLong');
            }

            if (! array_key_exists($reasonType, CreditNoteModel::REASON_TYPES)) {
                $errors['reason_type'] = lang('Messages.creditReasonType');
            }

            $shippingLeft = $this->creditableShipping($invoice);

            if (! DocumentLines::isDecimal($shipping, 2)) {
                $errors['shipping_amount'] = lang('Messages.creditShipping');
            } elseif (bccomp($shipping, $shippingLeft, 2) > 0) {
                $errors['shipping_amount'] = lang('Messages.creditShippingMax', [format_idr($shippingLeft)]);
            }

            if ($errors === [] && $lines === [] && bccomp($shipping, '0', 2) === 0) {
                $errors['lines'] = lang('Messages.creditNeedsLine');
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $totals     = DocumentTotals::calculate($lines, $shipping);
            $uncredited = bcsub((string) $invoice['grand_total'], (string) $invoice['credit_total'], 2);

            if (bccomp($totals['grand_total'], $uncredited, 2) > 0) {
                throw new ValidationFailed(['lines' => lang('Messages.creditTooHigh', [format_idr($totals['grand_total']), format_idr($uncredited)])]);
            }

            if (bccomp($totals['grand_total'], '0', 2) <= 0) {
                throw new ValidationFailed(['lines' => lang('Messages.creditPositive')]);
            }

            $id = $this->notes->insert([
                'credit_note_number' => (new DocumentSequence())->next('CN'),
                'invoice_id'         => $invoiceId,
                'issue_date'         => date('Y-m-d'),
                'reason_type'        => $reasonType,
                'reason'             => $reason,
                'subtotal'           => $totals['subtotal'],
                'tax_total'          => $totals['tax_total'],
                'shipping_amount'    => CalcSettings::current()->round($shipping),
                'grand_total'        => $totals['grand_total'],
                'created_by'         => $userId,
            ]);

            if ($id === false) {
                throw new ValidationFailed($this->notes->errors());
            }

            foreach ($lines as $i => $line) {
                $this->items->insert($line + [
                    'credit_note_id' => (int) $id,
                    'tax_amount'     => $totals['lines'][$i]['tax_amount'],
                    'line_total'     => $totals['lines'][$i]['line_total'],
                ]);
            }

            (new InvoiceModel())->update($invoiceId, ['credit_total' => bcadd((string) $invoice['credit_total'], $totals['grand_total'], 2)]);
            $this->invoiceService->refreshStatus($invoiceId);

            return (int) $id;
        });
    }

    /**
     * Shipping cost not yet credited on an invoice.
     *
     * @param array<string, mixed> $invoice Invoice row.
     *
     * @return string Decimal string >= 0.
     */
    public function creditableShipping(array $invoice): string
    {
        $row  = db_connect()->query(
            'SELECT COALESCE(SUM(shipping_amount), 0) AS total FROM credit_note WHERE invoice_id = ? AND deleted_at IS NULL',
            [$invoice['id']],
        )->getRowArray();
        $left = bcsub((string) $invoice['shipping_cost'], (string) ($row['total'] ?? '0'), 2);

        return bccomp($left, '0', 2) > 0 ? $left : '0.00';
    }

    /**
     * Invoice lines with what was already credited and what can still be credited (for the form).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>> Invoice item rows plus credited_quantity, credited_amount, creditable_amount.
     */
    public function creditableLines(int $invoiceId): array
    {
        $credited = $this->items->creditedByInvoiceItem($invoiceId);
        $rows     = [];

        foreach ((new InvoiceItemModel())->forInvoice($invoiceId) as $item) {
            $done = $credited[(int) $item['id']] ?? ['quantity' => '0', 'line_total' => '0'];

            $rows[] = $item + [
                'credited_quantity' => $done['quantity'],
                'credited_amount'   => bcadd($done['line_total'], '0', 2),
                'creditable_amount' => bcsub((string) $item['line_total'], $done['line_total'], 2),
            ];
        }

        return $rows;
    }

    /**
     * Validate posted lines against the invoice lines.
     *
     * @param int                                     $invoiceId Invoice id.
     * @param array<int|string, array<string, mixed>> $posted    Keyed by invoice_item_id.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>} [credit note lines, errors]
     */
    private function prepareLines(int $invoiceId, array $posted): array
    {
        $lines  = [];
        $errors = [];
        $n      = 0;
        $calc   = CalcSettings::current();

        foreach ($this->creditableLines($invoiceId) as $item) {
            $n++;
            $row      = is_array($posted[$item['id']] ?? null) ? $posted[$item['id']] : [];
            $quantity = trim((string) ($row['quantity'] ?? ''));
            $price    = trim((string) ($row['unit_price'] ?? ''));
            $key      = 'lines.' . $item['id'];

            if ($quantity === '' || (DocumentLines::isDecimal($quantity, 3) && bccomp($quantity, '0', 3) === 0)) {
                continue; // line not credited
            }

            $price = $price === '' ? (string) $item['unit_price'] : $price;

            $error = match (true) {
                ! DocumentLines::isDecimal($quantity, 3)                     => lang('Messages.lineQtyNumber'),
                bccomp($quantity, (string) $item['quantity'], 3) > 0         => lang('Messages.lineQtyMaxInvoiced', [format_qty($item['quantity'])]),
                ! DocumentLines::isDecimal($price, 6)                        => lang('Messages.linePrice'),
                bccomp($price, (string) $item['unit_price'], 6) > 0          => lang('Messages.linePriceMaxInvoice', [format_price($item['unit_price'])]),
                default                                                      => null,
            };

            $credit = [];

            if ($error === null) {
                // Discount in proportion to the credited quantity. A line priced incl. tax and credited at its invoice
                // price is credited incl. tax as well, so crediting all of it gives back exactly its incl. total.
                $share = static fn (string $amount): string => $calc->round(bcdiv(bcmul($amount, $quantity, 12), (string) $item['quantity'], 12));

                if (DocumentTotals::isIncl($item) && bccomp($price, (string) $item['unit_price'], 6) === 0) {
                    $discountIncl = $item['discount_incl'] ?? null;
                    $discountIncl = $discountIncl !== null ? (string) $discountIncl : DocumentTotals::inclFromExcl((string) $item['discount_amount'], $item['tax_rate']);
                    $credit = ['price_base' => 'INCL', 'unit_price_incl' => $item['unit_price_incl'], 'discount_incl' => $share($discountIncl)];
                } else {
                    $credit = ['price_base' => 'EXCL', 'unit_price_incl' => null, 'discount_incl' => null, 'discount_amount' => $share((string) $item['discount_amount'])];
                }

                $amounts   = DocumentTotals::calculate([['quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => $item['tax_rate']] + $credit]);
                $lineTotal = $amounts['lines'][0]['line_total'];
                $discount  = $amounts['lines'][0]['discount_amount'];

                // One amount step of tolerance: with tax per document total the invoice line amounts were spread.
                if (bccomp($lineTotal, '0', 2) < 0) {
                    $error = lang('Messages.creditLineNegative');
                } elseif (bccomp($lineTotal, bcadd((string) $item['creditable_amount'], $calc->taxMode === 'TOTAL' ? $calc->step() : '0', 2), 2) > 0) {
                    $error = lang('Messages.creditLineMax', [format_idr($item['creditable_amount'])]);
                }
            }

            if ($error !== null) {
                $errors[$key] = lang('Messages.lineNamed', [$n, up($item['item_title']), $error]);

                continue;
            }

            $lines[] = [
                'invoice_item_id' => (int) $item['id'],
                'product_id'      => (int) $item['product_id'],
                'variant_id'      => $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
                'item_title'      => $item['item_title'],
                'item_description' => $item['item_description'],
                'unit_code'       => $item['unit_code'],
                'item_type'       => $item['item_type'],
                'quantity'        => $quantity,
                'unit_price'      => $price,
                'discount_amount' => $discount,
                'tax_rate'        => $item['tax_rate'],
                'tax_code'        => $item['tax_code'],
                'price_base'      => $credit['price_base'],
                'unit_price_incl' => $credit['unit_price_incl'],
                'discount_incl'   => $credit['discount_incl'],
            ];
        }

        return [$lines, $errors];
    }
}
