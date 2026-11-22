<?php

namespace Config;

use App\Models\ClientProposalStatusModel;
use App\Models\FundingSourceModel;
use App\Models\InvoiceStatusModel;
use App\Models\PaymentMethodModel;
use App\Models\PaymentTermModel;
use App\Models\SchoolLevelModel;
use App\Models\ShipmentModel;
use App\Models\TaxRateModel;
use App\Models\TaxWithholdingRuleModel;
use App\Models\UnitModel;
use CodeIgniter\Config\BaseConfig;

/**
 * Lookup tables managed by the generic Settings\Lookups screen (URL slug => definition).
 *
 * 'fields'   : form fields: name => [label, type, help?, step?, options?, width?]
 *              type: text | number | date | textarea | select (with 'options' value => label) | checkbox
 *              width: Bootstrap column class (default: full width for text, half for number/date/select)
 * 'columns'  : list columns: name => label
 * 'formats'  : optional column formats: percent | money | date | bool
 * 'readonly' : system lookups whose codes drive application logic (view only)
 * The 'code' field is set on create and locked afterwards (application logic refers to codes).
 */
class Lookups extends BaseConfig
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $types = [
        'payment-methods' => [
            'title'       => 'Payment Methods',
            'description' => 'How customers pay (bank transfer, cash).',
            'icon'        => 'wallet2',
            'model'       => PaymentMethodModel::class,
            'columns'     => ['code' => 'Code', 'name' => 'Name'],
            'fields'      => [
                'code' => ['label' => 'Code', 'type' => 'text', 'help' => 'UPPER_SNAKE_CASE, e.g. TRANSFER. Cannot be changed later.'],
                'name' => ['label' => 'Name', 'type' => 'text'],
            ],
        ],
        'payment-terms' => [
            'title'       => 'Payment Terms',
            'description' => 'Due days used to calculate invoice due dates.',
            'icon'        => 'calendar-check',
            'model'       => PaymentTermModel::class,
            'columns'     => ['code' => 'Code', 'due_days' => 'Due days', 'description' => 'Description'],
            'fields'      => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. NET_30. Cannot be changed later.'],
                'due_days'    => ['label' => 'Due days', 'type' => 'number', 'step' => '1', 'help' => 'Days after the invoice date; 0 = on delivery.'],
                'description' => ['label' => 'Description', 'type' => 'text'],
            ],
        ],
        'shipments' => [
            'title'       => 'Shipment Types',
            'description' => 'How goods reach the customer.',
            'icon'        => 'truck',
            'model'       => ShipmentModel::class,
            'columns'     => ['code' => 'Code', 'name' => 'Name'],
            'fields'      => [
                'code' => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. THIRD_PARTY. Cannot be changed later.'],
                'name' => ['label' => 'Name', 'type' => 'text'],
            ],
        ],
        'units' => [
            'title'       => 'Units of Measurement',
            'description' => 'Units used on products and document lines.',
            'icon'        => 'rulers',
            'model'       => UnitModel::class,
            'columns'     => ['code' => 'Code', 'name' => 'Name'],
            'fields'      => [
                'code' => ['label' => 'Code', 'type' => 'text', 'help' => 'Short code printed on documents, e.g. PCS. Cannot be changed later.'],
                'name' => ['label' => 'Name', 'type' => 'text'],
            ],
        ],
        'school-levels' => [
            'title'       => 'School Levels',
            'description' => 'Levels used to classify school customers (SD, SMP, MI, MTs, ...).',
            'icon'        => 'mortarboard',
            'model'       => SchoolLevelModel::class,
            'order'       => 'sort_order',
            'columns'     => ['sort_order' => '#', 'code' => 'Code', 'name' => 'Name'],
            'fields'      => [
                'code'       => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. SMK. Cannot be changed later.'],
                'name'       => ['label' => 'Name', 'type' => 'text'],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'step' => '1'],
            ],
        ],
        'contact-roles' => [
            'title'       => 'Contact Roles',
            'description' => 'Roles of contact persons at a school (head of school, BOS treasurer, operator, ...).',
            'icon'        => 'person-vcard',
            'model'       => \App\Models\ContactRoleModel::class,
            'order'       => 'sort_order',
            'columns'     => ['sort_order' => '#', 'code' => 'Code', 'name' => 'Name'],
            'fields'      => [
                'code'       => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. TREASURER. Cannot be changed later.'],
                'name'       => ['label' => 'Name', 'type' => 'text'],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'step' => '1'],
            ],
        ],
        'funding-sources' => [
            'title'       => 'Funding Sources',
            'description' => 'Where a school pays from (BOSP, APBD, committee funds, ...). BOS funds change tax withholding.',
            'icon'        => 'piggy-bank',
            'model'       => FundingSourceModel::class,
            'order'       => 'sort_order',
            'columns'     => ['sort_order' => '#', 'code' => 'Code', 'name' => 'Name', 'is_bos' => 'BOS funds', 'description' => 'Note'],
            'formats'     => ['is_bos' => 'bool'],
            'fields'      => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. BOSP. Cannot be changed later.'],
                'name'        => ['label' => 'Name', 'type' => 'text'],
                'is_bos'      => ['label' => 'BOS funds (PPh 22 is not withheld on goods bought with BOS funds)', 'type' => 'checkbox'],
                'description' => ['label' => 'Note', 'type' => 'text'],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'step' => '1'],
            ],
        ],
        'tax-rates' => [
            'title'       => 'Tax Rates',
            'description' => 'PPN and other rates with validity periods. A rate change is a new row.',
            'icon'        => 'percent',
            'model'       => TaxRateModel::class,
            'order'       => 'code',
            'columns'     => ['code' => 'Code', 'rate' => 'Rate', 'valid_from' => 'Valid from', 'valid_to' => 'Valid to'],
            'formats'     => ['rate' => 'percent', 'valid_from' => 'date', 'valid_to' => 'date'],
            'fields'      => [
                'code'       => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. PPN. Cannot be changed later.'],
                'rate'       => ['label' => 'Rate (%)', 'type' => 'number', 'step' => '0.01'],
                'valid_from' => ['label' => 'Valid from', 'type' => 'date'],
                'valid_to'   => ['label' => 'Valid to', 'type' => 'date', 'help' => 'Leave empty while the rate is still valid.'],
            ],
        ],
        'tax-withholding-rules' => [
            'title'       => 'Tax Withholding Rules',
            'description' => 'Taxes the customer\'s treasurer collects or withholds (PPN, PPh 22, PPh 23). Confirm with your tax adviser.',
            'icon'        => 'shield-check',
            'model'       => TaxWithholdingRuleModel::class,
            'order'       => 'tax_kind',
            'columns'     => [
                'code' => 'Code', 'name' => 'Name', 'customer_status' => 'Customer', 'item_type' => 'Items',
                'rate' => 'Rate', 'umkm_rate' => 'UMKM', 'threshold' => 'Above', 'skip_for_bos' => 'Skip BOS',
                'valid_from' => 'From', 'is_active' => 'Active',
            ],
            'formats'     => [
                'rate' => 'percent', 'umkm_rate' => 'percent', 'threshold' => 'money', 'skip_for_bos' => 'bool',
                'is_active' => 'bool', 'valid_from' => 'date',
            ],
            'fields'      => [
                'code'            => ['label' => 'Code', 'type' => 'text', 'help' => 'e.g. PPH22_GOV. Cannot be changed later; add a new row with a later "valid from" when a regulation changes.'],
                'name'            => ['label' => 'Name', 'type' => 'text'],
                'tax_kind'        => ['label' => 'Tax', 'type' => 'select', 'options' => ['PPN' => 'PPN (VAT collected)', 'PPH22' => 'PPh 22', 'PPH23' => 'PPh 23']],
                'base'            => ['label' => 'Base', 'type' => 'select', 'options' => ['DPP' => 'DPP (amount before VAT)', 'PPN' => 'PPN amount']],
                'customer_status' => ['label' => 'Applies to customers', 'type' => 'select', 'options' => ['ALL' => 'All customers', 'PUBLIC' => 'Public schools / government', 'PRIVATE' => 'Private schools / others']],
                'item_type'       => ['label' => 'Applies to lines', 'type' => 'select', 'options' => ['ALL' => 'All lines', 'GOODS' => 'Goods only', 'SERVICE' => 'Services only']],
                'rate'            => ['label' => 'Rate (%)', 'type' => 'number', 'step' => '0.01', 'help' => 'For PPN collection use 100 (= the full PPN amount).'],
                'umkm_rate'       => ['label' => 'UMKM final rate (%)', 'type' => 'number', 'step' => '0.01', 'help' => 'Used instead of the rate when the company has a valid SK PP 55 certificate. Empty = not applicable.'],
                'threshold'       => ['label' => 'Only when invoice amount is above (Rp)', 'type' => 'number', 'step' => '0.01'],
                'threshold_basis' => ['label' => 'Threshold measured on', 'type' => 'select', 'options' => ['DPP' => 'DPP (before VAT)', 'GROSS' => 'Total incl. VAT']],
                'skip_for_bos'    => ['label' => 'Do not apply when paid with BOS funds', 'type' => 'checkbox'],
                'requires_pkp'    => ['label' => 'Only when our company is PKP', 'type' => 'checkbox'],
                'regulation'      => ['label' => 'Regulation reference', 'type' => 'textarea'],
                'valid_from'      => ['label' => 'Valid from', 'type' => 'date'],
                'valid_to'        => ['label' => 'Valid to', 'type' => 'date', 'help' => 'Leave empty while the rule is still valid.'],
                'is_active'       => ['label' => 'Active', 'type' => 'checkbox', 'default' => true],
            ],
        ],
        'proposal-statuses' => [
            'title'       => 'Proposal Statuses',
            'description' => 'System statuses for the proposal workflow (read-only).',
            'icon'        => 'diagram-3',
            'model'       => ClientProposalStatusModel::class,
            'readonly'    => true,
            'order'       => 'sort_order',
            'columns'     => ['sort_order' => '#', 'code' => 'Code', 'cps_description' => 'Description'],
            'fields'      => [],
        ],
        'invoice-statuses' => [
            'title'       => 'Invoice Statuses',
            'description' => 'System statuses for the invoice workflow (read-only).',
            'icon'        => 'diagram-3',
            'model'       => InvoiceStatusModel::class,
            'readonly'    => true,
            'order'       => 'sort_order',
            'columns'     => ['sort_order' => '#', 'code' => 'Code', 'description' => 'Description'],
            'fields'      => [],
        ],
    ];
}
