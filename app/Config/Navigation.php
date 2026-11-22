<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Sidebar navigation for the staff app (app_layout).
 *
 * Item keys:
 *   'label'      language key of the text shown in the menu (app/Language/<locale>/Nav.php)
 *   'uri'        path relative to baseURL
 *   'icon'       Bootstrap Icons name (without the "bi-" prefix)
 *   'permission' Shield permission required to see the item (omit = every logged-in user)
 *   'exact'      true = active only on 'uri' itself, not 'uri/*'
 *
 * Items whose module is not built yet still link to their future URI.
 */
class Navigation extends BaseConfig
{
    /**
     * Company name (logo alt text, documents).
     */
    public string $companyName = "de'makmur";

    /**
     * Module caption shown under the logo.
     */
    public string $moduleName = 'Invoicing';

    /**
     * Application name used in page titles and the footer.
     */
    public string $appName = "de'makmur Invoicing";

    /**
     * Sidebar sections: heading language key => items. An empty heading renders no title.
     *
     * @var array<string, list<array{label: string, uri: string, icon: string, permission?: string, exact?: bool}>>
     */
    public array $sidebar = [
        '' => [
            ['label' => 'Nav.dashboard', 'uri' => '/', 'icon' => 'speedometer2', 'exact' => true],
        ],
        'Nav.sales' => [
            ['label' => 'Nav.proposals',  'uri' => 'proposals',  'icon' => 'file-earmark-text', 'permission' => 'proposals.view'],
            ['label' => 'Nav.activities', 'uri' => 'activities', 'icon' => 'calendar-check', 'permission' => 'crm.view'],
            ['label' => 'Nav.deliveries', 'uri' => 'deliveries', 'icon' => 'truck',             'permission' => 'deliveries.view'],
        ],
        'Nav.finance' => [
            ['label' => 'Nav.invoices', 'uri' => 'invoices',     'icon' => 'receipt',          'permission' => 'invoices.view'],
            ['label' => 'Nav.payments', 'uri' => 'payments',     'icon' => 'cash-coin',        'permission' => 'payments.view'],
            ['label' => 'Nav.creditNotes', 'uri' => 'credit-notes', 'icon' => 'arrow-return-left', 'permission' => 'credit_notes.manage'],
            ['label' => 'Nav.reports', 'uri' => 'reports',      'icon' => 'bar-chart-line',    'permission' => 'reports.view'],
        ],
        'Nav.masterData' => [
            ['label' => 'Nav.customers', 'uri' => 'customers', 'icon' => 'building', 'permission' => 'customers.view'],
            ['label' => 'Nav.contacts', 'uri' => 'contacts', 'icon' => 'person-lines-fill', 'permission' => 'customers.view'],
            ['label' => 'Nav.salesReps', 'uri' => 'sales-reps', 'icon' => 'person-badge', 'permission' => 'salesreps.view'],
            ['label' => 'Nav.products', 'uri' => 'products',  'icon' => 'box-seam', 'permission' => 'products.view'],
        ],
        'Nav.administration' => [
            ['label' => 'Nav.settings', 'uri' => 'settings',  'icon' => 'gear',         'permission' => 'settings.manage'],
            ['label' => 'Nav.users', 'uri' => 'users',     'icon' => 'people',       'permission' => 'users.manage'],
            ['label' => 'Nav.auditLog', 'uri' => 'audit-log', 'icon' => 'journal-text', 'permission' => 'audit.view'],
        ],
    ];
}
