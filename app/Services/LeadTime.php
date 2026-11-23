<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lead times of an order: how long each step took, from the proposal to the delivery.
 * Used by the combined order report (cover) and Reports → Lead time (averages over finished orders).
 *
 * durations() is pure (timestamps in, days out); forProposal() / timestamps() load the timestamps of orders.
 */
final class LeadTime
{
    /**
     * Steps: key => [from timestamp, to timestamp]. Delivery steps are null for orders without goods.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const STEPS = [
        'preparation' => ['created', 'sent'],
        'approval'    => ['sent', 'approved'],
        'invoicing'   => ['approved', 'issued'],
        'payment'     => ['issued', 'paid'],
        'packing'     => ['issued', 'noted'],
        'shipping'    => ['noted', 'shipped'],
        'transit'     => ['shipped', 'delivered'],
        'total'       => ['created', 'finished'],
    ];

    /** Steps about the delivery (shown as "no goods" for services-only orders). */
    public const DELIVERY_STEPS = ['packing', 'shipping', 'transit'];

    /**
     * Duration of every step in days (one decimal), null when a step has not happened (or the order has no goods).
     *
     * @param array<string, string|null> $ts Timestamps (Y-m-d H:i:s or Y-m-d): created, sent, approved, issued, paid,
     *                                       noted (first delivery note), shipped, delivered; finished is derived.
     *
     * @return array<string, array{from: ?string, to: ?string, days: ?float, seconds: ?int}>
     */
    public static function durations(array $ts): array
    {
        $ts['finished'] ??= self::finished($ts);
        $out = [];

        foreach (self::STEPS as $key => [$from, $to]) {
            $start = $ts[$from] ?? null;
            $end   = $ts[$to] ?? null;
            $days  = null;
            $secs  = null;

            if ($start !== null && $end !== null) {
                $secs = max(0, strtotime($end) - strtotime($start));
                $days = round($secs / 86400, 1);
            }

            $out[$key] = ['from' => $start, 'to' => $end, 'days' => $days, 'seconds' => $secs];
        }

        return $out;
    }

    /**
     * When the order was finished: paid and delivered (or only paid when there is nothing to deliver).
     *
     * @param array<string, string|null> $ts Timestamps.
     *
     * @return string|null
     */
    public static function finished(array $ts): ?string
    {
        $paid = $ts['paid'] ?? null;

        if ($paid === null) {
            return null;
        }

        if (! empty($ts['has_goods'])) {
            $delivered = $ts['delivered'] ?? null;

            return $delivered === null ? null : max($paid, $delivered);
        }

        return $paid;
    }

    /**
     * Parts of a duration for display: [value, unit] with unit day / hour / minute (labelled by the caller).
     *
     * @param int|null $seconds Duration in seconds.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parts(?int $seconds): ?array
    {
        return match (true) {
            $seconds === null    => null,
            $seconds >= 86400    => [number_format($seconds / 86400, 1, ',', '.'), 'day'],
            $seconds >= 3600     => [number_format($seconds / 3600, 1, ',', '.'), 'hour'],
            default              => [(string) max(1, (int) round($seconds / 60)), 'minute'],
        };
    }

    /**
     * Statistics of a list of values (days or seconds): count, average, median, min, max.
     *
     * @param list<float|null> $values Values (nulls are ignored).
     *
     * @return array{count: int, avg: ?float, median: ?float, min: ?float, max: ?float}
     */
    public static function stats(array $values): array
    {
        $values = array_values(array_filter($values, static fn ($v): bool => $v !== null));
        sort($values);
        $n = count($values);

        if ($n === 0) {
            return ['count' => 0, 'avg' => null, 'median' => null, 'min' => null, 'max' => null];
        }

        $mid    = intdiv($n, 2);
        $median = $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        return [
            'count'  => $n,
            'avg'    => round(array_sum($values) / $n, 1),
            'median' => round((float) $median, 1),
            'min'    => (float) $values[0],
            'max'    => (float) $values[$n - 1],
        ];
    }

    /**
     * Durations of one order.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array<string, array{from: ?string, to: ?string, days: ?float}>
     */
    public function forProposal(int $proposalId): array
    {
        return self::durations($this->timestamps([$proposalId])[$proposalId] ?? []);
    }

    /**
     * Timestamps of several orders (one query per source).
     *
     * @param list<int> $proposalIds Proposal ids.
     *
     * @return array<int, array<string, string|bool|null>> proposal id => timestamps (+ has_goods, invoice_id)
     */
    public function timestamps(array $proposalIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $proposalIds)));

        if ($ids === []) {
            return [];
        }

        $db  = db_connect();
        $out = [];

        foreach ($db->table('client_proposal_list')->select('id, created_at')->whereIn('id', $ids)->get()->getResultArray() as $p) {
            $out[(int) $p['id']] = ['created' => $p['created_at'], 'sent' => null, 'approved' => null, 'issued' => null, 'paid' => null,
                'noted' => null, 'shipped' => null, 'delivered' => null, 'has_goods' => false, 'invoice_id' => null];
        }

        // First send and last approval from the status history.
        $history = $db->table('proposal_status_history h')
            ->select('h.proposal_id, s.code, MIN(h.created_at) AS first_at, MAX(h.created_at) AS last_at')
            ->join('client_proposal_status s', 's.id = h.to_status_id')
            ->whereIn('h.proposal_id', $ids)
            ->whereIn('s.code', [ProposalWorkflow::PROPOSE, ProposalWorkflow::STORE_APPROVE])
            ->groupBy(['h.proposal_id', 's.code'])
            ->get()->getResultArray();

        foreach ($history as $h) {
            $key = $h['code'] === ProposalWorkflow::PROPOSE ? 'sent' : 'approved';
            $out[(int) $h['proposal_id']][$key] = $key === 'sent' ? $h['first_at'] : $h['last_at'];
        }

        // Active invoice = latest non-void invoice of the proposal.
        $invoices = $db->table('invoice i')
            ->select('i.id, i.proposal_id, i.issued_at, s.code')
            ->join('invoice_status s', 's.id = i.status_id')
            ->whereIn('i.proposal_id', $ids)
            ->where('i.deleted_at', null)
            ->where('s.code !=', InvoiceWorkflow::VOID)
            ->orderBy('i.id')
            ->get()->getResultArray();

        $byInvoice = [];

        foreach ($invoices as $inv) {
            $pid = (int) $inv['proposal_id'];
            $out[$pid]['invoice_id'] = (int) $inv['id'];
            $out[$pid]['issued']     = $inv['issued_at'];
            $out[$pid]['paid']       = null;
            $out[$pid]['status']     = $inv['code'];
            $byInvoice[(int) $inv['id']] = $pid;
        }

        $byInvoice = array_filter($byInvoice, static fn (int $pid, int $iid): bool => $out[$pid]['invoice_id'] === $iid, ARRAY_FILTER_USE_BOTH);

        if ($byInvoice !== []) {
            $invoiceIds = array_keys($byInvoice);

            // Paid in full: the last non-reversed payment. Payments only have a date; when it was recorded on that date,
            // the recording time is used (otherwise midnight of the payment date).
            $payments = $db->table('payment')->select("invoice_id, MAX(IF(DATE(created_at) = payment_date, created_at, CONCAT(payment_date, ' 00:00:00'))) AS paid_on", false)
                ->whereIn('invoice_id', $invoiceIds)->where('reversed_at', null)->where('deleted_at', null)
                ->groupBy('invoice_id')->get()->getResultArray();

            foreach ($payments as $pay) {
                $pid = $byInvoice[(int) $pay['invoice_id']];

                if (($out[$pid]['status'] ?? null) === InvoiceWorkflow::PAID) {
                    $out[$pid]['paid'] = $pay['paid_on'];
                }
            }

            // Goods lines decide whether the delivery steps apply; delivered = every goods quantity on delivered notes.
            $goods = $db->table('invoice_item')->select('invoice_id, SUM(quantity) AS qty')->whereIn('invoice_id', $invoiceIds)
                ->where('item_type', 'GOODS')->where('deleted_at', null)->groupBy('invoice_id')->get()->getResultArray();
            $ordered = [];

            foreach ($goods as $g) {
                $out[$byInvoice[(int) $g['invoice_id']]]['has_goods'] = true;
                $ordered[(int) $g['invoice_id']] = (string) $g['qty'];
            }

            $received = [];

            foreach ($db->table('delivery_note_item i')->select('n.invoice_id, SUM(i.quantity) AS qty')
                ->join('delivery_note n', 'n.id = i.delivery_note_id')
                ->whereIn('n.invoice_id', $invoiceIds)->where('n.cancelled_at', null)->where('n.deleted_at', null)->where('n.delivered_at IS NOT NULL', null, false)
                ->groupBy('n.invoice_id')->get()->getResultArray() as $d) {
                $received[(int) $d['invoice_id']] = (string) $d['qty'];
            }

            // Delivery notes that were not cancelled: first created, first shipped, last delivered.
            $notes = $db->table('delivery_note')
                ->select('invoice_id, MIN(created_at) AS noted, MIN(shipped_at) AS shipped, MAX(delivered_at) AS delivered')
                ->whereIn('invoice_id', $invoiceIds)->where('cancelled_at', null)->where('deleted_at', null)
                ->groupBy('invoice_id')->get()->getResultArray();

            foreach ($notes as $n) {
                $pid = $byInvoice[(int) $n['invoice_id']];
                $out[$pid]['noted']     = $n['noted'];
                $out[$pid]['shipped']   = $n['shipped'] ?? $n['delivered']; // picked up at the store: delivered without shipping
                $all = bccomp($received[(int) $n['invoice_id']] ?? '0', $ordered[(int) $n['invoice_id']] ?? '0', 3) >= 0;
                $out[$pid]['delivered'] = $all ? $n['delivered'] : null;
            }
        }

        foreach ($out as &$ts) {
            unset($ts['status']);
        }
        unset($ts);

        return $out;
    }
}
