<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Pdf;
use App\Models\AgreementModel;
use App\Models\BankAccountModel;
use App\Models\CompanyProfileModel;
use App\Models\CustomerAddressModel;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\DeliveryNoteItemModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentModel;
use App\Models\PaymentWithholdingModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalModel;
use App\Models\SchoolLevelModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Documents;

/**
 * Data for the printed documents (views in app/Views/pdf/). Everything comes from the stored document rows
 * (copied values), never from current product data. Each method returns
 * ['view' => ..., 'data' => ..., 'filename' => ..., 'footer' => ...] for App\Libraries\Pdf.
 *
 * Seller = the company profile (the store, Warung Sahabat); the group (de'makmur) is printed in the footer.
 */
final class PrintDocuments
{
    private DocumentVerification $verification;

    public function __construct()
    {
        $this->verification = new DocumentVerification();
    }

    /**
     * Surat Penawaran Harga (proposal / quotation).
     *
     * @param int $id Proposal id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function proposal(int $id): array
    {
        $proposal = (new ProposalModel())->withDetails()->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $number   = $proposal['cpl_id'];

        return $this->result('pdf/proposal', 'QUO', $id, $number, [
            'proposal'  => $proposal,
            'items'     => (new ProposalItemModel())->forProposal($id),
            'customer'  => $this->customer((int) $proposal['customer_id']),
            'contact'   => $this->contact($proposal['contact_person_id']),
            'watermark' => match ($proposal['status_code']) {
                ProposalWorkflow::DRAFT, ProposalWorkflow::UNDER_REVIEW         => 'DRAFT',
                ProposalWorkflow::CANCEL_BY_CLIENT, ProposalWorkflow::CANCEL_BY_STORE => 'BATAL',
                default                                                          => null,
            },
        ], 'Penawaran ' . $number);
    }

    /**
     * Surat Perintah Kerja (agreement) of an approved proposal.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function agreement(int $proposalId): array
    {
        $agreement = (new AgreementModel())->forProposal($proposalId) ?? throw PageNotFoundException::forPageNotFound();
        $proposal  = (new ProposalModel())->withDetails()->find($proposalId) ?? throw PageNotFoundException::forPageNotFound();
        $number    = $agreement['agreement_number'];

        return $this->result('pdf/agreement', 'SPK', (int) $agreement['id'], $number, [
            'agreement' => $agreement,
            'proposal'  => $proposal,
            'items'     => (new ProposalItemModel())->forProposal($proposalId),
            'customer'  => $this->customer((int) $proposal['customer_id']),
            'terms'     => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $agreement['terms'])))),
            'watermark' => in_array($proposal['status_code'], [ProposalWorkflow::CANCEL_BY_CLIENT, ProposalWorkflow::CANCEL_BY_STORE], true) ? 'BATAL' : null,
        ], 'SPK ' . $number);
    }

    /**
     * Invoice.
     *
     * @param int $id Invoice id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function invoice(int $id): array
    {
        $invoice = (new InvoiceModel())->withDetails()->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $items   = (new InvoiceItemModel())->forInvoice($id);
        $isDraft = $invoice['status_code'] === InvoiceWorkflow::DRAFT;
        $number  = InvoiceModel::displayNumber($invoice);

        return $this->result('pdf/invoice', 'INV', $id, $number, [
            'invoice'     => $invoice,
            'items'       => $items,
            'withholding' => $isDraft ? (new InvoiceService())->calculateWithholding($invoice, $items, date('Y-m-d')) : (new InvoiceWithholdingModel())->forInvoice($id),
            'customer'    => $this->customer((int) $invoice['customer_id']),
            'bank'        => $invoice['bank_account_id'] ? (new BankAccountModel())->withDeleted()->find($invoice['bank_account_id']) : null,
            'agreement'   => $invoice['proposal_id'] ? (new AgreementModel())->forProposal((int) $invoice['proposal_id']) : null,
            'watermark'   => match ($invoice['status_code']) {
                InvoiceWorkflow::DRAFT                           => 'DRAFT',
                InvoiceWorkflow::VOID, InvoiceWorkflow::CANCELLED => 'BATAL',
                InvoiceWorkflow::PAID                            => 'LUNAS',
                default                                          => null,
            },
        ], 'Invoice ' . $number, ! $isDraft);
    }

    /**
     * Kwitansi (receipt) of one payment: cash received plus the taxes withheld with it.
     *
     * @param int $paymentId Payment id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function receipt(int $paymentId): array
    {
        $payment = (new PaymentModel())->withDetails()->find($paymentId) ?? throw PageNotFoundException::forPageNotFound();
        $invoice = (new InvoiceModel())->withDetails()->find((int) $payment['invoice_id']);
        $taxes   = (new PaymentWithholdingModel())->where('payment_id', $paymentId)->orderBy('id')->findAll();
        $gross   = bcadd((string) $payment['amount'], (string) $payment['withheld'], 2);
        $number  = $payment['receipt_number'] ?: 'KW-' . $paymentId;

        return $this->result('pdf/receipt', 'KW', $paymentId, $number, [
            'payment'   => $payment,
            'invoice'   => $invoice,
            'taxes'     => $taxes,
            'gross'     => $gross,
            'customer'  => $this->customer((int) $invoice['customer_id']),
            'stampDuty' => bccomp($gross, config(Documents::class)->stampDutyThreshold, 2) > 0,
            'watermark' => $payment['reversed_at'] !== null ? 'BATAL' : null,
        ], 'Kwitansi ' . $number);
    }

    /**
     * Surat Jalan (delivery note).
     *
     * @param int $id Delivery note id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function deliveryNote(int $id): array
    {
        $data   = $this->deliveryData($id);
        $number = $data['note']['delivery_number'];

        return $this->result('pdf/delivery_note', 'DO', $id, $number, $data, 'Surat Jalan ' . $number);
    }

    /**
     * Berita Acara Serah Terima (handover report) of a delivery note. Number derived from the DO number.
     *
     * @param int $id Delivery note id.
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    public function handover(int $id): array
    {
        $data   = $this->deliveryData($id);
        $number = $data['note']['handover_number'] ?: self::handoverNumber($data['note']['delivery_number']);
        $date   = $data['note']['delivered_at'] ?? $data['note']['ship_date'] ?? date('Y-m-d');

        return $this->result('pdf/handover', 'BAST', $id, $number, $data + [
            'handoverDate' => substr((string) $date, 0, 10),
        ], 'BAST ' . $number);
    }

    /**
     * BAST number of a delivery note: DO/2026/11/0001 -> BAST/2026/11/0001.
     *
     * @param string $deliveryNumber Delivery note number.
     *
     * @return string
     */
    public static function handoverNumber(string $deliveryNumber): string
    {
        return (string) preg_replace('/^DO\//', 'BAST/', $deliveryNumber);
    }

    /**
     * Shared data of delivery documents.
     *
     * @param int $id Delivery note id.
     *
     * @return array<string, mixed>
     */
    private function deliveryData(int $id): array
    {
        $note    = (new DeliveryNoteModel())->withDetails()->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $invoice = (new InvoiceModel())->withDetails()->find((int) $note['invoice_id']);

        return [
            'note'      => $note,
            'items'     => (new DeliveryNoteItemModel())->forNote($id),
            'invoice'   => $invoice,
            'customer'  => $this->customer((int) $invoice['customer_id']),
            'agreement' => $invoice['proposal_id'] ? (new AgreementModel())->forProposal((int) $invoice['proposal_id']) : null,
            'watermark' => $note['cancelled_at'] !== null ? 'BATAL' : null,
        ];
    }

    /**
     * Customer block: name, school data, default billing address.
     *
     * @param int $customerId Customer id.
     *
     * @return array<string, mixed>
     */
    private function customer(int $customerId): array
    {
        $customer  = (new CustomerModel())->withDeleted()->find($customerId) ?? [];
        $addresses = (new CustomerAddressModel())->forCustomer($customerId);
        $billing   = array_values(array_filter($addresses, static fn (array $a): bool => $a['address_type'] === 'BILLING'));
        $address   = $billing[0] ?? $addresses[0] ?? null;
        $level     = ! empty($customer['school_level_id']) ? (new SchoolLevelModel())->find($customer['school_level_id']) : null;

        return $customer + [
            'address_text' => $address !== null ? CustomerAddressModel::format($address) : '',
            'level_name'   => $level['name'] ?? null,
        ];
    }

    /**
     * Contact person row (or null).
     *
     * @param int|string|null $contactId Contact id.
     *
     * @return array<string, mixed>|null
     */
    private function contact(int|string|null $contactId): ?array
    {
        $contact = $contactId ? (new CustomerContactModel())->findWithPerson((int) $contactId) : null;

        return $contact === null ? null : $contact + ['role_label' => CustomerContactModel::roleLabel($contact, 'id')];
    }

    /**
     * Add the common context (company, logos, verification QR, place) and build the result.
     *
     * @param string               $view     View name.
     * @param string               $type     DocumentVerification type.
     * @param int                  $docId    Document id for the verification token.
     * @param string               $number   Document number.
     * @param array<string, mixed> $data     Document data.
     * @param string               $filename File name (without .pdf).
     * @param bool                 $verify   Print the verification QR (not for drafts).
     *
     * @return array{view: string, data: array<string, mixed>, filename: string, footer: string}
     */
    /**
     * Name / title / address columns that are stored in lower case (see BaseModel::$lowercase). Printed in upper case.
     */
    private const NAME_COLUMNS = [
        'cl_organization_name', 'customer_name', 'address_text', 'billing_address', 'shipping_address', 'address_line', 'city', 'province',
        'rep_name', 'cpl_proposal_title', 'item_title', 'client_signatory_name', 'client_signatory_position',
        'store_signatory_name', 'store_signatory_position', 'received_by_name', 'courier_name', 'bank_name',
        'bank_account_name', 'ccp_first_name', 'ccp_last_name', 'ccp_position', 'role_label', 'location', 'full_name',
    ];

    /**
     * Document data with the stored lower-case names in upper case (rows and lists of rows, one level deep).
     *
     * @param array<string, mixed> $data View data.
     *
     * @return array<string, mixed>
     */
    private static function upperNames(array $data): array
    {
        $row = static function (array $r): array {
            foreach (self::NAME_COLUMNS as $col) {
                if (isset($r[$col]) && is_string($r[$col])) {
                    $r[$col] = up($r[$col]);
                }
            }

            return $r;
        };

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $data[$key] = array_is_list($value)
                ? array_map(static fn ($v) => is_array($v) ? $row($v) : $v, $value)
                : $row($value);
        }

        return $data;
    }

    private function result(string $view, string $type, int $docId, string $number, array $data, string $filename, bool $verify = true): array
    {
        $config  = config(Documents::class);
        $profile = (new CompanyProfileModel())->getProfile() ?? [];
        $logo    = ! empty($profile['logo_path']) ? Pdf::imageDataUri(WRITEPATH . 'uploads/' . $profile['logo_path']) : null;
        $url     = $verify ? $this->verification->url($type, $docId) : null;
        $company = $profile['company_name'] ?? 'Warung Sahabat';

        return [
            'view'     => $view,
            'data'     => self::upperNames($data) + [
                'number'    => $number,
                'company'   => $profile + ['company_name' => $company],
                'logo'      => $logo ?? Pdf::imageDataUri(FCPATH . $config->defaultStoreLogo),
                'groupName' => $config->groupName,
                'groupLogo' => Pdf::imageDataUri(FCPATH . $config->groupLogo),
                'verifyUrl' => $url,
                'qr'        => $url !== null ? DocumentVerification::qrDataUri($url) : null,
                'place'     => $profile['document_city'] ?? '',
            ],
            'filename' => $filename,
            'footer'   => $company . ' · bagian dari grup ' . $config->groupName . ' · ' . $number,
        ];
    }
}
