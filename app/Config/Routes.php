<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Shield auth routes: /login, /logout, ... (registration and magic links are disabled in Config\Auth).
service('auth')->routes($routes);

// Language toggle (also on the login page): saved on the user's account / in the session.
$routes->post('locale', 'Locale::switch');

// Public "scan to verify" page for printed documents (random token, no login).
$routes->get('verify/(:segment)', 'Verify::show/$1');

/*
 * Everything below requires a logged-in staff user ('session' filter).
 * Each module adds a Shield permission filter: '<scope>.view' for pages, '<scope>.manage' for changes
 * (see Config\AuthGroups). Changes use POST (CSRF-protected) with explicit action URLs.
 */
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index', ['as' => 'dashboard']);

    // Company logo for documents: any logged-in user.
    $routes->get('settings/company/logo', 'Settings\Company::logo');

    // ---- Customers ----
    $routes->group('customers', ['filter' => 'permission:customers.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Customers::index');
        $routes->get('(:num)', 'Customers::show/$1');
    });
    $routes->group('customers', ['filter' => 'permission:customers.manage'], static function (RouteCollection $routes): void {
        $routes->get('new', 'Customers::new');
        $routes->post('/', 'Customers::create');
        $routes->get('(:num)/edit', 'Customers::edit/$1');
        $routes->post('(:num)', 'Customers::update/$1');
        $routes->post('(:num)/delete', 'Customers::delete/$1');


        $routes->get('(:num)/addresses/new', 'CustomerAddresses::new/$1');
        $routes->post('(:num)/addresses', 'CustomerAddresses::create/$1');
        $routes->get('(:num)/addresses/(:num)/edit', 'CustomerAddresses::edit/$1/$2');
        $routes->post('(:num)/addresses/(:num)', 'CustomerAddresses::update/$1/$2');
        $routes->post('(:num)/addresses/(:num)/delete', 'CustomerAddresses::delete/$1/$2');
    });

    // ---- Contacts (people, shared by schools) ----
    $routes->group('contacts', ['filter' => 'permission:customers.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Contacts::index');
        $routes->get('(:num)', 'Contacts::show/$1');
    });
    $routes->group('', ['filter' => 'permission:contacts.manage'], static function (RouteCollection $routes): void {
        $routes->get('contacts/(:num)/edit', 'Contacts::edit/$1');
        $routes->post('contacts/(:num)', 'Contacts::update/$1');
        $routes->get('api/contacts', 'Contacts::search');
        $routes->get('customers/(:num)/contacts/new', 'CustomerContacts::new/$1');
        $routes->post('customers/(:num)/contacts', 'CustomerContacts::create/$1');
        $routes->get('customers/(:num)/contacts/(:num)/edit', 'CustomerContacts::edit/$1/$2');
        $routes->post('customers/(:num)/contacts/(:num)', 'CustomerContacts::update/$1/$2');
        $routes->post('customers/(:num)/contacts/(:num)/delete', 'CustomerContacts::delete/$1/$2');
    });

    // ---- Activities & follow-ups (CRM) ----
    $routes->get('activities', 'Activities::index', ['filter' => 'permission:crm.view']);
    $routes->group('activities', ['filter' => 'permission:crm.manage'], static function (RouteCollection $routes): void {
        $routes->post('/', 'Activities::create');
        $routes->get('(:num)/edit', 'Activities::edit/$1');
        $routes->post('(:num)', 'Activities::update/$1');
        $routes->post('(:num)/complete', 'Activities::complete/$1');
        $routes->post('(:num)/delete', 'Activities::delete/$1');
    });

    // ---- Sales representatives ----
    $routes->get('sales-reps', 'SalesReps::index', ['filter' => 'permission:salesreps.view']);
    $routes->group('sales-reps', ['filter' => 'permission:customers.manage'], static function (RouteCollection $routes): void {
        $routes->get('new', 'SalesReps::new');
        $routes->post('/', 'SalesReps::create');
        $routes->get('(:num)/edit', 'SalesReps::edit/$1');
        $routes->post('(:num)', 'SalesReps::update/$1');
        $routes->post('(:num)/delete', 'SalesReps::delete/$1');
    });

    // ---- Products ----
    $routes->group('products', ['filter' => 'permission:products.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Products::index');
        $routes->get('(:num)', 'Products::show/$1');
        $routes->get('(:num)/image', 'Products::image/$1');
        $routes->get('variants/(:num)/image', 'Products::variantImage/$1');
    });
    $routes->group('products', ['filter' => 'permission:products.manage'], static function (RouteCollection $routes): void {
        $routes->get('new', 'Products::new');
        $routes->post('/', 'Products::create');
        $routes->get('(:num)/edit', 'Products::edit/$1');
        $routes->post('(:num)', 'Products::update/$1');
        $routes->post('(:num)/delete', 'Products::delete/$1');
        $routes->post('(:num)/variants', 'Products::createVariant/$1');
        $routes->post('(:num)/variants/(:num)', 'Products::updateVariant/$1/$2');
        $routes->post('(:num)/variants/(:num)/delete', 'Products::deleteVariant/$1/$2');
    });

    // ---- Proposals (quotations) ----
    // Status changes check proposals.manage / proposals.approve per transition (ProposalWorkflow).
    $routes->group('proposals', ['filter' => 'permission:proposals.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Proposals::index');
        $routes->get('(:num)', 'Proposals::show/$1');
        $routes->post('(:num)/status', 'Proposals::status/$1');
    });
    $routes->group('proposals', ['filter' => 'permission:proposals.manage'], static function (RouteCollection $routes): void {
        $routes->get('new', 'Proposals::new');
        $routes->post('/', 'Proposals::create');
        $routes->get('(:num)/edit', 'Proposals::edit/$1');
        $routes->post('(:num)', 'Proposals::update/$1');
    });

    // ---- Invoices ----
    // Created from approved proposals; editable/deletable only as DRAFT; issued invoices are locked.
    $routes->group('invoices', ['filter' => 'permission:invoices.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Invoices::index');
        $routes->get('(:num)', 'Invoices::show/$1');
    });
    $routes->group('', ['filter' => 'permission:invoices.manage'], static function (RouteCollection $routes): void {
        $routes->post('proposals/(:num)/invoice', 'Invoices::createFromProposal/$1');
        $routes->get('invoices/(:num)/edit', 'Invoices::edit/$1');
        $routes->post('invoices/(:num)', 'Invoices::update/$1');
        $routes->post('invoices/(:num)/issue', 'Invoices::issue/$1');
        $routes->post('invoices/(:num)/delete', 'Invoices::delete/$1');
    });
    $routes->post('invoices/(:num)/void', 'Invoices::void/$1', ['filter' => 'permission:invoices.void']);

    // ---- Payments (cash + withheld taxes) and refunds ----
    $routes->get('payments', 'Payments::index', ['filter' => 'permission:payments.view']);
    $routes->group('', ['filter' => 'permission:payments.record'], static function (RouteCollection $routes): void {
        $routes->get('invoices/(:num)/payments/new', 'Payments::new/$1');
        $routes->post('invoices/(:num)/payments', 'Payments::create/$1');
        $routes->post('payments/withholdings/(:num)', 'Payments::proof/$1');
        $routes->get('invoices/(:num)/refunds/new', 'Refunds::new/$1');
        $routes->post('invoices/(:num)/refunds', 'Refunds::create/$1');
    });
    // Reversal of failed / mistaken payments and refunds (records are kept, never deleted).
    $routes->group('', ['filter' => 'permission:payments.reverse'], static function (RouteCollection $routes): void {
        $routes->post('payments/(:num)/reverse', 'Payments::reverse/$1');
        $routes->post('refunds/(:num)/reverse', 'Refunds::reverse/$1');
    });

    // ---- Credit notes ----
    $routes->get('credit-notes/(:num)', 'CreditNotes::show/$1', ['filter' => 'permission:invoices.view']);
    $routes->group('', ['filter' => 'permission:credit_notes.manage'], static function (RouteCollection $routes): void {
        $routes->get('credit-notes', 'CreditNotes::index');
        $routes->get('invoices/(:num)/credit-notes/new', 'CreditNotes::new/$1');
        $routes->post('invoices/(:num)/credit-notes', 'CreditNotes::create/$1');
    });

    // ---- Delivery notes (surat jalan) ----
    $routes->group('deliveries', ['filter' => 'permission:deliveries.view'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Deliveries::index');
        $routes->get('(:num)', 'Deliveries::show/$1');
    });
    $routes->group('', ['filter' => 'permission:deliveries.manage'], static function (RouteCollection $routes): void {
        $routes->get('invoices/(:num)/deliveries/new', 'Deliveries::new/$1');
        $routes->post('invoices/(:num)/deliveries', 'Deliveries::create/$1');
        $routes->get('deliveries/(:num)/edit', 'Deliveries::edit/$1');
        $routes->post('deliveries/(:num)', 'Deliveries::update/$1');
        $routes->post('deliveries/(:num)/shipped', 'Deliveries::shipped/$1');
        $routes->post('deliveries/(:num)/track', 'Deliveries::track/$1');
        $routes->post('deliveries/(:num)/delivered', 'Deliveries::delivered/$1');
        $routes->post('deliveries/(:num)/cancel', 'Deliveries::cancel/$1');
    });

    // ---- PDF documents (inline; ?download=1 to download) ----
    $routes->get('proposals/(:num)/pdf', 'Documents::proposal/$1', ['filter' => 'permission:proposals.view']);
    $routes->get('proposals/(:num)/agreement/pdf', 'Documents::agreement/$1', ['filter' => 'permission:proposals.view']);
    $routes->get('invoices/(:num)/pdf', 'Documents::invoice/$1', ['filter' => 'permission:invoices.view']);
    $routes->get('payments/(:num)/receipt', 'Documents::receipt/$1', ['filter' => 'permission:invoices.view']);
    $routes->get('deliveries/(:num)/pdf', 'Documents::deliveryNote/$1', ['filter' => 'permission:deliveries.view']);
    $routes->get('deliveries/(:num)/handover', 'Documents::handover/$1', ['filter' => 'permission:deliveries.view']);

    // Finance reports (CSV with ?export=csv)
    $routes->group('reports', ['filter' => 'permission:reports.view'], static function (RouteCollection $routes): void {
        $routes->get('', 'Reports::index');
        $routes->get('receivables', 'Reports::receivables');
        $routes->get('withholding', 'Reports::withholding');
    });

    // Quick search (top bar); results limited per module permission and AccessScope
    $routes->get('search', 'Search::index');

    // Attachments of an order's documents (access checked per record in the controller)
    $routes->group('attachments', ['filter' => 'permission:proposals.view,invoices.view,deliveries.view'], static function (RouteCollection $routes): void {
        $routes->post('', 'Attachments::create');
        $routes->get('(:num)', 'Attachments::show/$1');
        $routes->post('(:num)/delete', 'Attachments::delete/$1');
    });

    // ---- Agreement (Surat Perintah Kerja) from an approved proposal ----
    $routes->get('proposals/(:num)/agreement', 'Agreements::edit/$1', ['filter' => 'permission:proposals.manage']);
    $routes->post('proposals/(:num)/agreement', 'Agreements::save/$1', ['filter' => 'permission:proposals.manage']);

    // ---- JSON lookups for document forms ----
    $routes->get('api/products', 'Api\Lookup::products', ['filter' => 'permission:products.view']);
    $routes->get('api/customers', 'Api\Lookup::customers', ['filter' => 'permission:customers.view,proposals.manage']);
    $routes->get('api/customers/(:num)', 'Api\Lookup::customer/$1', ['filter' => 'permission:customers.view,proposals.manage']);
    $routes->post('api/customers/quick', 'Api\Lookup::quickCustomer', ['filter' => 'permission:proposals.manage']);
    $routes->get('api/regions', 'Api\Lookup::regions'); // provinces / regencies for address dropdowns (any staff user)

    // ---- Settings (company profile, bank accounts, lookups) ----
    $routes->group('settings', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Settings\Index::index');

        $routes->get('company', 'Settings\Company::edit');
        $routes->post('company', 'Settings\Company::update');

        $routes->get('bank-accounts', 'Settings\BankAccounts::index');
        $routes->get('bank-accounts/new', 'Settings\BankAccounts::new');
        $routes->post('bank-accounts', 'Settings\BankAccounts::create');
        $routes->get('bank-accounts/(:num)/edit', 'Settings\BankAccounts::edit/$1');
        $routes->post('bank-accounts/(:num)', 'Settings\BankAccounts::update/$1');
        $routes->post('bank-accounts/(:num)/delete', 'Settings\BankAccounts::delete/$1');

        $routes->get('calculation', 'Settings\Calculation::edit');
        $routes->post('calculation', 'Settings\Calculation::update');

        $routes->get('numbering', 'Settings\Numbering::index');
        $routes->get('numbering/preview', 'Settings\Numbering::preview');
        $routes->get('numbering/(:segment)', 'Settings\Numbering::edit/$1');
        $routes->post('numbering/(:segment)', 'Settings\Numbering::update/$1');

        $routes->get('lookups/(:segment)', 'Settings\Lookups::index/$1');
        $routes->get('lookups/(:segment)/new', 'Settings\Lookups::new/$1');
        $routes->post('lookups/(:segment)', 'Settings\Lookups::create/$1');
        $routes->get('lookups/(:segment)/(:num)/edit', 'Settings\Lookups::edit/$1/$2');
        $routes->post('lookups/(:segment)/(:num)', 'Settings\Lookups::update/$1/$2');
        $routes->post('lookups/(:segment)/(:num)/delete', 'Settings\Lookups::delete/$1/$2');
    });

    // ---- Users ----
    $routes->group('users', ['filter' => 'permission:users.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Users::index');
        $routes->get('new', 'Users::new');
        $routes->post('/', 'Users::create');
        $routes->get('(:num)/edit', 'Users::edit/$1');
        $routes->post('(:num)', 'Users::update/$1');
        $routes->post('(:num)/deactivate', 'Users::deactivate/$1');
        $routes->post('(:num)/activate', 'Users::activate/$1');
    });

    // ---- Audit log ----
    $routes->get('audit-log', 'AuditLog::index', ['filter' => 'permission:audit.view']);
});
