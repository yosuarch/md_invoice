<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgreementModel;
use App\Models\AttachmentModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;

/**
 * Every file of one order in one place, for everyone related to the proposal: the printable PDFs of all its documents
 * (penawaran, SPK, invoices, kwitansi, surat jalan, BAST) and the attachments of the proposal, invoices, payments and
 * delivery notes. Used by OrderFlow and rendered by components/order_files.
 */
final class OrderFiles
{
    /**
     * @param array<string, mixed>|null  $proposal Proposal with details, or null (invoice without proposal).
     * @param list<array<string, mixed>> $invoices Invoices with details of the order (incl. void), oldest first.
     *
     * @return array{
     *     prints: list<array{group: string, label: string, url: string, icon: string, muted: bool}>,
     *     attachments: list<array<string, mixed>>,
     *     targets: list<array{type: string, id: int, label: string}>
     * }
     */
    public function collect(?array $proposal, array $invoices): array
    {
        $prints   = [];
        $entities = ['proposal' => [], 'invoice' => [], 'payment' => [], 'delivery_note' => []];
        $labels   = [];

        if ($proposal !== null) {
            $pid = (int) $proposal['id'];
            $entities['proposal'][] = $pid;
            $labels['proposal'][$pid] = lang('Orders.proposalNo', [$proposal['cpl_id']]);
            $prints[] = $this->print(lang('Orders.proposalNo', [$proposal['cpl_id']]), 'Surat Penawaran Harga', "proposals/{$pid}/pdf", 'file-earmark-text', false);

            $agreement = (new AgreementModel())->forProposal($pid);

            if ($agreement !== null) {
                $prints[] = $this->print(lang('Orders.proposalNo', [$proposal['cpl_id']]), 'Surat Perintah Kerja ' . $agreement['agreement_number'], "proposals/{$pid}/agreement/pdf", 'file-earmark-check', false);
            }
        }

        foreach ($invoices as $inv) {
            $iid    = (int) $inv['id'];
            $number = InvoiceModel::displayNumber($inv);
            $void   = $inv['status_code'] === InvoiceWorkflow::VOID;
            $group  = lang('Orders.invoiceNo', [$number]) . ($void ? ' (' . lang('Orders.void') . ')' : '');

            $entities['invoice'][] = $iid;
            $labels['invoice'][$iid] = lang('Orders.invoiceNo', [$number]);
            $prints[] = $this->print($group, $inv['status_code'] === InvoiceWorkflow::DRAFT ? lang('Orders.invoiceDraftPreview') : 'Invoice', "invoices/{$iid}/pdf", 'receipt', $void);

            foreach ((new PaymentModel())->where('invoice_id', $iid)->orderBy('id')->findAll() as $pay) {
                $payId = (int) $pay['id'];
                $entities['payment'][] = $payId;
                $labels['payment'][$payId] = lang('Orders.payment') . ' ' . ($pay['receipt_number'] ?: format_date($pay['payment_date'])) . ' · ' . format_idr($pay['amount'], 0);

                if (! empty($pay['receipt_number'])) {
                    $reversed = $pay['reversed_at'] !== null;
                    $prints[] = $this->print($group, 'Kwitansi ' . $pay['receipt_number'] . ($reversed ? ' (' . lang('Orders.reversed') . ')' : ''), "payments/{$payId}/receipt", 'cash-coin', $reversed);
                }
            }

            foreach ((new DeliveryNoteModel())->where('invoice_id', $iid)->orderBy('id')->findAll() as $dn) {
                $did       = (int) $dn['id'];
                $cancelled = $dn['cancelled_at'] !== null;
                $entities['delivery_note'][] = $did;
                $labels['delivery_note'][$did] = lang('Orders.deliveryNo', [$dn['delivery_number']]);
                $prints[] = $this->print($group, 'Surat Jalan ' . $dn['delivery_number'] . ($cancelled ? ' (' . lang('Orders.cancelled') . ')' : ''), "deliveries/{$did}/pdf", 'truck', $cancelled);
                $prints[] = $this->print($group, 'Berita Acara Serah Terima ' . ($dn['handover_number'] ?: PrintDocuments::handoverNumber($dn['delivery_number'])), "deliveries/{$did}/handover", 'clipboard-check', $cancelled);
            }
        }

        $attachments = (new AttachmentModel())->forEntities($entities);

        foreach ($attachments as &$a) {
            $a['entity_label'] = $labels[$a['entity_type']][(int) $a['entity_id']] ?? code_label('attachmentType', $a['entity_type'], AttachmentService::TYPES[$a['entity_type']]['label']);
            $a['can_delete']   = AttachmentService::canDelete($a);
        }
        unset($a);

        $targets = [];

        foreach ($labels as $type => $records) {
            if (! AttachmentService::canUpload($type)) {
                continue;
            }

            foreach ($records as $id => $label) {
                $targets[] = ['type' => $type, 'id' => (int) $id, 'label' => $label];
            }
        }

        return ['prints' => $prints, 'attachments' => $attachments, 'targets' => $targets];
    }

    /**
     * @return array{group: string, label: string, url: string, icon: string, muted: bool}
     */
    private function print(string $group, string $label, string $uri, string $icon, bool $muted): array
    {
        return ['group' => $group, 'label' => $label, 'url' => site_url($uri), 'icon' => $icon, 'muted' => $muted];
    }
}
