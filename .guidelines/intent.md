# Project Intent: de'makmur B2B Invoicing

## Vision
An internal back-office app for de'makmur to manage the sale of **products and services to schools in Indonesia** for transactions that do **not** go through SIPLah (the government school procurement marketplace). It covers quotations (proposals), invoices with PPN, the taxes a school treasurer withholds, payments, credit notes and delivery notes, and produces the **PDF documents** schools need for their accountability reports (SPJ).

## Reference: Dolibarr, but simpler
Dolibarr ERP is the functional role model (third parties with sales representatives, proposals, invoices, payments, shipments, PDF document models). It is deliberately **not** used because it is too complex for the employees. Every screen should do one job with as few fields and clicks as possible; advanced configuration stays in Settings.

## Customers
Mostly schools (SD, SMP, SMA, SMK, madrasahs, ...), identified by **NPSN**, school level and **public/private** status. Public schools have government treasurers who collect PPN and may withhold income tax; the **funding source** of a purchase (BOSP, APBD, committee funds, ...) changes which taxes apply. Customers do not log in.

## Users (staff only)
- **App Administrator** (developer): users, backups, calculation and numbering settings, audit log; full access as a fallback.
- **Management:** sees everything and the reports; approves proposals, voids invoices, reverses payments, allows prices below minimum. No data entry.
- **Administrator:** full business administration (company settings, tax rules, lookups, customers, products, proposals, invoices, payments, deliveries) without approvals.
- **Sales:** proposals (quotations) for their own schools; keeps their contacts, calls, visits and follow-ups (lean CRM); views their invoices and delivery notes.
- **Finance:** invoices, payments (including withheld taxes) and credit notes.
- **Warehouse:** delivery notes (create, ship, track, deliver, cancel) for all schools.
- **Sales representatives** are linked to customers and documents; they do not need a login account.

## Relationship management
Schools are worked by relationships: the head of school, the BOS treasurer and the operator change over the years and sometimes work for several schools. People are stored once and linked to schools with a role; staff log calls, visits and WhatsApp chats and plan follow-ups, so nobody forgets to chase a quotation or a payment.

## Core Workflow
1. **Master data:** company profile (NPWP, PKP status, UMKM certificate, bank accounts), customers (schools, contacts, billing/shipping addresses, sales reps), products (goods or services, unit, price, tax).
2. **Proposal:** Sales drafts a quotation for a school with its funding source → PROPOSE → UNDER_REVIEW (negotiation) → STORE_APPROVE (Admin), or cancelled. Every status change is logged. Printable as PDF.
3. **Invoice:** created from an approved proposal (items copied), issued with a document number and due date; expected treasurer withholding is calculated from the tax rules; locked once issued. Printable as PDF.
4. **Payment:** Finance records cash received plus the taxes the treasurer withheld (with bukti potong / NTPN); the invoice moves to PARTIALLY_PAID / PAID automatically. Unpaid invoices past their due date become OVERDUE.
5. **Credit note / Void:** the only ways to correct an issued invoice.
6. **Delivery:** delivery notes with partial quantities; handover documents for the school.

## Language
The staff screens are in Indonesian by default; each user can switch to English. Printed documents are always Indonesian. Code, data and lookup codes stay English.

## Documents (PDF)
Generated from the stored document data with the store's logo and profile (**Warung Sahabat**, part of the **de'makmur** group): proposal (Surat Penawaran Harga), **Surat Perintah Kerja** (agreement when the school uses the approved quotation as a work order), **invoice** (the main deliverable), receipt (kwitansi), delivery note (surat jalan) and handover report (BAST). Printed in Indonesian, each with a "scan to verify" QR code.

## Success Criteria
- An approved proposal becomes an invoice without retyping items.
- Old documents always show the prices and taxes that applied when they were created.
- Expected withholding follows the configured rules; invoice balances stay consistent with payments, withheld taxes and credit notes.
- Document numbers are sequential per type and month without gaps.
- Every change to business data is traceable in the audit log.
- Employees can use it without training beyond a short walkthrough.

## Non-Goals (for now)
- SIPLah transactions (handled by SIPLah itself).
- Customer self-service portal / customer login.
- Payment gateway integration.
- Tax system integration (e-Faktur / Coretax / tax APIs). Taxes are handled manually: the school treasurer creates the e-billing and pays withheld taxes at the Dinas Pendidikan office; the app only records the resulting numbers (NTPN, bukti potong) and expected amounts.
- Multi-company or multi-currency.
