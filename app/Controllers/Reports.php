<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\FundingSourceModel;
use App\Models\SalesRepModel;
use App\Services\Reports as ReportService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Finance reports: receivables aging, withholding reconciliation and lead times (permission reports.view).
 * ?export=csv downloads the rows as CSV for Excel (semicolon separated, decimal comma, UTF-8 with BOM).
 */
class Reports extends BaseController
{
    /**
     * Overview of the reports.
     *
     * @return string
     */
    public function index(): string
    {
        $today = date('Y-m-d');
        $recv  = $this->service()->receivables($today);
        $wh    = $this->service()->withholding(date('Y-m-01'), $today);

        return view('reports/index', [
            'receivables' => $recv['totals'],
            'pastDue'     => bcsub($recv['totals']['balance'], $recv['totals']['current'], 2),
            'withholding' => $wh['totals'],
            'openTaxes'   => count(array_filter($wh['rows'], static fn (array $r): bool => $r['state'] !== 'ok')),
        ]);
    }

    /**
     * Receivables aging (today), per customer and per invoice.
     *
     * @return ResponseInterface|string
     */
    public function receivables(): ResponseInterface|string
    {
        $filters = [
            'q'       => trim((string) $this->request->getGet('q')),
            'rep'     => (string) $this->request->getGet('rep'),
            'funding' => (string) $this->request->getGet('funding'),
            'view'    => $this->request->getGet('view') === 'invoices' ? 'invoices' : 'customers',
        ];
        $today  = date('Y-m-d');
        $report = $this->service()->receivables($today, $filters);

        if ($this->request->getGet('export') === 'csv') {
            return $this->csv('receivables-' . $today . '.csv',
                [lang('Reports.csvInvoice'), lang('Reports.csvCustomer'), 'NPSN', lang('Reports.csvIssueDate'), lang('Reports.csvDueDate'), lang('Reports.csvDaysPastDue'), lang('Reports.csvAging'), lang('Reports.csvStatus'), lang('Reports.csvSalesRep'), lang('Reports.csvFunding'), lang('Reports.csvTotal'), lang('Reports.csvOpenBalance'), lang('Reports.csvTaxesPending'), lang('Reports.csvCashExpected')],
                array_map(static fn (array $r): array => [
                    $r['invoice_number'], up($r['customer_name']), $r['npsn'], $r['issue_date'], $r['due_date'], $r['days_past_due'],
                    code_label('bucket', $r['bucket']), \App\Services\InvoiceWorkflow::label($r['status_code']), up($r['rep_name']), $r['funding_source_name'],
                    self::num($r['grand_total']), self::num($r['balance']), self::num($r['taxes_pending']), self::num($r['cash_expected']),
                ], $report['rows']));
        }

        return view('reports/receivables', [
            'report'  => $report,
            'filters' => $filters,
            'today'   => $today,
            'reps'    => $this->scope()->seesAll() ? (new SalesRepModel())->activeOptions() : [],
            'funding' => (new FundingSourceModel())->options(),
        ]);
    }

    /**
     * Withholding reconciliation for invoices issued in a period (default: this month).
     *
     * @return ResponseInterface|string
     */
    public function withholding(): ResponseInterface|string
    {
        $from = $this->date('from', date('Y-m-01'));
        $to   = $this->date('to', date('Y-m-d'));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'q'     => trim((string) $this->request->getGet('q')),
            'kind'  => in_array($this->request->getGet('kind'), ['PPN', 'PPH22', 'PPH23', 'PPH_FINAL'], true) ? (string) $this->request->getGet('kind') : '',
            'state' => array_key_exists((string) $this->request->getGet('state'), ReportService::STATES + ['open' => '']) ? (string) $this->request->getGet('state') : '',
        ];
        $report = $this->service()->withholding($from, $to, $filters);

        if ($this->request->getGet('export') === 'csv') {
            return $this->csv("withholding-{$from}-{$to}.csv",
                [lang('Reports.csvInvoice'), lang('Reports.csvIssueDate'), lang('Reports.csvCustomer'), 'NPSN', lang('Reports.csvFunding'), lang('Reports.csvTax'), lang('Reports.csvDescription'), lang('Reports.csvBase'), lang('Reports.csvExpected'), lang('Reports.csvWithheld'), lang('Reports.proof'), lang('Reports.state')],
                array_map(static fn (array $r): array => [
                    $r['invoice_number'], $r['issue_date'], up($r['customer_name']), $r['npsn'], $r['funding'], code_label('taxKind', $r['tax_kind']), $r['description'],
                    self::num($r['base_amount']), self::num($r['expected']), self::num($r['withheld']), implode(', ', $r['proofs']), code_label('reconState', $r['state']),
                ], $report['rows']));
        }

        return view('reports/withholding', ['report' => $report, 'filters' => $filters, 'from' => $from, 'to' => $to]);
    }

    /**
     * Lead times of the orders finished in a period (default: the last 90 days).
     *
     * @return ResponseInterface|string
     */
    public function leadTime(): ResponseInterface|string
    {
        $from = $this->date('from', date('Y-m-d', strtotime('-90 days')));
        $to   = $this->date('to', date('Y-m-d'));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'q'       => trim((string) $this->request->getGet('q')),
            'rep'     => (string) $this->request->getGet('rep'),
            'level'   => (string) $this->request->getGet('level'),
            'funding' => (string) $this->request->getGet('funding'),
        ];
        $report = $this->service()->leadTime($from, $to, $filters);

        if ($this->request->getGet('export') === 'csv') {
            $steps  = array_keys(\App\Services\LeadTime::STEPS);
            $days   = static fn (?float $d): string => $d === null ? '' : str_replace('.', ',', (string) $d);

            return $this->csv("lead-time-{$from}-{$to}.csv",
                array_merge([lang('Reports.csvProposal'), lang('Reports.csvCustomer'), 'NPSN', lang('Reports.csvSalesRep'), lang('Reports.csvFunding'), lang('Reports.csvFinished')],
                    array_map(static fn (string $s): string => lang('Reports.step_' . $s) . ' (' . lang('Reports.daysUnit') . ')', $steps)),
                array_map(static fn (array $r): array => array_merge(
                    [$r['cpl_id'], up($r['customer_name']), $r['npsn'], up($r['rep_name']), $r['funding_source_name'], substr((string) $r['finished'], 0, 10)],
                    array_map(static fn (string $s): string => $days($r['steps'][$s]['days']), $steps),
                ), $report['rows']));
        }

        return view('reports/lead_time', [
            'report'  => $report,
            'filters' => $filters,
            'from'    => $from,
            'to'      => $to,
            'reps'    => $this->scope()->seesAll() ? (new SalesRepModel())->activeOptions() : [],
            'levels'  => (new \App\Models\SchoolLevelModel())->options(),
            'funding' => (new FundingSourceModel())->options(),
        ]);
    }

    /**
     * @return ReportService
     */
    private function service(): ReportService
    {
        return new ReportService($this->scope());
    }

    /**
     * Valid Y-m-d date from the query string, else the default.
     *
     * @param string $key     Query parameter.
     * @param string $default Y-m-d.
     *
     * @return string
     */
    private function date(string $key, string $default): string
    {
        $value = (string) $this->request->getGet($key);
        $date  = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : $default;
    }

    /**
     * Amount for CSV: decimal comma, no thousands separator (Excel with Indonesian regional settings).
     *
     * @param string|null $value Decimal string.
     *
     * @return string
     */
    private static function num(?string $value): string
    {
        return str_replace('.', ',', bcadd((string) ($value ?? '0'), '0', 2));
    }

    /**
     * CSV download (semicolon separated, UTF-8 with BOM so Excel shows Indonesian names correctly).
     * Cells starting with = + - @ are prefixed with ' so Excel does not run them as formulas.
     *
     * @param string                       $filename
     * @param list<string>                 $header
     * @param list<list<string|int|null>>  $rows
     *
     * @return ResponseInterface
     */
    private function csv(string $filename, array $header, array $rows): ResponseInterface
    {
        $out = fopen('php://temp', 'r+b');
        fwrite($out, "\xEF\xBB\xBF");

        foreach ([$header, ...$rows] as $row) {
            fputcsv($out, array_map(static function ($cell): string {
                $cell = (string) ($cell ?? '');

                return preg_match('/^[=+\-@\t\r]/', $cell) === 1 && ! is_numeric(str_replace(',', '.', $cell)) ? "'" . $cell : $cell;
            }, $row), ';', '"', '');
        }

        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, max-age=0')
            ->setBody($body);
    }
}
