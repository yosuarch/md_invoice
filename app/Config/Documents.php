<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Default document number formats for App\Services\DocumentSequence. The formats in use are stored in
 * `document_numbering` (Settings → Document numbering); these are the initial values and the fallback.
 *
 * Pattern tokens: {Y} year (2026), {y} 2-digit year, {m} month (01-12), {d} day (01-31), {seq:N} counter
 * zero-padded to N digits. Period: 'monthly' (counter restarts each month), 'yearly' or 'never' (one running counter).
 */
class Documents extends BaseConfig
{
    /**
     * @var array<string, array{label: string, pattern: string, period: 'monthly'|'yearly'|'never', table: string, column: string}>
     *      table/column: where numbers of this type are stored (used to skip numbers that already exist).
     */
    public array $sequences = [
        'QUO'  => ['label' => 'Proposal (Surat Penawaran)', 'pattern' => 'QUO/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'client_proposal_list', 'column' => 'cpl_id'],
        'SPK'  => ['label' => 'Agreement (SPK)', 'pattern' => 'SPK/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'agreement', 'column' => 'agreement_number'],
        'INV'  => ['label' => 'Invoice', 'pattern' => 'INV/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'invoice', 'column' => 'invoice_number'],
        'KW'   => ['label' => 'Receipt (kwitansi)', 'pattern' => 'KW/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'payment', 'column' => 'receipt_number'],
        'CN'   => ['label' => 'Credit note', 'pattern' => 'CN/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'credit_note', 'column' => 'credit_note_number'],
        'DO'   => ['label' => 'Delivery note (surat jalan)', 'pattern' => 'DO/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'delivery_note', 'column' => 'delivery_number'],
        'BAST' => ['label' => 'Handover report (BAST)', 'pattern' => 'BAST/{Y}/{m}/{seq:4}', 'period' => 'monthly', 'table' => 'delivery_note', 'column' => 'handover_number'],
        'SKU'  => ['label' => 'Product SKU', 'pattern' => 'ws{y}{m}{seq:4}', 'period' => 'monthly', 'table' => 'product_list', 'column' => 'pl_sku'],
        // Customers use a UUIDv7 (App\Libraries\Uuid) instead of a sequence.
    ];

    /**
     * Default validity of a new proposal (quotation) in days; prefilled as "valid until".
     */
    public int $proposalValidDays = 30;

    /**
     * Group the store belongs to, printed in the footer of every PDF ("bagian dari grup ...").
     */
    public string $groupName = "de'makmur";

    /**
     * Group logo (small, in the PDF footer), relative to FCPATH.
     */
    public string $groupLogo = 'assets/img/logo-mark-doc.png';

    /**
     * Store logo used on PDFs when no logo was uploaded in Settings → Company profile, relative to FCPATH.
     */
    public string $defaultStoreLogo = 'assets/img/store-logo.png';

    /**
     * Documents stating an amount above this need a meterai (stamp duty Rp 10.000, UU 10/2020); PDFs show a meterai box.
     */
    public string $stampDutyThreshold = '5000000';

    /**
     * Largest attachment in KB. The effective limit is also capped by php.ini upload_max_filesize / post_max_size.
     */
    public int $attachmentMaxKb = 10240;

    /**
     * Allowed attachment extensions => MIME types accepted for them (detected from the file content with fileinfo).
     * Office files are sometimes detected as generic zip / CDF containers, so those are accepted for them.
     *
     * @var array<string, list<string>>
     */
    public array $attachmentTypes = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];
}
