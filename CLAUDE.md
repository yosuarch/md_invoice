# de'makmur B2B Invoicing App

Internal back-office app for selling products and services to **Indonesian schools outside SIPLah**: quotations (proposals), invoices with PPN and treasurer withholding, payments, credit notes, delivery notes, and **PDF documents** (invoice is the main deliverable). IDR currency. Dolibarr is the functional reference, but keep everything simpler.
CodeIgniter 4.7 + Shield + MariaDB + Bootstrap 5.3, PHP 8.6. Claude Code is the main assistant on this project.
**All code is in English** (identifiers, enum values, lookup codes, comments, seeds); only Indonesian proper names/acronyms (NPSN, NPWP, PPN, PPh, BOS, PMK) stay. **Screen text** comes from `app/Language/{id,en}/` (Indonesian default, per-user toggle) — never hard-code UI text. Names/titles/addresses are stored lower case and shown with `up()`.

## Source of truth: `.guidelines/`
Follow these documents for every task. If a request conflicts with them, flag it before writing code.

@.guidelines/intent.md
@.guidelines/database.md
@.guidelines/architecture.md
@.guidelines/ui_ux_guidelines.md
@.guidelines/rules.md
@.guidelines/roadmap.md

When a guideline changes, re-copy it into `.continue/rules/01-*.md` … `06-*.md` (used by the Continue extension with a local Gemma model). Those files are verbatim copies with rule frontmatter added.

## Commands
- Dev server: `php spark serve` → http://localhost:8080/
- Migrations: `php spark migrate` / `migrate:rollback` / `migrate:status`
- Seed lookups (idempotent): `php spark db:seed DatabaseSeeder`
- Staff users: `php spark shield:user create -n <user> -e <email>`, then `shield:user addgroup -n <user> -g app_admin|management|admin|sales|finance|warehouse` (new users also get the default group `sales`; remove with `removegroup` if unwanted)
- Tests: `composer test` (PHPUnit 10; unit + feature tests on the separate `test_md_invoice` database, rebuilt from the migrations per test class; ~20 s)
- Installation check: `php spark app:check` (permanent command; production readiness, see `DEPLOYMENT.md`)
- Overdue invoices: `php spark invoices:mark-overdue` (permanent command; schedule it daily with Windows Task Scheduler / cron on the server)
- Backups: `php spark backup:create [--label x] [--prune]` (daily job with `--prune`), `backup:list`, `backup:restore <file>` (permanent commands; also Settings → Backups). Backups go to `writable/backups/` (git-ignored). Never restore on the working dev DB to test: restore into `test_md_invoice` (`new BackupService($config, $testConnection)` with temporary folders) instead.
- Protection triggers: `php spark db:protection-sql [--dev]` prints the SQL for a DBA; `--check` lists installed triggers (permanent command)
- Seeders include `RegionSeeder` (provinces, regencies/cities and kecamatan from `app/Database/Seeds/data/regions.csv`, upsert by code; run it after deploying migration 900002) and `NumberingSeeder` (default document number formats, insert-ignore)

## Environment
- Config in `.env` (development). `app.baseURL` is http://localhost:8080/ — every redirect goes there, so run the app on that address. DB: MySQLi, database `md_invoice` on localhost. Never print or commit `.env` contents.
- Shield tables were created with `utf8mb4_general_ci` (before the collation change); app tables use `utf8mb4_unicode_ci`. Joins between them are on integer ids, so this is harmless.
- `Config\Email` fromEmail/fromName are not set, so Shield magic-link login is disabled (`Config\Auth::$allowMagicLinkLogins = false`). Re-enable once email is configured.
- Branding: de'makmur logo colors as `--brand-*` tokens in `public/assets/css/app.css` (see UI/UX guidelines). Logo crops in `public/assets/img/` were generated from `logo-original.png` with a PowerShell/System.Drawing script (no GD extension installed).
- Headless screenshots: `msedge --headless=new --screenshot` works, but its minimum viewport is ~492px wide (narrower "mobile" shots look cut off), and pages opened via file:// can't load the icon font. Serve previews from the app origin.
- **PHP 8.6 workaround** in `app/Config/Events.php` (first `pre_system` handler): PHP 8.6 raises an E_WARNING for CI's session handlers (missing `create_sid()`/`validateId()`), which CI turns into a 500. The handler preloads those classes while ignoring only that warning. Remove when CodeIgniter fixes it (still present in 4.7.6).
- PDF: Dompdf 3 + chillerlan/php-qrcode (composer); merging for the combined order report with setasign/fpdi + setasign/fpdf (`App\Libraries\PdfMerge`, `App\Services\OrderBundle`). Labels are Indonesian, only in `app/Views/pdf/`; render partials with `pdf_part()`. Public route `verify/{token}` (outside the session filter).
- Image uploads are validated by content with `getimagesize()` (`App\Libraries\ImageUpload`, `Settings\Company::checkLogo`); this works regardless of the fileinfo/gd extensions. Product / variant pictures: `writable/uploads/products/`.
- Git repository (since 2026-11-23); commit only when asked. No Python available; use PHP/bash for scripts. Temporary spark commands go in `app/Commands/Tmp*.php` and must be deleted afterwards (`MarkOverdueInvoices.php`, `ProtectionSql.php`, `AppCheck.php` and `Backup*.php` are permanent).

## Code map
- Routes: `app/Config/Routes.php`. Everything behind the `session` filter; `/` → `Dashboard::index`.
- Clean URLs: `Config\App::$indexPage = ''` (no `index.php` in links/redirects). Works with `php spark serve` and Apache via `public/.htaccess`; a different web server needs an equivalent rewrite to `public/index.php`.
- Dev login for testing: the developer's admin account (credentials provided in chat; ask the user rather than storing them).
- Layouts: `app/Views/layouts/app_layout.php` (staff, `hx-boost` body swap; global scripts in `layouts/common/head.php`), `auth_layout.php` (Shield). Sidebar items + permissions: `app/Config/Navigation.php`. htmx 2.0.4 is vendored in `public/assets/vendor/htmx/`.
- Record-level access: `App\Services\AccessScope` + `BaseController::scope()` / `guardProposal()` / `guardInvoice()` / `guardDelivery()` / `guardCustomer()`. Sales (no `records.view_all`) sees only their schools and related documents, plus the people (`contacts()` / `canContact()`) and activities (`activities()`) of those schools.
- Helpers (autoloaded): `navigation_helper.php`, `format_helper.php` (`format_idr`, `format_qty`, `normalize_name`, `up`, `month_label`), `ui_helper.php` (form/badge helpers, `code_label`, `item_type_label`, `localize_lookup`), `locale_helper.php` (`app_locales`, `js_strings`), CI `form`.
- Language files: `app/Language/{en,id}/` — `App`, `Nav`, `Labels` (stored codes), `Messages` (services/controllers), `Fields` (model labels/validation, used by `BaseModel`), `Js`, `Lookups` (id only), one file per module. Keys must match in both locales (`ProductVariantsTest`).
- Migrations extend `App\Database\Support\BaseMigration` (column helpers: `idField`, `fk`, `money`, `quantity`, `rate`, `itemLineFields`, `timestamps`, `createStandardTable`).
- Shield user provider: `App\Models\UserModel` (adds `full_name`).
- Models/services/controllers conventions: see "Backend Structure" in `.guidelines/architecture.md` (BaseModel audit, Transaction, DocumentSequence, ValidationFailed, controller helpers, URL pattern).
- Reports: `App\Services\Reports` + `Reports` controller (receivables aging, withholding reconciliation, **lead time** via `LeadTime`, CSV), permission `reports.view` (app_admin, management, admin, finance).
- Modules built: `Customers` (+ `CustomerContacts` = person ↔ school links, `CustomerAddresses`), `Contacts` (people shared by schools), `Activities` (CRM: activities & follow-ups, `ActivityService`, `ActivityPanel`), `SalesReps`, `Proposals` (+ `Api\Lookup` JSON for pickers, regions and quick-add school; negotiation revisions via `ProposalRevisions`), `Invoices`, `Payments` (+ reversal), `Refunds`, `CreditNotes`, `Deliveries`, `Products` (SKU generated; picture, description, variants via `ProductService`; read-only product page), `Attachments`, `OrderBundles` (combined order report of a finished order), `Search` (top bar), `Settings\{Index,Company,BankAccounts,Lookups,Numbering,Calculation,Backups}` (lookup types incl. school levels, funding sources, tax withholding rules in `app/Config/Lookups.php`; field types text/number/date/textarea/select/checkbox), `Users`, `AuditLog`.
- Pricing / tax per line: `DocumentLines` + `LineTax` (tax select per line, prices excl./incl. tax, minimum price with `prices.override`); migration 900003.
- Calculation: `DocumentTotals` + `CalcSettings` (Settings → Calculation: tax per line / total, rounding, whole rupiah, unit price decimals; `window.AppCalc` for `document-items.js`, which must give identical results). Incl.-tax lines are computed from the incl. total (exact). `unit_price` is DECIMAL(19,6), `discount_incl` added; migration 900004.
- Backups: `BackupService` + `BackupSql` + `Config\Backup`, `Settings\Backups` (permissions `backups.manage` / `backups.restore`, app_admin), commands `Backup{Create,List,Restore}`; audit rows with table_name `backup`.
- Tax withholding: `App\Services\WithholdingCalculator` (pure) + `tax_withholding_rule` data; rules researched 2026-11 (PMK 81/2024 Art. 219: no PPh 22 on BOS-funded goods; public-school treasurers collect PPN, tax invoice code 02; PPh 23 2% on services; UMKM SK PP 55 → 0.5% final). Must be confirmed by a tax adviser.
- Company logo uploads: `writable/uploads/company/`, streamed by `GET settings/company/logo`. Attachments: `writable/uploads/attachments/Y/m/` (random names), streamed by `GET attachments/{id}`; PHP's `upload_max_filesize` (2M on the dev machine) caps the 10 MB app limit.

## Testing approach
- PHPUnit: `tests/unit/` (pure logic) and `tests/feature/` (extend `Tests\Support\AppTestCase`, real services on `test_md_invoice`). Add tests for new rules there.
- Logic checks: write a temporary spark command in `app/Commands/Tmp*.php`, run with `php spark tmp:... < /dev/null` (stdin must be closed), then delete it.
- HTTP checks: curl with a cookie jar against the running server; fetch the form page first to get the CSRF token (`csrf_test_name`, regenerated per request). Add `-m 20` timeouts.
- Mark test data with a `ZZ`/`zz` prefix and remove it (and its audit_log rows) afterwards; the DB is the developer's working database. Run `SET @allow_test_purge = 1` on the connection before deleting (needed once the dev protection triggers are installed).
- Delete order for cleanup: crm_activity, attachment (+ its file), delivery_note_event, delivery_note_item, delivery_note, payment_withholding, payment, refund, credit_note_item, credit_note, invoice_withholding, invoice_item, invoice, agreement, document_verification, proposal_revision, proposal_status_history, client_proposal_item, client_proposal_list, customer_address, customer_contact_person, contact_person, customer_sales_rep, product_list, customer_list; sales reps auto-created by `AccessScope::ensureRep()`; then reset `document_sequence` for the month (incl. SKU / BAST / SPK / KW).
- Browser checks: Edge headless with a temporary same-origin page in `public/zz-*.html` that logs in through an iframe and drives the app (`--dump-dom --virtual-time-budget`); delete the page and the temporary `--user-data-dir` profile afterwards (it holds a session cookie).

## Current state
- **This copy runs on the production server** (container behind an openresty reverse proxy, reachable on the LAN as https://invoice.yonako.site). Back up before migrations (`backup:create --label …`, run as `www-data`: `su -s /bin/sh www-data -c 'php spark …'` so files in `writable/` keep the web server owner). Feature tests cannot reach `test_md_invoice` here (run them on the dev machine); test writes go in a rolled-back transaction or as ZZ data removed afterwards. Headless screenshots: `chromium --headless=new --no-sandbox` (installed in the container 2026-11-23, gone after a rebuild). Redis: `.env` sends a password Redis does not have, so the app cache falls back to "dummy" (to fix in `.env` or Redis). `.env` still has `CI_ENVIRONMENT = development` (debug toolbar visible to users; switch to production). App CSS / JS links use `asset_url()` (mtime version) because the web server sends long Expires headers. Screenshots of staff pages: temporary `zz-screens` app_admin account + a same-origin `public/zz-login.html` that logs in and redirects (one Chromium run per page); delete the account (auth_logins by email, identities, groups, users row) and the page afterwards. Next: `.plans/2026-11-23-internet-exposure.md`, `.plans/2026-11-23-modular-permissions.md`.
- 2026-11-22: the dev database was emptied on request (all business data, audit log, counters; users, lookups, regions, company profile and bank account kept). Added product pictures / variants / line descriptions, the ID | EN language toggle and lower-case name storage (migrations 800001–800003).
- Phases 1–5 and 5b done (PDFs; configurable numbering + SKU; record-level access; documents & files with attachments; negotiation revisions; htmx SPA navigation, live filters, quick search). Phase 6 done (reports, PHPUnit unit + feature tests, `DEPLOYMENT.md` / `env.production.example` / `app:check`). Next: go live on the server; the DBA must (re)install the protection triggers (now incl. proposal_revision and attachment).
- Company naming: **Warung Sahabat** = the store and seller on every document (company profile); **de'makmur** = parent group (app branding, PDF footer). Source artwork in `.assets/` (samples + logos); the PDF logo fallback is `public/assets/img/store-logo.png` (cropped/cleaned from the source).
- PDFs need `gd` in the **web server** process: restart `php spark serve` after enabling extensions (the old dev server returned 500 "ext-gd not loaded").
- Dashboard is real (open proposals, unpaid / past-due invoices, received this month, order pipeline queues, unpaid invoices by due date).
- Taxes are handled manually (no tax API): the treasurer creates the e-billing at the Dinas Pendidikan office; the app only records NTPN / bukti potong numbers.
- Protection triggers are NOT installed in the dev DB yet: `app_user-1` has no TRIGGER privilege (by design). The user/DBA must run the SQL from `php spark db:protection-sql --dev` as root. Test cleanups already `SET @allow_test_purge = 1` so they keep working afterwards.
- App timezone is Asia/Makassar (WITA, set by the developer to match the server location; was UTC before 2026-11-22, so older dev rows have UTC timestamps).
- Groups (since 2026-11-23): `app_admin` = developer / system tasks + fallback, `management` = approvals, `admin` = Administrator (business, no approvals); see `.guidelines/database.md` rule 13. Migration 2026-11-23-100001 added `app_admin` to every former admin.
- Existing users: `yosua` (admin + app_admin), `SMPN1LIKSEL` (formerly `userClient`) and `userProvider` (sales), `deborah` (finance). Nobody is in `warehouse` yet (delivery notes are managed by admin until then).
- PHP `fileinfo` and `gd` are enabled (since 2026-11-22); a running `php spark serve` must be restarted to pick up php.ini changes.
