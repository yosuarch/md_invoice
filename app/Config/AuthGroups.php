<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'sales';

    /**
     * --------------------------------------------------------------------
     * Groups (staff roles)
     * --------------------------------------------------------------------
     * Customers do not log in; every user is internal staff.
     * Public registration is disabled (Config\Auth::$allowRegistration).
     *
     * @var array<string, array<string, string>>
     */
    public array $groups = [
        'app_admin' => [
            'title'       => 'App Administrator',
            'description' => 'Developer / system owner: users, backups, calculation and numbering settings, audit log; full access as a fallback.',
        ],
        'management' => [
            'title'       => 'Management',
            'description' => 'Oversight: sees all records and reports; approves proposals, voids invoices, reverses payments, allows prices below minimum.',
        ],
        'admin' => [
            'title'       => 'Administrator',
            'description' => 'Full business administration (customers, products, proposals, invoices, payments, deliveries, company settings) without approvals.',
        ],
        'sales' => [
            'title'       => 'Sales',
            'description' => 'Proposals (quotations) for their own schools; views their invoices and delivery notes.',
        ],
        'finance' => [
            'title'       => 'Finance',
            'description' => 'Invoices, payments and credit notes.',
        ],
        'warehouse' => [
            'title'       => 'Warehouse',
            'description' => 'Delivery notes: create, ship, track, deliver and cancel (all schools).',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * Check with auth()->user()->can('invoices.manage') or the
     * 'permission:invoices.manage' route filter.
     */
    public array $permissions = [
        'settings.manage'     => 'Manage company profile, lookups and bank accounts',
        'settings.system'     => 'Manage calculation and document numbering settings',
        'backups.manage'      => 'Create, download and delete backups',
        'backups.restore'     => 'Restore a backup (replaces all data)',
        'users.manage'        => 'Create, edit, ban staff users and assign roles',
        'audit.view'          => 'View the audit log',
        'records.view_all'    => 'See all customers, proposals, invoices and deliveries (without it: only records related to the user)',
        'customers.view'      => 'View customers (without records.view_all: only the user\'s own schools)',
        'customers.manage'    => 'Create and edit customers, addresses and sales reps',
        'salesreps.view'      => 'View the sales representatives list',
        'contacts.manage'     => 'Add, edit and remove contact persons of the schools the user may see',
        'crm.view'            => 'See activities and follow-ups',
        'crm.manage'          => 'Log activities and plan follow-ups',
        'products.view'       => 'View products',
        'products.manage'     => 'Create and edit products and prices',
        'prices.override'     => 'Sell below a product\'s minimum price on proposals and invoices',
        'proposals.view'      => 'View proposals',
        'proposals.manage'    => 'Create, edit, submit and negotiate proposals',
        'proposals.approve'   => 'Set a proposal to STORE_APPROVE',
        'invoices.view'       => 'View invoices',
        'invoices.manage'     => 'Create draft invoices and issue them',
        'invoices.void'       => 'Void an issued invoice',
        'payments.view'       => 'View payments',
        'payments.record'     => 'Record payments against invoices and refunds to customers',
        'payments.reverse'    => 'Reverse a payment or refund (failed transfer, wrong entry); the record is kept',
        'credit_notes.manage' => 'Create credit notes',
        'deliveries.view'     => 'View delivery notes',
        'deliveries.manage'   => 'Create and update delivery notes',
        'reports.view'        => 'View finance reports (receivables aging, withholding reconciliation)',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     */
    public array $matrix = [
        // App Administrator (developer): everything, incl. the system-only tasks and approvals as a fallback.
        'app_admin' => [
            'settings.*', 'backups.*', 'users.*', 'audit.*', 'records.*', 'customers.*', 'salesreps.*', 'contacts.*', 'crm.*', 'products.*', 'prices.*', 'proposals.*',
            'invoices.*', 'payments.*', 'credit_notes.*', 'deliveries.*', 'reports.*',
        ],
        // Management: oversight and approvals, no data entry.
        'management' => [
            'records.view_all',
            'customers.view', 'salesreps.view', 'crm.view',
            'products.view',
            'proposals.view', 'proposals.approve',
            'invoices.view', 'invoices.void',
            'payments.view', 'payments.reverse',
            'prices.override',
            'deliveries.view',
            'reports.view',
        ],
        // Administrator: full business administration; no approvals and no system tasks
        // (users, backups, calculation / numbering settings, audit log stay with app_admin).
        'admin' => [
            'settings.manage', 'records.*', 'customers.*', 'salesreps.*', 'contacts.*', 'crm.*', 'products.*',
            'proposals.view', 'proposals.manage',
            'invoices.view', 'invoices.manage',
            'payments.view', 'payments.record',
            'credit_notes.*', 'deliveries.*', 'reports.*',
        ],
        // Sales: own schools only (view + contacts + activities; school data, addresses and reps stay with admin).
        'sales' => [
            'customers.view', 'contacts.manage', 'crm.view', 'crm.manage',
            'products.view',
            'proposals.view', 'proposals.manage',
            'invoices.view',
            'deliveries.view', // view only: delivery notes are managed by the warehouse group
        ],
        'finance' => [
            'records.view_all',
            'customers.view', 'salesreps.view', 'contacts.manage', 'crm.view', 'crm.manage',
            'products.view',
            'proposals.view',
            'invoices.view', 'invoices.manage',
            'payments.view', 'payments.record',
            'credit_notes.manage',
            'deliveries.view',
            'reports.view',
        ],
        // Warehouse: ships the goods of issued invoices for every school (no prices to change, no payments).
        'warehouse' => [
            'records.view_all',
            'products.view',
            'proposals.view',
            'invoices.view',
            'deliveries.view', 'deliveries.manage',
        ],
    ];
}
