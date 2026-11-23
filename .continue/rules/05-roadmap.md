---
name: Project Roadmap
alwaysApply: true
description: Current phase, upcoming work, open questions
---

# Project Roadmap

## Phase 1 - Foundation & Database Design (done)
- [x] Initialize CodeIgniter 4 project and MariaDB connection.
- [x] Shield authentication; groups `admin`, `sales`, `finance`; registration disabled.
- [x] Migrations for the full schema in `database.md`; lookup seeders.
- [x] Bootstrap 5 staff layout, permission-aware sidebar, placeholder dashboard.

## Phase 2 - Core Infrastructure & Master Data (done)
- [x] Base model with audit logging (`audit_log` via model events) + audit log viewer.
- [x] `DocumentSequence` service (transaction + `SELECT ... FOR UPDATE`; formats in `Config\Documents`).
- [x] Settings: company profile (with logo upload), bank accounts, lookup management (system statuses read-only).
- [x] User management: create staff, assign groups, deactivate/reactivate (self/last-admin protected).
- [x] Customers CRUD (contacts with one primary, addresses with one default per type).
- [x] Products CRUD.
- [x] Global CSRF protection; route permission filters per module.

## Phase 2b - School Procurement Context (done)
- [x] Customer ID is a UUIDv7.
- [x] School data on customers: NPSN, school level, public/private.
- [x] Sales representatives (no login required, optional link to a staff account) assigned to customers; "My customers" filter.
- [x] Funding sources (BOSP, APBD, ...) and goods/service type on products and item lines.
- [x] Company tax status (PKP, SK PP 55 UMKM certificate).
- [x] Data-driven tax withholding rules + `WithholdingCalculator` (PPN collection by public-school treasurers, PPh 22 except BOS funds, PPh 23 on services, UMKM 0.5% final).
- [x] All code in English.

## Phase 3 - Proposals (Quotations) + PDF foundation (done except PDF)
- [x] Proposal list (search, status/open filter, "My proposals"), form with item table (product picker copies title/unit/type/price/tax) and live totals; recent proposals on the customer page.
- [x] Funding source and sales rep on the proposal (defaults from the customer).
- [x] Status transitions with `proposal_status_history`; approval restricted to admin; editable only in DRAFT / UNDER_REVIEW.
- [x] PDF infrastructure: Dompdf, shared document layout (company header/logo, footer, page numbers), A4 portrait, download/preview routes, "scan to verify" QR + public verification page.
- [x] Proposal PDF (Surat Penawaran Harga) with payment terms, shipment and planned delivery date.
- [x] Surat Perintah Kerja (agreement) from an approved proposal: form + PDF, both signatories, clauses, meterai box.

## Phase 4 - Invoices, Payments & Credit Notes (done except PDFs)
- [x] Create invoice from STORE_APPROVE proposal (copy items); draft edit/delete; recent invoices on the customer page.
- [x] Issue (number, due date, expected withholding, lock), void.
- [x] **Invoice PDF** (main deliverable): totals, PPN, expected withholding and net amount, bank account, terms, standard notes.
- [x] Receipt (kwitansi) PDF per payment (cash + withheld taxes with NTPN), meterai box above Rp 5.000.000.
- [x] Record payments with withheld taxes and proof numbers (status update in one transaction); payments list.
- [x] Credit notes.
- [x] Scheduled `php spark invoices:mark-overdue` command for OVERDUE (still to be scheduled on the server).
- [x] Dashboard with real invoice/payment figures and unpaid invoices by due date.
- [x] Overpayment (confirmed, becomes a credit balance), refunds, reversal of failed/mistaken payments and refunds (records kept), cancellation after issue via credit note (CANCELLED), credit notes on paid invoices.
- [x] Tamper-protection DB triggers (`php spark db:protection-sql`, to be installed by a DBA).
- [x] Address province → regency/city → district (kecamatan) cascading dropdowns (Kepmendagri 2025 region data).

## Phase 5 - Delivery (done except PDFs)
- [x] Delivery notes with partial quantities and courier tracking; mark delivered (receiver, date); cancel with reason; delivery progress on the invoice.
- [x] Delivery tracking: preparing → shipped → delivered, tracking timeline with manual updates (location, note).
- [x] End-to-end integration: order tracker on proposal / invoice / delivery pages, linked documents, work queues (to invoice, to ship, open deliveries, refunds due) and dashboard order pipeline.
- [x] Delivery note (surat jalan) PDF and handover report (BAST) PDF.

## Phase 5b - Access, Negotiation & SPA (done)
- [x] Document numbering configurable in Settings (tokens, counter period, live example); product SKU generated (`ws` + yymm + 4 digits); BAST with its own number.
- [x] Record-level access (`AccessScope`): sales sees only their schools and related documents; no Master Data for sales except products; quick-add school on the proposal form.
- [x] Documents & files on proposal / invoice / delivery pages: all PDFs of the order + attachments (upload, view, remove).
- [x] Negotiation: customer request recorded, revisions per send with line comparison, "Save & send revision", revision on the PDF.
- [x] htmx single-page navigation, progress bar, toasts, live list filters, quick search.

## Phase 6 - Reports & Hardening (done)
- [x] Attachments (stored under `writable/uploads/attachments/`).
- [x] Reports (Finance → Reports, `reports.view`): receivables aging per school / per invoice (taxes pending vs. cash expected) and withholding reconciliation (expected vs. withheld, bukti potong / NTPN, states to follow up); CSV export for Excel.
- [x] PHPUnit tests (`composer test`): unit tests (totals, withholding rules, workflows, number formats, revision diff) and feature tests on the `test_md_invoice` database (invoice flow, payments, overpayment, reversal, void, credit notes, overdue, numbering, access scope, negotiation, reports).
- [x] Deployment: `DEPLOYMENT.md`, `env.production.example`, `php spark app:check` (installation check); protection triggers extended to proposal revisions and attachments.

## Next (proposals)
- [x] Delivery notes managed by a new `warehouse` group (and admin); sales views them only (2026-11-22).
- [x] Products: picture, variants (own SKU, price, picture), default description; per-line descriptions on proposals / invoices / credit notes and PDFs (2026-11-22).
- [x] Screen language toggle Indonesian / English (CodeIgniter language files, per-user choice, Indonesian default) (2026-11-22).
- [x] Names / titles / addresses stored lower case, shown upper case (2026-11-22).
- [x] Contacts shared by schools (roles, WhatsApp, left the school) and lean CRM: activities & follow-ups on school / proposal / invoice pages, Activities page, "My follow-ups" on the dashboard; sales opens their own schools (2026-11-22).
- [x] Dolibarr-style pricing: tax rate per line (default from the product, changeable on proposals / draft invoices), prices typed excl. or incl. tax, minimum price per product / variant with `prices.override` (2026-11-22).
- [x] Exact totals for prices typed incl. tax (Dolibarr "TTC") and Settings → Calculation (tax per line / per total, rounding mode, whole rupiah, unit price decimals) (2026-11-23).
- [x] Backup & restore: Settings → Backups (create, download, delete, restore with safety backup), `php spark backup:create --prune` daily job with retention, `backup:list` / `backup:restore`; DEPLOYMENT.md updated (off-site copy, restore procedure) (2026-11-23).
- [x] Staff groups split: `app_admin` (developer), `management` (approvals), `admin` (business administration without approvals) (2026-11-23).
- [ ] Go live: install on the server following `DEPLOYMENT.md` (DBA: protection triggers; daily overdue and backup jobs; off-site copy of the backups).
- [x] Combined order report: one PDF (cover + index + chosen documents and attachments) for finished orders; uploads limited to PDF and images (2026-11-23).
- [x] Combined report: continuous page numbers, lead times on the cover, order history appendix, report generation audited; Reports → Lead time (averages, CSV); short verification code under every QR + public code page with rate limit (2026-11-23).
- [ ] Internet exposure of the public pages only (`.plans/2026-11-23-internet-exposure.md`, after development).
- [ ] Credit note PDF; proforma / sales order if needed.
- [ ] Email documents to schools (needs email configuration).

## Open Questions
- Idea (needs its own plan): public e-commerce-style catalog as landing page where schools browse products and request a quotation, which then follows the proposal flow. Conflicts with the non-goal "customer self-service portal / customer login" in `intent.md`: decide guest requests vs. customer accounts first.
- PPN 12% with DPP 11/12: show "DPP nilai lain" separately on invoices, or the effective 11% only? (A PPN 12% rate can now be added in Settings → Tax rates and chosen per line.)
- Printed documents are Indonesian (decided 2026-11-22, after the store's own invoice sample). Lookup names printed on documents (funding sources, shipment methods) are English seeds; rename them in Settings → Lookups if Indonesian wording is wanted.
- Document set confirmed: proposal, SPK, invoice, kwitansi, surat jalan, BAST. Not yet: proforma / sales order, purchase order to suppliers (no supplier module), credit note PDF.
- Tax rules: confirm thresholds, BOS Regional (BOSDA) treatment and the PP 55/2022 amendments (PP 20/2026) with a tax adviser.
