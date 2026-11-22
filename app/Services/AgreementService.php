<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgreementModel;
use App\Models\CompanyProfileModel;
use App\Models\CustomerContactModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Surat Perintah Kerja (SPK) / agreement from an approved proposal. Some schools use the approved quotation as
 * the work order between the school and the store; this stores the extra data the signed document needs
 * (dates, both signatories, clauses) and gives it an SPK number. Items and amounts come from the proposal.
 */
final class AgreementService
{
    /**
     * Agreement fields accepted from the form.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'agreement_date', 'completion_date', 'client_signatory_name', 'client_signatory_position', 'client_signatory_nip',
        'store_signatory_name', 'store_signatory_position', 'terms',
    ];

    /**
     * Built-in clauses (Indonesian, printed as is) when Settings has none. To be reviewed by the store's adviser.
     * PIHAK PERTAMA = the store (Penyedia), PIHAK KEDUA = the customer (Pemesan), as in the store's own documents.
     */
    public const DEFAULT_TERMS = <<<'TXT'
        PIHAK PERTAMA menyediakan dan menyerahkan barang/jasa sesuai rincian dalam surat ini kepada PIHAK KEDUA.
        Barang/jasa diserahkan paling lambat pada tanggal penyelesaian yang tercantum, di alamat PIHAK KEDUA, disertai Berita Acara Serah Terima.
        PIHAK KEDUA membayar nilai pekerjaan berdasarkan invoice sesuai ketentuan pembayaran yang tercantum.
        Pajak yang wajib dipungut/dipotong oleh bendahara PIHAK KEDUA dilaksanakan sesuai ketentuan perpajakan yang berlaku.
        Barang yang rusak atau tidak sesuai spesifikasi pada saat serah terima diganti oleh PIHAK PERTAMA.
        Perubahan atas isi surat ini hanya dapat dilakukan atas persetujuan tertulis kedua belah pihak.
        Perselisihan yang timbul diselesaikan secara musyawarah untuk mufakat.
        TXT;

    public function __construct(private readonly AgreementModel $agreements = new AgreementModel())
    {
    }

    /**
     * Prefilled values for a new agreement: today, the planned delivery date, the proposal's contact person and the
     * company signatory, and the clauses from Settings (or the built-in ones).
     *
     * @param array<string, mixed> $proposal Proposal row.
     *
     * @return array<string, string|null>
     */
    public function defaults(array $proposal): array
    {
        $profile = (new CompanyProfileModel())->getProfile() ?? [];
        // The school's head (role HEADMASTER) usually signs the SPK; else the proposal's contact person.
        $links   = new CustomerContactModel();
        $contact = $links->withPerson()->where('customer_contact_person.customer_id', (int) $proposal['customer_id'])
            ->where('customer_contact_person.ended_on', null)->where('r.code', 'HEADMASTER')->first()
            ?? ($proposal['contact_person_id'] ? $links->findWithPerson((int) $proposal['contact_person_id']) : null);

        return [
            'agreement_date'            => date('Y-m-d'),
            'completion_date'           => $proposal['delivery_date'] ?? null,
            'client_signatory_name'     => $contact !== null ? trim($contact['ccp_first_name'] . ' ' . ($contact['ccp_last_name'] ?? '')) : '',
            'client_signatory_position' => $contact !== null ? mb_strtolower(CustomerContactModel::roleLabel($contact)) : '',
            'client_signatory_nip'      => (string) ($contact['cp_nip'] ?? ''),
            'store_signatory_name'      => $profile['signatory_name'] ?? '',
            'store_signatory_position'  => $profile['signatory_position'] ?? '',
            'terms'                     => trim((string) ($profile['agreement_terms'] ?? '')) !== '' ? $profile['agreement_terms'] : self::DEFAULT_TERMS,
        ];
    }

    /**
     * Create the agreement of an approved proposal (SPK number) or update it.
     *
     * @param int                  $proposalId Proposal id.
     * @param array<string, mixed> $data       FIELDS.
     * @param int                  $userId     Current user.
     *
     * @return int Agreement id.
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the proposal is not approved.
     */
    public function save(int $proposalId, array $data, int $userId): int
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $clean[$field] = trim((string) ($data[$field] ?? ''));
        }

        $errors = $this->agreements->validate($clean) ? [] : $this->agreements->errors();

        if (! isset($errors['completion_date']) && ! isset($errors['agreement_date'])
            && $clean['completion_date'] !== '' && $clean['completion_date'] < $clean['agreement_date']) {
            $errors['completion_date'] = lang('Messages.completionBeforeAgreement');
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return Transaction::run(function (BaseConnection $db) use ($proposalId, $clean, $userId): int {
            $proposal = $db->query(
                'SELECT p.id, s.code FROM client_proposal_list p JOIN client_proposal_status s ON s.id = p.status_id WHERE p.id = ? AND p.deleted_at IS NULL FOR UPDATE',
                [$proposalId],
            )->getRowArray() ?? throw new RuntimeException(lang('Messages.notFoundProposal'));

            if ($proposal['code'] !== ProposalWorkflow::STORE_APPROVE) {
                throw new RuntimeException(lang('Messages.spkOnlyApproved'));
            }

            $existing = $this->agreements->forProposal($proposalId);

            if ($existing !== null) {
                $this->agreements->update((int) $existing['id'], $clean);

                return (int) $existing['id'];
            }

            $id = $this->agreements->insert($clean + [
                'agreement_number' => (new DocumentSequence())->next('SPK'),
                'proposal_id'      => $proposalId,
                'created_by'       => $userId,
            ]);

            if ($id === false) {
                throw new ValidationFailed($this->agreements->errors());
            }

            return (int) $id;
        });
    }
}
