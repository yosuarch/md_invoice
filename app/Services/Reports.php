<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceModel;

/**
 * Finance reports (read-only):
 * - receivables(): open invoices with aging buckets by days past due, per customer;
 * - withholding(): expected treasurer withholding (invoice_withholding) against what was recorded with payments
 *   (payment_withholding of payments that are not reversed), to reconcile bukti potong / NTPN.
 * Both respect AccessScope (admin and finance see everything).
 */
final class Reports
{
    /** Aging buckets: key => [label, min days past due, max days (null = open end)]. */
    public const BUCKETS = [
        'current' => ['Not due', null, 0],
        'd1_30'   => ['1–30 days', 1, 30],
        'd31_60'  => ['31–60 days', 31, 60],
        'd61_90'  => ['61–90 days', 61, 90],
        'd90'     => ['> 90 days', 91, null],
    ];

    /** Reconciliation states of one expected / recorded withholding. */
    public const STATES = [
        'ok'            => 'Withheld, proof recorded',
        'missing_proof' => 'Withheld, proof number missing',
        'difference'    => 'Amount differs',
        'not_withheld'  => 'Not withheld yet',
        'unexpected'    => 'Withheld but not expected',
    ];

    public function __construct(private readonly AccessScope $scope)
    {
    }

    /**
     * Open receivables on a date: invoices that are issued and not fully paid (ISSUED, PARTIALLY_PAID, OVERDUE)
     * with a positive balance. Each row has days past due, bucket, open balance and the part of it that the
     * treasurer is still expected to withhold (taxes), so "cash expected" = balance − taxes pending.
     *
     * @param string                                         $today   Y-m-d (days past due are counted from here).
     * @param array{q?: string, rep?: string, funding?: string} $filters
     *
     * @return array{rows: list<array<string, mixed>>, customers: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function receivables(string $today, array $filters = []): array
    {
        $model = (new InvoiceModel())->withDetails()
            ->whereIn('invoice_status.code', InvoiceWorkflow::PAYABLE)
            ->orderBy('invoice.due_date')
            ->orderBy('invoice.id');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $model->groupStart()->like('invoice.invoice_number', $q)->orLike('customer_list.cl_organization_name', $q)->orLike('customer_list.npsn', $q)->groupEnd();
        }

        if ((int) ($filters['rep'] ?? 0) > 0) {
            $model->where('invoice.sales_rep_id', (int) $filters['rep']);
        }

        if ((int) ($filters['funding'] ?? 0) > 0) {
            $model->where('invoice.funding_source_id', (int) $filters['funding']);
        }

        $rows      = [];
        $customers = [];
        $totals    = array_fill_keys(array_keys(self::BUCKETS), '0.00') + ['balance' => '0.00', 'taxes_pending' => '0.00', 'cash_expected' => '0.00'];

        foreach ($this->scope->invoices($model)->findAll() as $inv) {
            $balance = InvoiceModel::balance($inv);

            if (bccomp($balance, '0', 2) <= 0) {
                continue;
            }

            $days    = (int) floor((strtotime($today) - strtotime((string) $inv['due_date'])) / 86400);
            $bucket  = self::bucketFor($days);
            $pending = bcsub((string) $inv['withholding_total'], (string) $inv['withheld_total'], 2);
            $pending = bccomp($pending, '0', 2) > 0 ? (bccomp($pending, $balance, 2) > 0 ? $balance : $pending) : '0.00';
            $cash    = bcsub($balance, $pending, 2);

            $rows[] = $inv + ['balance' => $balance, 'days_past_due' => $days, 'bucket' => $bucket, 'taxes_pending' => $pending, 'cash_expected' => $cash];

            $cid = (int) $inv['customer_id'];
            $customers[$cid] ??= ['customer_id' => $cid, 'customer_name' => $inv['customer_name'], 'npsn' => $inv['npsn'], 'invoices' => 0, 'balance' => '0.00']
                + array_fill_keys(array_keys(self::BUCKETS), '0.00');
            $customers[$cid]['invoices']++;
            $customers[$cid][$bucket]   = bcadd($customers[$cid][$bucket], $balance, 2);
            $customers[$cid]['balance'] = bcadd($customers[$cid]['balance'], $balance, 2);

            $totals[$bucket]          = bcadd($totals[$bucket], $balance, 2);
            $totals['balance']        = bcadd($totals['balance'], $balance, 2);
            $totals['taxes_pending']  = bcadd($totals['taxes_pending'], $pending, 2);
            $totals['cash_expected']  = bcadd($totals['cash_expected'], $cash, 2);
        }

        $customers = array_values($customers);
        usort($customers, static fn (array $a, array $b): int => bccomp($b['balance'], $a['balance'], 2));

        return ['rows' => $rows, 'customers' => $customers, 'totals' => $totals];
    }

    /**
     * Aging bucket for a number of days past due.
     *
     * @param int $days Days past the due date (0 or negative = not due).
     *
     * @return string Bucket key.
     */
    public static function bucketFor(int $days): string
    {
        foreach (self::BUCKETS as $key => [, $min, $max]) {
            if (($min === null || $days >= $min) && ($max === null || $days <= $max)) {
                return $key;
            }
        }

        return 'd90';
    }

    /**
     * Withholding reconciliation for invoices issued in a period: one row per expected withholding (with what was
     * recorded against it) plus rows for recorded taxes that were not expected.
     *
     * @param string                                   $from    Y-m-d (issue date from).
     * @param string                                   $to      Y-m-d (issue date to).
     * @param array{kind?: string, state?: string, q?: string} $filters kind = tax_kind, state = STATES key or 'open'
     *        (everything except ok).
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, array{expected: string, withheld: string, count: int, open: int}>, totals: array{expected: string, withheld: string}}
     */
    public function withholding(string $from, string $to, array $filters = []): array
    {
        $db       = db_connect();
        $invoices = (new InvoiceModel())->withDetails()
            ->where('invoice.issue_date >=', $from)
            ->where('invoice.issue_date <=', $to)
            ->where('invoice.issued_at IS NOT NULL', null, false)
            ->where('invoice_status.code !=', InvoiceWorkflow::VOID);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $invoices->groupStart()->like('invoice.invoice_number', $q)->orLike('customer_list.cl_organization_name', $q)->groupEnd();
        }

        $byId = array_column($this->scope->invoices($invoices)->orderBy('invoice.issue_date')->orderBy('invoice.id')->findAll(), null, 'id');

        if ($byId === []) {
            return ['rows' => [], 'summary' => [], 'totals' => ['expected' => '0.00', 'withheld' => '0.00']];
        }

        $ids      = array_map('intval', array_keys($byId));
        $expected = $db->table('invoice_withholding')->whereIn('invoice_id', $ids)->orderBy('invoice_id')->orderBy('id')->get()->getResultArray();
        $recorded = $db->table('payment_withholding pw')
            ->select('pw.*, p.invoice_id, p.payment_date, p.receipt_number')
            ->join('payment p', 'p.id = pw.payment_id')
            ->whereIn('p.invoice_id', $ids)
            ->where('p.reversed_at', null)
            ->where('p.deleted_at', null)
            ->orderBy('pw.id')
            ->get()->getResultArray();

        $byExpected = [];
        $unexpected = [];

        foreach ($recorded as $r) {
            if ($r['invoice_withholding_id'] !== null) {
                $byExpected[(int) $r['invoice_withholding_id']][] = $r;
            } else {
                $unexpected[] = $r;
            }
        }

        $rows = [];

        foreach ($expected as $e) {
            $records  = $byExpected[(int) $e['id']] ?? [];
            $withheld = '0.00';
            $proofs   = [];
            $missing  = false;

            foreach ($records as $r) {
                $withheld = bcadd($withheld, (string) $r['amount'], 2);
                $missing  = $missing || trim((string) $r['document_number']) === '';

                if (trim((string) $r['document_number']) !== '') {
                    $proofs[] = $r['document_number'] . ($r['document_date'] ? ' (' . format_date($r['document_date']) . ')' : '');
                }
            }

            $state = match (true) {
                $records === []                                          => 'not_withheld',
                bccomp($withheld, (string) $e['amount'], 2) !== 0        => 'difference',
                $missing                                                 => 'missing_proof',
                default                                                  => 'ok',
            };

            $rows[] = $this->withholdingRow($byId[$e['invoice_id']], (string) $e['tax_kind'], (string) $e['description'], (string) $e['base_amount'], (string) $e['amount'], $withheld, $proofs, $state);
        }

        foreach ($unexpected as $r) {
            $proof  = trim((string) $r['document_number']) !== '' ? [$r['document_number']] : [];
            $rows[] = $this->withholdingRow($byId[$r['invoice_id']], (string) $r['tax_kind'], 'Recorded with payment ' . ($r['receipt_number'] ?? ''), '0.00', '0.00', (string) $r['amount'], $proof, 'unexpected');
        }

        $kind  = (string) ($filters['kind'] ?? '');
        $state = (string) ($filters['state'] ?? '');
        $rows  = array_values(array_filter($rows, static fn (array $r): bool => ($kind === '' || $r['tax_kind'] === $kind)
            && ($state === '' || $r['state'] === $state || ($state === 'open' && $r['state'] !== 'ok'))));

        $summary = [];
        $totals  = ['expected' => '0.00', 'withheld' => '0.00'];

        foreach ($rows as $r) {
            $summary[$r['tax_kind']] ??= ['expected' => '0.00', 'withheld' => '0.00', 'count' => 0, 'open' => 0];
            $summary[$r['tax_kind']]['expected'] = bcadd($summary[$r['tax_kind']]['expected'], $r['expected'], 2);
            $summary[$r['tax_kind']]['withheld'] = bcadd($summary[$r['tax_kind']]['withheld'], $r['withheld'], 2);
            $summary[$r['tax_kind']]['count']++;
            $summary[$r['tax_kind']]['open'] += $r['state'] === 'ok' ? 0 : 1;
            $totals['expected'] = bcadd($totals['expected'], $r['expected'], 2);
            $totals['withheld'] = bcadd($totals['withheld'], $r['withheld'], 2);
        }

        ksort($summary);

        return ['rows' => $rows, 'summary' => $summary, 'totals' => $totals];
    }

    /**
     * @param array<string, mixed> $invoice
     * @param list<string>         $proofs
     *
     * @return array<string, mixed>
     */
    private function withholdingRow(array $invoice, string $kind, string $description, string $base, string $expected, string $withheld, array $proofs, string $state): array
    {
        return [
            'invoice_id'     => (int) $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'issue_date'     => $invoice['issue_date'],
            'customer_name'  => $invoice['customer_name'],
            'npsn'           => $invoice['npsn'],
            'funding'        => $invoice['funding_source_name'],
            'tax_kind'       => $kind,
            'description'    => $description,
            'base_amount'    => $base,
            'expected'       => $expected,
            'withheld'       => $withheld,
            'proofs'         => $proofs,
            'state'          => $state,
        ];
    }
}
