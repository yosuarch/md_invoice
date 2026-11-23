<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Pdf;
use App\Models\AgreementModel;
use App\Models\CompanyProfileModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ProposalModel;
use App\Services\DocumentVerification;
use App\Services\InvoiceWorkflow;
use App\Services\PrintDocuments;
use App\Services\ProposalWorkflow;
use Config\Documents;

/**
 * Public "scan to verify" page (no login): shows the key data of a printed document from the database,
 * so the school can check that a paper or forwarded PDF is genuine and still valid. Indonesian, like the documents.
 * Only reachable with the random token printed in the QR code, or the short code printed under it (GET verify, form);
 * shows no more than the document itself. Rate limited per IP (VerifyThrottle filter).
 */
class Verify extends BaseController
{
    /**
     * Verification page.
     *
     * @param string $token Token from the QR code.
     *
     * @return string
     */
    public function show(string $token): string
    {
        return $this->page((new DocumentVerification())->resolve($token), null);
    }

    /**
     * Code form (GET verify), and the result for a typed code (GET verify?code=K7Q2-M9XD).
     *
     * @return string
     */
    public function index(): string
    {
        $code = trim((string) $this->request->getGet('code'));

        if ($code === '') {
            return $this->page(null, '', true);
        }

        return $this->page((new DocumentVerification())->resolveCode($code), mb_substr($code, 0, 20));
    }

    /**
     * Render the verification page.
     *
     * @param array{doc_type: string, doc_id: int}|null $doc      Resolved document, or null.
     * @param string|null                             $code     Typed code (to show it again), or null for QR links.
     * @param bool                                    $formOnly Show only the code form (no result yet).
     *
     * @return string
     */
    private function page(?array $doc, ?string $code, bool $formOnly = false): string
    {
        $summary = $doc !== null ? $this->summary($doc['doc_type'], $doc['doc_id']) : null;
        $profile = (new CompanyProfileModel())->getProfile() ?? [];
        $logo    = ! empty($profile['logo_path']) ? Pdf::imageDataUri(WRITEPATH . 'uploads/' . $profile['logo_path']) : null;

        if ($summary === null && ! $formOnly) {
            $this->response->setStatusCode(404);
        }

        return view('verify/show', [
            'summary'  => $summary,
            'formOnly' => $formOnly,
            'code'     => $code,
            'company'  => $profile['company_name'] ?? 'Warung Sahabat',
            'logo'     => $logo ?? Pdf::imageDataUri(FCPATH . config(Documents::class)->defaultStoreLogo),
        ]);
    }

    /**
     * Key data of a document, or null when it no longer exists.
     *
     * @param string $type  Document type.
     * @param int    $id    Document id.
     *
     * @return array{type: string, rows: array<string, string>, valid: bool, status: string}|null
     */
    private function summary(string $type, int $id): ?array
    {
        $label = DocumentVerification::TYPES[$type] ?? $type;

        switch ($type) {
            case 'QUO':
                $p = (new ProposalModel())->withDetails()->find($id);

                return $p === null ? null : $this->result($label, [
                    'Nomor' => $p['cpl_id'], 'Tanggal' => format_date_id($p['created_at']), 'Kepada' => up($p['customer_name']),
                    'Perihal' => $p['cpl_proposal_title'], 'Total' => format_idr($p['grand_total']),
                ], ! in_array($p['status_code'], [ProposalWorkflow::CANCEL_BY_CLIENT, ProposalWorkflow::CANCEL_BY_STORE], true), ProposalWorkflow::label($p['status_code']));

            case 'SPK':
                $a = (new AgreementModel())->find($id);
                $p = $a !== null ? (new ProposalModel())->withDetails()->find((int) $a['proposal_id']) : null;

                return $p === null ? null : $this->result($label, [
                    'Nomor' => $a['agreement_number'], 'Tanggal' => format_date_id($a['agreement_date']), 'Pemesan' => up($p['customer_name']),
                    'Paket pekerjaan' => $p['cpl_proposal_title'], 'Nilai pekerjaan' => format_idr($p['grand_total']),
                ], ! in_array($p['status_code'], [ProposalWorkflow::CANCEL_BY_CLIENT, ProposalWorkflow::CANCEL_BY_STORE], true), 'Berlaku');

            case 'INV':
                $i = (new InvoiceModel())->withDetails()->find($id);

                return $i === null ? null : $this->result($label, [
                    'Nomor' => InvoiceModel::displayNumber($i), 'Tanggal' => format_date_id($i['issue_date']), 'Kepada' => up($i['customer_name']),
                    'Total tagihan' => format_idr($i['grand_total']), 'Jatuh tempo' => format_date_id($i['due_date']),
                ], ! in_array($i['status_code'], [InvoiceWorkflow::VOID, InvoiceWorkflow::CANCELLED, InvoiceWorkflow::DRAFT], true), $this->invoiceStatus($i['status_code']));

            case 'KW':
                $pay = (new PaymentModel())->withDetails()->find($id);

                return $pay === null ? null : $this->result($label, [
                    'Nomor' => (string) $pay['receipt_number'], 'Tanggal' => format_date_id($pay['payment_date']), 'Dari' => up($pay['customer_name']),
                    'Jumlah' => format_idr(bcadd((string) $pay['amount'], (string) $pay['withheld'], 2)), 'Untuk invoice' => (string) $pay['invoice_number'],
                ], $pay['reversed_at'] === null, $pay['reversed_at'] === null ? 'Sah' : 'Dibatalkan');

            case 'DO':
            case 'BAST':
                $n = (new DeliveryNoteModel())->withDetails()->find($id);

                if ($n === null) {
                    return null;
                }

                $status = DeliveryNoteModel::status($n);

                return $this->result($label, [
                    'Nomor' => $type === 'BAST' ? ($n['handover_number'] ?: PrintDocuments::handoverNumber($n['delivery_number'])) : $n['delivery_number'],
                    'Tanggal kirim' => format_date_id($n['ship_date']), 'Kepada' => up($n['customer_name']), 'Invoice' => (string) $n['invoice_number'],
                    'Diterima' => $n['delivered_at'] ? format_date_id($n['delivered_at']) . ' oleh ' . $n['received_by_name'] : '-',
                ], $status !== DeliveryNoteModel::CANCELLED, match ($status) {
                    DeliveryNoteModel::DELIVERED => 'Sudah diterima',
                    DeliveryNoteModel::CANCELLED => 'Dibatalkan',
                    DeliveryNoteModel::SHIPPED   => 'Dalam pengiriman',
                    default                      => 'Disiapkan',
                });
        }

        return null;
    }

    /**
     * @param string                $type   Document label.
     * @param array<string, string> $rows   label => value.
     * @param bool                  $valid  Document is valid (not cancelled / void).
     * @param string                $status Status text.
     *
     * @return array{type: string, rows: array<string, string>, valid: bool, status: string}
     */
    private function result(string $type, array $rows, bool $valid, string $status): array
    {
        return ['type' => $type, 'rows' => $rows, 'valid' => $valid, 'status' => $status];
    }

    /**
     * Indonesian invoice status.
     *
     * @param string $code Status code.
     *
     * @return string
     */
    private function invoiceStatus(string $code): string
    {
        return match ($code) {
            InvoiceWorkflow::ISSUED         => 'Belum dibayar',
            InvoiceWorkflow::PARTIALLY_PAID => 'Dibayar sebagian',
            InvoiceWorkflow::PAID           => 'Lunas',
            InvoiceWorkflow::OVERDUE        => 'Lewat jatuh tempo',
            InvoiceWorkflow::VOID           => 'Dibatalkan',
            InvoiceWorkflow::CANCELLED      => 'Dibatalkan (nota kredit)',
            default                         => 'Draft (belum diterbitkan)',
        };
    }
}
