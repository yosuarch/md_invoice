<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * Loads lookup data. Safe to run repeatedly: rows are matched on their unique key
 * and existing rows are left untouched (INSERT IGNORE).
 */
class LookupSeeder extends Seeder
{
    /**
     * Seed all lookup tables inside one transaction.
     *
     * @return void
     */
    public function run(): void
    {
        $this->db->transException(true)->transStart();

        try {
            foreach ($this->data() as $table => $rows) {
                $this->insertIgnore($table, $rows);
            }

            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    /**
     * Insert rows, skipping any that already exist (by unique key).
     *
     * @param string                           $table Table name.
     * @param list<array<string, int|string>>  $rows  Rows without timestamps.
     *
     * @return void
     */
    private function insertIgnore(string $table, array $rows): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $this->db->table($table)->ignore(true)->insert($row);
        }
    }

    /**
     * Lookup rows per table.
     *
     * @return array<string, list<array<string, int|string>>>
     */
    private function data(): array
    {
        return [
            'payment_method' => [
                ['code' => 'TRANSFER', 'name' => 'Bank Transfer'],
                ['code' => 'CASH',     'name' => 'Cash'],
            ],
            'payment_terms' => [
                ['code' => 'ON_DELIVERY', 'due_days' => 0,  'description' => 'Payment right after the service or item arrives'],
                ['code' => 'NET_7',       'due_days' => 7,  'description' => '7 days after received'],
                ['code' => 'NET_14',      'due_days' => 14, 'description' => '14 days after received'],
                ['code' => 'NET_30',      'due_days' => 30, 'description' => '30 days after received'],
            ],
            'shipment' => [
                ['code' => 'PICK_AT_STORE', 'name' => 'Pick up at store'],
                ['code' => 'PRIVATE',       'name' => 'Private delivery'],
                ['code' => 'THIRD_PARTY',   'name' => 'Third-party courier'],
            ],
            'unit_of_measurement' => [
                ['code' => 'PCS', 'name' => 'Pieces'],
                ['code' => 'BOX', 'name' => 'Box'],
                ['code' => 'KG',  'name' => 'Kilogram'],
                ['code' => 'SET', 'name' => 'Set'],
            ],
            'tax_rate' => [
                // Confirm the PPN rate/DPP treatment with your accountant before issuing invoices.
                ['code' => 'PPN',     'rate' => '11.00', 'valid_from' => '2022-04-01'],
                ['code' => 'NON_PPN', 'rate' => '0.00',  'valid_from' => '2022-04-01'],
            ],
            'client_proposal_status' => [
                ['code' => 'DRAFT',            'cps_description' => 'Proposal not yet submitted by the client',     'sort_order' => 1],
                ['code' => 'PROPOSE',          'cps_description' => 'Proposal submitted by client',                 'sort_order' => 2],
                ['code' => 'UNDER_REVIEW',     'cps_description' => 'Under review by the store (negotiation phase)', 'sort_order' => 3],
                ['code' => 'STORE_APPROVE',    'cps_description' => 'Store approved the proposal',                  'sort_order' => 4],
                ['code' => 'CANCEL_BY_CLIENT', 'cps_description' => 'Cancelled or rejected by the client',          'sort_order' => 5],
                ['code' => 'CANCEL_BY_STORE',  'cps_description' => 'Cancelled or rejected by the store',           'sort_order' => 6],
            ],
            'invoice_status' => [
                ['code' => 'DRAFT',          'description' => 'Not yet issued, still editable',  'sort_order' => 1],
                ['code' => 'ISSUED',         'description' => 'Sent to customer, locked',        'sort_order' => 2],
                ['code' => 'PARTIALLY_PAID', 'description' => 'Some payment received',           'sort_order' => 3],
                ['code' => 'PAID',           'description' => 'Fully paid',                      'sort_order' => 4],
                ['code' => 'OVERDUE',        'description' => 'Past due date and not fully paid', 'sort_order' => 5],
                ['code' => 'VOID',           'description' => 'Cancelled before any payment (number kept)', 'sort_order' => 6],
                ['code' => 'CANCELLED',      'description' => 'Fully credited after issue (order cancelled); refund if paid', 'sort_order' => 7],
            ],
            // Roles of contact persons at a school (Settings → Lookups)
            'contact_role' => [
                ['code' => 'HEADMASTER', 'name' => 'Head of school', 'sort_order' => 1],
                ['code' => 'TREASURER',  'name' => 'BOS treasurer',  'sort_order' => 2],
                ['code' => 'OPERATOR',   'name' => 'School operator', 'sort_order' => 3],
                ['code' => 'COMMITTEE',  'name' => 'School committee', 'sort_order' => 4],
                ['code' => 'FOUNDATION', 'name' => 'Foundation',      'sort_order' => 5],
                ['code' => 'OTHER',      'name' => 'Other',           'sort_order' => 99],
            ],
            // School levels (Ministry of Education schools + Ministry of Religious Affairs madrasahs)
            'school_level' => [
                ['code' => 'PAUD', 'name' => 'PAUD / KB', 'sort_order' => 1],
                ['code' => 'TK',   'name' => 'TK',        'sort_order' => 2],
                ['code' => 'RA',   'name' => 'RA',        'sort_order' => 3],
                ['code' => 'SD',   'name' => 'SD',        'sort_order' => 4],
                ['code' => 'MI',   'name' => 'MI',        'sort_order' => 5],
                ['code' => 'SMP',  'name' => 'SMP',       'sort_order' => 6],
                ['code' => 'MTS',  'name' => 'MTs',       'sort_order' => 7],
                ['code' => 'SMA',  'name' => 'SMA',       'sort_order' => 8],
                ['code' => 'MA',   'name' => 'MA',        'sort_order' => 9],
                ['code' => 'SMK',  'name' => 'SMK',       'sort_order' => 10],
                ['code' => 'MAK',  'name' => 'MAK',       'sort_order' => 11],
                ['code' => 'SLB',  'name' => 'SLB',       'sort_order' => 12],
                ['code' => 'PKBM', 'name' => 'PKBM / SKB', 'sort_order' => 13],
                ['code' => 'OTHER', 'name' => 'Other',    'sort_order' => 99],
            ],
            // is_bos = 1: purchases with these funds are exempt from PPh 22 (PMK 81/2024 Article 219). Verify BOSDA locally.
            'funding_source' => [
                ['code' => 'BOSP',         'name' => 'BOSP (regular BOS/BOP, Ministry of Education)', 'is_bos' => 1, 'sort_order' => 1],
                ['code' => 'BOS_MADRASAH', 'name' => 'BOS Madrasah (Ministry of Religious Affairs)',                 'is_bos' => 1, 'sort_order' => 2],
                ['code' => 'BOS_REGIONAL',  'name' => 'Regional BOS (BOSDA, APBD)',               'is_bos' => 0, 'sort_order' => 3,
                    'description' => 'Regional APBD funds; confirm whether the BOS exemption applies.'],
                ['code' => 'APBD',         'name' => 'APBD',                                   'is_bos' => 0, 'sort_order' => 4],
                ['code' => 'APBN',         'name' => 'APBN (non-BOS)',                         'is_bos' => 0, 'sort_order' => 5],
                ['code' => 'COMMITTEE',    'name' => 'School committee / parent funds',                'is_bos' => 0, 'sort_order' => 6],
                ['code' => 'FOUNDATION',   'name' => 'Foundation funds',                           'is_bos' => 0, 'sort_order' => 7],
                ['code' => 'OTHER',        'name' => 'Other',                                  'is_bos' => 0, 'sort_order' => 99],
            ],
            // Data-driven withholding rules. Rates/thresholds/regulations must be confirmed by a tax adviser.
            'tax_withholding_rule' => [
                [
                    'code' => 'PPN_COLLECT_GOV', 'name' => 'VAT collected by government treasurer',
                    'tax_kind' => 'PPN', 'base' => 'PPN', 'item_type' => 'ALL', 'customer_status' => 'PUBLIC',
                    'rate' => '100.00', 'threshold' => '2000000.00', 'threshold_basis' => 'GROSS',
                    'skip_for_bos' => 0, 'requires_pkp' => 1, 'valid_from' => '2025-01-01',
                    'regulation' => 'VAT Law Art. 16A; PMK 81/2024. Tax invoice (faktur pajak) transaction code 02.',
                ],
                [
                    'code' => 'PPH22_GOV', 'name' => 'PPh 22 by government treasurer (purchase of goods)',
                    'tax_kind' => 'PPH22', 'base' => 'DPP', 'item_type' => 'GOODS', 'customer_status' => 'PUBLIC',
                    'rate' => '1.50', 'umkm_rate' => '0.50', 'threshold' => '2000000.00', 'threshold_basis' => 'DPP',
                    'skip_for_bos' => 1, 'requires_pkp' => 0, 'valid_from' => '2025-01-01',
                    'regulation' => 'Income Tax Law Art. 22; PMK 81/2024 (Art. 219: not collected on BOS-funded purchases). UMKM with certificate: PP 55/2022 0.5% final.',
                ],
                [
                    'code' => 'PPH23_SERVICE', 'name' => 'PPh 23 on services',
                    'tax_kind' => 'PPH23', 'base' => 'DPP', 'item_type' => 'SERVICE', 'customer_status' => 'ALL',
                    'rate' => '2.00', 'umkm_rate' => '0.50', 'threshold' => '0.00', 'threshold_basis' => 'DPP',
                    'skip_for_bos' => 0, 'requires_pkp' => 0, 'valid_from' => '2025-01-01',
                    'regulation' => 'Income Tax Law Art. 23; PMK 141/2015 (other services). UMKM with certificate: PP 55/2022 0.5% final.',
                ],
            ],
        ];
    }
}
