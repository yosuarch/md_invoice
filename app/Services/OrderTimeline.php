<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Readable history of one order (audit trail for people): proposal created, status changes with notes, revisions sent,
 * SPK, invoice created / issued / voided, payments, refunds, credit notes, delivery events, attachments and earlier
 * combined reports. Sorted by time. Printed as the "Riwayat pesanan" appendix of the combined order report.
 *
 * Every event: at (Y-m-d H:i:s), code (event kind, labelled by the view), detail (numbers / notes, not translated),
 * user (display name or null).
 */
final class OrderTimeline
{
    /**
     * Events of the order of a proposal.
     *
     * @param int $proposalId Proposal id.
     *
     * @return list<array{at: string, code: string, detail: string, user: ?string}>
     */
    public function forProposal(int $proposalId): array
    {
        $db       = db_connect();
        $events   = [];
        $add      = static function (?string $at, string $code, string $detail, $userId) use (&$events): void {
            if ($at !== null && $at !== '') {
                $events[] = ['at' => strlen($at) === 10 ? $at . ' 00:00:00' : $at, 'code' => $code, 'detail' => trim($detail), 'user_id' => $userId !== null ? (int) $userId : null];
            }
        };
        $proposal = $db->table('client_proposal_list')->where('id', $proposalId)->get()->getRowArray();

        if ($proposal === null) {
            return [];
        }

        $add($proposal['created_at'], 'PROPOSAL_CREATED', (string) $proposal['cpl_id'], $proposal['created_by']);

        $history = $db->table('proposal_status_history h')->select('h.*, s.code')
            ->join('client_proposal_status s', 's.id = h.to_status_id')
            ->where('h.proposal_id', $proposalId)->orderBy('h.id')->get()->getResultArray();

        foreach ($history as $h) {
            $add($h['created_at'], 'STATUS_' . $h['code'], (string) ($h['note'] ?? ''), $h['changed_by']);
        }

        foreach ($db->table('proposal_revision')->where('proposal_id', $proposalId)->orderBy('id')->get()->getResultArray() as $r) {
            $add($r['created_at'], 'REVISION', '#' . $r['revision_no'] . ' · ' . format_amount($r['grand_total']) . ($r['note'] ? ' · ' . $r['note'] : ''), $r['created_by']);
        }

        $agreement = $db->table('agreement')->where('proposal_id', $proposalId)->where('deleted_at', null)->get()->getRowArray();

        if ($agreement !== null) {
            $add($agreement['created_at'], 'AGREEMENT', (string) $agreement['agreement_number'], $agreement['created_by']);
        }

        $entities = ['proposal' => [$proposalId], 'invoice' => [], 'payment' => [], 'delivery_note' => []];
        $invoices = $db->table('invoice')->where('proposal_id', $proposalId)->where('deleted_at', null)->orderBy('id')->get()->getResultArray();

        foreach ($invoices as $inv) {
            $iid    = (int) $inv['id'];
            $number = str_starts_with((string) $inv['invoice_number'], 'DRAFT-') ? 'Draft #' . $iid : (string) $inv['invoice_number'];
            $entities['invoice'][] = $iid;

            $add($inv['created_at'], 'INVOICE_CREATED', $number, $inv['created_by']);
            $add($inv['issued_at'], 'INVOICE_ISSUED', $number . ' · ' . format_amount($inv['grand_total']), $this->issuedBy($iid));
            $add($inv['voided_at'], 'INVOICE_VOIDED', $number . ' · ' . $inv['void_reason'], $inv['voided_by']);

            foreach ($db->table('payment')->where('invoice_id', $iid)->where('deleted_at', null)->orderBy('id')->get()->getResultArray() as $pay) {
                $entities['payment'][] = (int) $pay['id'];
                $add($pay['created_at'], 'PAYMENT', trim(($pay['receipt_number'] ?? '') . ' · ' . format_date_id($pay['payment_date']) . ' · ' . format_amount($pay['amount']), ' ·'), $pay['recorded_by']);
                $add($pay['reversed_at'], 'PAYMENT_REVERSED', ($pay['receipt_number'] ?? '') . ' · ' . $pay['reversal_reason'], $pay['reversed_by']);
            }

            foreach ($db->table('refund')->where('invoice_id', $iid)->where('deleted_at', null)->orderBy('id')->get()->getResultArray() as $ref) {
                $add($ref['created_at'], 'REFUND', format_amount($ref['amount']) . ' · ' . $ref['reason'], $ref['recorded_by']);
                $add($ref['reversed_at'], 'REFUND_REVERSED', format_amount($ref['amount']) . ' · ' . $ref['reversal_reason'], $ref['reversed_by']);
            }

            foreach ($db->table('credit_note')->where('invoice_id', $iid)->where('deleted_at', null)->orderBy('id')->get()->getResultArray() as $cn) {
                $add($cn['created_at'], 'CREDIT_NOTE', $cn['credit_note_number'] . ' · ' . format_amount($cn['grand_total']) . ' · ' . $cn['reason'], $cn['created_by']);
            }

            $notes = $db->table('delivery_note')->where('invoice_id', $iid)->where('deleted_at', null)->orderBy('id')->get()->getResultArray();

            foreach ($notes as $dn) {
                $entities['delivery_note'][] = (int) $dn['id'];
                $events_ = $db->table('delivery_note_event')->where('delivery_note_id', $dn['id'])->orderBy('event_at')->orderBy('id')->get()->getResultArray();

                foreach ($events_ as $ev) {
                    $detail = $dn['delivery_number'];

                    if ($ev['event_type'] === 'DELIVERED' && ! empty($dn['received_by_name'])) {
                        $detail .= ' · ' . up($dn['received_by_name']); // receiver (labelled in the view)
                    }

                    if ($ev['event_type'] === 'SHIPPED' && ! empty($dn['courier_name'])) {
                        $detail .= ' · ' . up($dn['courier_name']) . (! empty($dn['tracking_number']) ? ' ' . $dn['tracking_number'] : '');
                    }

                    $detail .= ($ev['location'] ? ' · ' . up($ev['location']) : '') . ($ev['note'] ? ' · ' . $ev['note'] : '');
                    $add($ev['event_at'], 'DELIVERY_' . $ev['event_type'], $detail, $ev['created_by']);
                }
            }
        }

        foreach ((new \App\Models\AttachmentModel())->forEntities($entities) as $a) {
            $add($a['created_at'], 'ATTACHMENT', (string) $a['file_name'], $a['uploaded_by']);
        }

        foreach ($db->table('audit_log')->where(['table_name' => 'order_bundle', 'record_id' => $proposalId])->orderBy('id')->get()->getResultArray() as $log) {
            $add($log['created_at'], 'BUNDLE', '', $log['user_id']);
        }

        usort($events, static fn (array $a, array $b): int => [$a['at'], $a['code']] <=> [$b['at'], $b['code']]);

        return $this->withUserNames($events);
    }

    /**
     * Who issued the invoice: the audit log row that set issued_at.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return int|null User id.
     */
    private function issuedBy(int $invoiceId): ?int
    {
        $row = db_connect()->table('audit_log')
            ->select('user_id')
            ->where(['table_name' => 'invoice', 'record_id' => $invoiceId, 'action' => 'UPDATE'])
            ->where("JSON_CONTAINS_PATH(new_values, 'one', '$.issued_at')", null, false)
            ->orderBy('id')->limit(1)->get()->getRowArray();

        return isset($row['user_id']) ? (int) $row['user_id'] : null;
    }

    /**
     * Replace user ids with display names (full name in upper case, else the username).
     *
     * @param list<array{at: string, code: string, detail: string, user_id: ?int}> $events
     *
     * @return list<array{at: string, code: string, detail: string, user: ?string}>
     */
    private function withUserNames(array $events): array
    {
        $ids   = array_values(array_unique(array_filter(array_column($events, 'user_id'))));
        $names = [];

        if ($ids !== []) {
            foreach (db_connect()->table('users')->select('id, username, full_name')->whereIn('id', $ids)->get()->getResultArray() as $u) {
                $names[(int) $u['id']] = ! empty($u['full_name']) ? up($u['full_name']) : (string) $u['username'];
            }
        }

        return array_map(static fn (array $e): array => [
            'at'     => $e['at'],
            'code'   => $e['code'],
            'detail' => $e['detail'],
            'user'   => $e['user_id'] !== null ? ($names[$e['user_id']] ?? null) : null,
        ], $events);
    }
}
