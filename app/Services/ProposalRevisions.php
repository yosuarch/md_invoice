<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProposalRevisionModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Negotiation history of a proposal.
 *
 * Every time a proposal is sent (status → PROPOSE) its items and totals are stored as a numbered revision; when it is
 * approved with content that differs from the last revision sent, the approved version is stored too. The proposal
 * page compares each revision with the one before (price, quantity, discount changes; added/removed lines) and shows
 * unsent changes while the proposal is under review.
 */
final class ProposalRevisions
{
    /** Line fields that are compared between revisions => label. */
    private const COMPARED = [
        'quantity'   => 'Qty',
        'unit_price' => 'Unit price',
        'discount'   => 'Discount',
        'tax_rate'   => 'Tax',
    ];

    /**
     * Current content of a proposal (items + totals) in revision format.
     *
     * @param int                 $proposalId Proposal id.
     * @param BaseConnection|null $db         Connection (inside a transaction / migration).
     *
     * @return array{items: list<array<string, mixed>>, subtotal: string, discount_total: string, tax_total: string, grand_total: string, valid_until: ?string}
     */
    public static function snapshot(int $proposalId, ?BaseConnection $db = null): array
    {
        $db ??= db_connect();
        $p  = $db->table('client_proposal_list')->where('id', $proposalId)->get()->getRowArray();

        $items = array_map(static fn (array $r): array => [
            'line_id'    => (int) $r['id'],
            'product_id' => (int) $r['product_id'],
            'variant_id' => $r['variant_id'] !== null ? (int) $r['variant_id'] : null,
            'title'      => $r['item_title'],
            'description' => (string) ($r['item_description'] ?? ''),
            'unit'       => $r['unit_code'],
            'type'       => $r['item_type'],
            'quantity'   => (string) $r['cpi_quantity'],
            'unit_price' => (string) $r['unit_price'],
            'discount'   => (string) $r['discount_amount'],
            'tax_rate'   => (string) $r['tax_rate'],
            'line_total' => (string) $r['line_total'],
        ], $db->table('client_proposal_item')->where('proposal_id', $proposalId)->where('deleted_at', null)->orderBy('id')->get()->getResultArray());

        return [
            'items'          => $items,
            'subtotal'       => (string) $p['subtotal'],
            'discount_total' => (string) $p['discount_total'],
            'tax_total'      => (string) $p['tax_total'],
            'grand_total'    => (string) $p['grand_total'],
            'valid_until'    => $p['valid_until'],
        ];
    }

    /**
     * Store the current content as the next revision. Call inside the status-change transaction (row locked).
     *
     * @param int         $proposalId Proposal id.
     * @param string      $statusCode Status the proposal moves to (PROPOSE or STORE_APPROVE).
     * @param string|null $note       What changed / remark.
     * @param int         $userId     Current user.
     * @param bool        $onlyIfChanged Skip when the content equals the latest revision (used on approval).
     *
     * @return int|null New revision number, or null when skipped.
     */
    public function record(int $proposalId, string $statusCode, ?string $note, int $userId, bool $onlyIfChanged = false): ?int
    {
        $db     = db_connect();
        $snap   = self::snapshot($proposalId, $db);
        $model  = new ProposalRevisionModel();
        $latest = $model->where('proposal_id', $proposalId)->orderBy('revision_no', 'DESC')->first();

        if ($onlyIfChanged && $latest !== null && self::sameContent(json_decode((string) $latest['items'], true) ?: [], $snap['items'])) {
            return null;
        }

        $no = (int) ($latest['revision_no'] ?? 0) + 1;

        $model->insert([
            'proposal_id'    => $proposalId,
            'revision_no'    => $no,
            'status_code'    => $statusCode,
            'items'          => json_encode($snap['items'], JSON_UNESCAPED_UNICODE),
            'subtotal'       => $snap['subtotal'],
            'discount_total' => $snap['discount_total'],
            'tax_total'      => $snap['tax_total'],
            'grand_total'    => $snap['grand_total'],
            'valid_until'    => $snap['valid_until'],
            'note'           => $note !== null && trim($note) !== '' ? trim($note) : null,
            'created_by'     => $userId,
        ]);
        $db->table('client_proposal_list')->where('id', $proposalId)->update(['revision_no' => $no]);

        return $no;
    }

    /**
     * Negotiation history for the proposal page: revisions newest first, each compared with the one before, plus
     * the unsent changes (current content vs. latest revision) when there are any.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array{revisions: list<array<string, mixed>>, pending: ?array{diff: list<array<string, mixed>>, delta: string, grand_total: string}}
     */
    public function history(int $proposalId): array
    {
        $revisions = (new ProposalRevisionModel())->forProposal($proposalId);
        $previous  = null;

        foreach ($revisions as &$rev) {
            $rev['diff']  = $previous !== null ? self::diff($previous['items'], $rev['items']) : [];
            $rev['delta'] = $previous !== null ? bcsub((string) $rev['grand_total'], (string) $previous['grand_total'], 2) : null;
            $previous     = $rev;
        }
        unset($rev);

        $pending = null;

        if ($previous !== null) {
            $current = self::snapshot($proposalId);

            if (! self::sameContent($previous['items'], $current['items'])) {
                $pending = [
                    'diff'        => self::diff($previous['items'], $current['items']),
                    'delta'       => bcsub($current['grand_total'], (string) $previous['grand_total'], 2),
                    'grand_total' => $current['grand_total'],
                ];
            }
        }

        return ['revisions' => array_reverse($revisions), 'pending' => $pending];
    }

    /**
     * Line-by-line differences between two item lists (matched by line id).
     *
     * @param list<array<string, mixed>> $old Items of the earlier version.
     * @param list<array<string, mixed>> $new Items of the later version.
     *
     * @return list<array{kind: string, title: string, unit: string, changes: list<array{field: string, label: string, old: string, new: string}>, old: ?array<string, mixed>, new: ?array<string, mixed>}>
     *         kind: added | removed | changed (unchanged lines are left out)
     */
    public static function diff(array $old, array $new): array
    {
        $oldById = array_column($old, null, 'line_id');
        $result  = [];

        foreach ($new as $line) {
            $before = $oldById[$line['line_id']] ?? null;
            unset($oldById[$line['line_id']]);

            if ($before === null) {
                $result[] = ['kind' => 'added', 'title' => $line['title'], 'unit' => $line['unit'], 'changes' => [], 'old' => null, 'new' => $line];

                continue;
            }

            $changes = [];

            foreach (self::COMPARED as $field => $label) {
                $scale = $field === 'quantity' ? 3 : 2;

                if (bccomp((string) $before[$field], (string) $line[$field], $scale) !== 0) {
                    $changes[] = ['field' => $field, 'label' => $label, 'old' => (string) $before[$field], 'new' => (string) $line[$field]];
                }
            }

            if (mb_strtolower($before['title']) !== mb_strtolower($line['title'])) {
                $changes[] = ['field' => 'title', 'label' => 'Title', 'old' => $before['title'], 'new' => $line['title']];
            }

            // Revisions recorded before line descriptions existed have none.
            if (($before['description'] ?? '') !== ($line['description'] ?? '')) {
                $changes[] = ['field' => 'description', 'label' => 'Description', 'old' => (string) ($before['description'] ?? ''), 'new' => (string) ($line['description'] ?? '')];
            }

            if ($changes !== []) {
                $result[] = ['kind' => 'changed', 'title' => $line['title'], 'unit' => $line['unit'], 'changes' => $changes, 'old' => $before, 'new' => $line];
            }
        }

        foreach ($oldById as $line) {
            $result[] = ['kind' => 'removed', 'title' => $line['title'], 'unit' => $line['unit'], 'changes' => [], 'old' => $line, 'new' => null];
        }

        return $result;
    }

    /**
     * Whether two item lists offer the same thing (same lines, titles, quantities, prices, discounts).
     *
     * @param list<array<string, mixed>> $a
     * @param list<array<string, mixed>> $b
     *
     * @return bool
     */
    public static function sameContent(array $a, array $b): bool
    {
        return count($a) === count($b) && self::diff($a, $b) === [];
    }
}
