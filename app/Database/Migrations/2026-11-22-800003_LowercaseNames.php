<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Names, titles and addresses are stored in lower case and shown in upper case (normalize_name() / up()).
 * Convert the rows stored before this rule. Emails, codes, numbers and paragraphs are not touched.
 * down() does nothing: the original spelling is not kept (display is upper case either way).
 */
class LowercaseNames extends BaseMigration
{
    /** table => columns (same lists as the models' $lowercase). */
    public const COLUMNS = [
        'customer_list'           => ['cl_organization_name'],
        'customer_contact_person' => ['ccp_first_name', 'ccp_last_name', 'ccp_position'],
        'customer_address'        => ['address_line', 'city', 'province'],
        'sales_representative'    => ['rep_name'],
        'product_list'            => ['pl_title', 'pl_alias'],
        'product_variant'         => ['pv_name'],
        'client_proposal_list'    => ['cpl_proposal_title'],
        'client_proposal_item'    => ['item_title'],
        'invoice'                 => ['billing_address'],
        'invoice_item'            => ['item_title'],
        'credit_note_item'        => ['item_title'],
        'bank_account_list'       => ['bank_name', 'bank_account_name'],
        'agreement'               => ['client_signatory_name', 'client_signatory_position', 'store_signatory_name', 'store_signatory_position'],
        'delivery_note'           => ['shipping_address', 'courier_name', 'received_by_name'],
        'delivery_note_event'     => ['location'],
        'users'                   => ['full_name'],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = LOWER(TRIM(`{$c}`))", $columns));
            try {
                $this->db->query("UPDATE `{$table}` SET {$set}");
            } catch (\Throwable $e) {
                // Tables guarded by the protection triggers (issued invoice / credit note lines) keep their spelling;
                // they are shown in upper case anyway.
                log_message('warning', 'LowercaseNames: {table} skipped: {message}', ['table' => $table, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
    }
}
