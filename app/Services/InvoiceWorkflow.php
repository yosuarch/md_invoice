<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Invoice statuses (invoice_status.code) and how they change:
 *
 *   DRAFT ──issue──> ISSUED ──payment──> PARTIALLY_PAID ──payment──> PAID
 *                      │  └──(due date passed, command)──> OVERDUE ──payment──> PARTIALLY_PAID / PAID
 *                      ├──void (admin, nothing paid or credited)──> VOID
 *                      └──credit notes for the whole invoice (cancellation after issue)──> CANCELLED
 *
 * Payment statuses are derived from the amounts (statusFor()), never chosen by hand; reversing a payment
 * moves the invoice back. A DRAFT can be edited or deleted; every other status is locked (database.md §11.5).
 */
final class InvoiceWorkflow
{
    public const DRAFT          = 'DRAFT';
    public const ISSUED         = 'ISSUED';
    public const PARTIALLY_PAID = 'PARTIALLY_PAID';
    public const PAID           = 'PAID';
    public const OVERDUE        = 'OVERDUE';
    public const VOID           = 'VOID';
    public const CANCELLED      = 'CANCELLED';

    /**
     * Display labels per status code.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        self::DRAFT          => 'Draft',
        self::ISSUED         => 'Issued',
        self::PARTIALLY_PAID => 'Partially paid',
        self::PAID           => 'Paid',
        self::OVERDUE        => 'Overdue',
        self::VOID           => 'Void',
        self::CANCELLED      => 'Cancelled',
    ];

    /**
     * Statuses that accept payments (issued and not fully paid).
     *
     * @var list<string>
     */
    public const PAYABLE = [self::ISSUED, self::PARTIALLY_PAID, self::OVERDUE];

    /**
     * Statuses that accept credit notes (issued, not void, not yet fully credited).
     *
     * @var list<string>
     */
    public const CREDITABLE = [self::ISSUED, self::PARTIALLY_PAID, self::OVERDUE, self::PAID];

    /**
     * Label for a status code.
     *
     * @param string|null $code Status code.
     *
     * @return string
     */
    public static function label(?string $code): string
    {
        return code_label('invoiceStatus', $code, self::LABELS[$code ?? ''] ?? (string) $code);
    }

    /**
     * Payment status from the amounts (database.md §11.6):
     * fully credited → CANCELLED; balance <= 0 → PAID; past due → OVERDUE; something received → PARTIALLY_PAID; else ISSUED.
     *
     * @param string $balance       grand_total − credit_total − amount_paid − withheld_total + refunded_total.
     * @param string $received      amount_paid + withheld_total.
     * @param string $dueDate       Y-m-d.
     * @param string $today         Y-m-d.
     * @param bool   $fullyCredited credit_total >= grand_total.
     *
     * @return string Status code.
     */
    public static function statusFor(string $balance, string $received, string $dueDate, string $today, bool $fullyCredited = false): string
    {
        return match (true) {
            $fullyCredited                 => self::CANCELLED,
            bccomp($balance, '0', 2) <= 0  => self::PAID,
            $dueDate < $today              => self::OVERDUE,
            bccomp($received, '0', 2) > 0  => self::PARTIALLY_PAID,
            default                        => self::ISSUED,
        };
    }
}
