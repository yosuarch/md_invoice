---
name: Database Specification
alwaysApply: true
description: Schema, tax withholding, conventions and business rules (source of truth)
---

# Database Specification — B2B Invoicing App

**Stack:** MariaDB (InnoDB, utf8mb4) + CodeIgniter 4 · **Currency:** IDR (Rupiah) · **Tax:** PPN (VAT) · **Customer tax ID:** NPWP

This is the source of truth for the schema. Migrations: `app/Database/Migrations/2026-11-22-1000xx_*`. Lookup data: `app/Database/Seeds/LookupSeeder.php`.
Changes made relative to the original draft are marked **(rev)**.

---

## 0. Global Conventions (apply to ALL tables)

- Engine `InnoDB`, charset `utf8mb4`, collation `utf8mb4_unicode_ci` (set in `Config\Database`).
- Primary key: `id INT UNSIGNED AUTO_INCREMENT`.
- Foreign keys: `<entity>_id INT UNSIGNED`, always the same type as the referenced `id`.
- Business tables have `created_at`, `updated_at`, `deleted_at` (DATETIME NULL, soft delete).
  **(rev)** Exceptions: append-only logs (`audit_log`, `proposal_status_history`) have only `created_at NOT NULL`; `document_sequence` has no `deleted_at`.
- CI4 Models: `$useTimestamps = true`, `$useSoftDeletes = true`, `$returnType = 'array'`, explicit `$allowedFields`.
- Money `DECIMAL(15,2)` · Quantity `DECIMAL(12,3)` · Tax rate `DECIMAL(5,2)` (percent, e.g. `11.00`). Never FLOAT.
- Lookup tables have a UNIQUE `code` (UPPER_SNAKE_CASE). Application logic uses `code`, never hardcoded ids.
- Schema via CI4 **Migrations** (use `App\Database\Support\BaseMigration` helpers); lookup data via **Seeders**.
- Foreign keys default to RESTRICT. Only item tables cascade from their parent (note: soft deletes never trigger cascades).

- **(rev) Names in lower case:** names, titles and addresses (school, contact, sales rep and user names, product / variant / proposal / line titles, addresses, cities, bank names, signatories, courier / receiver) are stored in lower case with spaces collapsed (`BaseModel::$lowercase` → `normalize_name()`) and shown in UPPER CASE (`up()`, inputs with `.text-uppercase`). Never for emails, usernames, codes / enum values (stay UPPER_SNAKE_CASE), SKUs and document numbers, NPSN / NPWP / phone, or paragraphs (notes, descriptions, terms, reasons). Migration 800003 converted existing rows.

### Users & roles — CodeIgniter Shield **(rev)**
The draft `users` / `roles` tables are replaced by Shield:
| Draft | Implementation |
|---|---|
| `users.username` | Shield `users.username` |
| `users.email`, `password_hash` | Shield `auth_identities` (never hash passwords manually) |
| `users.full_name` | Added column `users.full_name VARCHAR(128) NULL` (`App\Models\UserModel`) |
| — | **(rev)** `users.locale VARCHAR(5) NULL`: screen language chosen by the user (`id` / `en`; NULL = default `id`) |
| `users.is_active` | Shield ban: `$user->ban()` / `->unBan()` (`users.status = 'banned'`) |
| `users.last_login_at` | Shield `users.last_active` + `auth_logins` |
| `roles` (ADMIN, SALES, FINANCE) | Shield groups **(rev)** `app_admin`, `management`, `admin`, `sales`, `finance`, `warehouse` in `app/Config/AuthGroups.php` (admin split 2026-11-23, migration 2026-11-23-100001 added `app_admin` to every former admin) |
All `*_by` / `user_id` columns reference Shield `users.id` (INT UNSIGNED).

---

## 1. Entity Relationships

```
company_profile (single row) >── bank_account_list

customer_list ──< customer_contact_person >── contact_person   (**(rev)** shared people: link with role, job title, primary, ended_on)
customer_contact_person >── contact_role
customer_list ──< crm_activity >── contact_person / client_proposal_list / invoice / users   (**(rev)** activities & follow-ups)
customer_list ──< customer_address
customer_list >──< sales_representative      (via customer_sales_rep; rep >── users, optional)
customer_list >── school_level
customer_list ──< client_proposal_list ──< client_proposal_item >── product_list
customer_list ──< invoice ──< invoice_item >── product_list
client_proposal_list / invoice >── funding_source, sales_representative
client_proposal_list ──< invoice            (invoice created from approved proposal)
client_proposal_list ──< proposal_status_history
client_proposal_list ──< proposal_revision        (negotiation: snapshot of every version sent)
invoice ──< invoice_withholding >── tax_withholding_rule   (expected, at issue)
invoice ──< payment ──< payment_withholding               (actual, with proof; payments can be reversed)
invoice ──< refund                                        (money paid back from a credit balance)
invoice ──< delivery_note ──< delivery_note_item >── invoice_item
invoice ──< credit_note ──< credit_note_item

Lookups: payment_method, payment_terms, shipment, bank_account_list, unit_of_measurement,
         tax_rate, tax_withholding_rule, school_level, funding_source, contact_role,
         client_proposal_status, invoice_status
Support: document_numbering, document_sequence, audit_log, attachment, document_verification
Users:   Shield users (+ groups app_admin / management / admin / sales / finance / warehouse)
```

---

## 2. Settings

### `company_profile` — own business details printed on documents (single row)
| Column | Type | Null | Notes |
|---|---|---|---|
| company_name | VARCHAR(150) | NO | |
| tax_id | VARCHAR(32) | YES | NPWP |
| address | TEXT | YES | |
| phone | VARCHAR(32) | YES | |
| email | VARCHAR(255) | YES | |
| logo_path | VARCHAR(255) | YES | |
| default_bank_account_id | INT UNSIGNED | YES | FK → bank_account_list |
| is_pkp | TINYINT(1) | NO (1) | **(rev)** registered for VAT; if 0, no PPN is charged and no PPN collection rule applies |
| umkm_certificate_number | VARCHAR(64) | YES | **(rev)** SK PP 55/2022 (UMKM final income tax) |
| umkm_certificate_valid_until | DATE | YES | **(rev)** NULL = no expiry recorded |
| website / document_city | VARCHAR(255) / VARCHAR(100) | YES | **(rev)** printed on documents ("Minahasa Utara, 22 November 2026") |
| signatory_name / signatory_position | VARCHAR(128) / VARCHAR(64) | YES | **(rev)** default signatory of the store |
| invoice_terms / agreement_terms | TEXT | YES | **(rev)** standard invoice notes / SPK clauses (one per line) |
| calc_tax_mode | ENUM('LINE','TOTAL') | NO (LINE) | **(rev)** Settings → Calculation: PPN rounded per line, or once per tax rate on the document total (e-Faktur style) |
| calc_rounding | ENUM('HALF_UP','DOWN') | NO (HALF_UP) | **(rev)** half away from zero, or truncate |
| calc_amount_decimals | TINYINT | NO (2) | **(rev)** 2, or 0 = whole rupiah (line totals, discounts, tax, shipping, totals; columns stay DECIMAL(15,2)) |
| calc_unit_decimals | TINYINT | NO (2) | **(rev)** 2–6: decimals of the stored unit price excl. tax |

### `document_numbering` **(rev)** — number format per document type (Settings → Document numbering)
doc_type VARCHAR(16) UNIQUE (`QUO`, `SPK`, `INV`, `KW`, `CN`, `DO`, `BAST`, `SKU`) · pattern VARCHAR(64) · period ENUM('monthly','yearly','never') (when the counter restarts) · created_at/updated_at (audited, no soft delete).
Pattern tokens: `{Y}` year, `{y}` 2-digit year, `{m}` month, `{d}` day, `{seq:N}` counter with N digits (required, once). A monthly counter needs year + month in the pattern, a yearly one the year (otherwise numbers would repeat). Defaults in `Config\Documents::$sequences` (seeded by `NumberingSeeder`). Changing a format never renumbers old documents; a generated number that already exists is skipped.

---

## 3. Customers (schools and other organizations; they do not log in)

### `customer_list`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| cl_id | CHAR(36) | NO | | **(rev)** UNIQUE UUIDv7 (`App\Libraries\Uuid::v7()`), generated on create |
| cl_organization_name | VARCHAR(200) | NO | | indexed |
| npsn | CHAR(8) | YES | | **(rev)** UNIQUE national school ID |
| school_level_id | INT UNSIGNED | YES | | **(rev)** FK → school_level |
| school_status | ENUM('PUBLIC','PRIVATE') | YES | | **(rev)** public school (negeri: government treasurer) or private (swasta). NULL = not a school, treated as PRIVATE for tax rules |
| cl_tax_id | VARCHAR(32) | YES | | NPWP |
| cl_email | VARCHAR(255) | YES | | |
| cl_phone | VARCHAR(32) | YES | | |
| default_payment_term_id | INT UNSIGNED | YES | | FK → payment_terms |
| credit_limit | DECIMAL(15,2) | YES | | |
| is_active | TINYINT(1) | NO | 1 | |

### `contact_person` **(rev)** — a person, stored once (shared by schools)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| cp_first_name / cp_last_name | VARCHAR(64) | NO / YES | | lower case (shown upper case) |
| cp_phone | VARCHAR(32) | YES | | required by the form; `phone` rule |
| cp_whatsapp | TINYINT(1) | NO | 1 | phone reachable on WhatsApp (wa.me link) |
| cp_email | VARCHAR(255) | YES | | |
| cp_nip | VARCHAR(32) | YES | | civil servant number (prefills the SPK signatory) |
| cp_note | TEXT | YES | | internal note |
| is_active | TINYINT(1) | NO | 1 | |

### `customer_contact_person` **(rev: now the link person ↔ school)**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| customer_id | INT UNSIGNED | NO | | FK → customer_list |
| contact_id | INT UNSIGNED | NO | | **(rev)** FK → contact_person (the person's name/phone/email moved there, migration 900001) |
| role_id | INT UNSIGNED | YES | | **(rev)** FK → contact_role (HEADMASTER, TREASURER, OPERATOR, COMMITTEE, FOUNDATION, OTHER) |
| ccp_position | VARCHAR(64) | YES | | job title at this school (shown instead of the role) |
| is_primary | TINYINT(1) | NO | 0 | one primary among the current contacts of a school (enforced in `ContactService`) |
| ended_on | DATE | YES | | **(rev)** left the school (never primary; old documents keep the link) |

A person is linked at most once per school. Proposals reference the **link** (`client_proposal_list.contact_person_id`), so a document keeps the person and role of that time. A link used on proposals cannot be removed (set `ended_on`). Reads use `CustomerContactModel::withPerson()` (person fields as `ccp_first_name`, `ccp_last_name`, `ccp_phone_number`, `ccp_email` aliases).

### `crm_activity` **(rev)** — activities & follow-ups (lean CRM)
customer_id FK · contact_id FK → contact_person NULL · proposal_id / invoice_id FK NULL · activity_type ENUM('CALL','VISIT','WHATSAPP','EMAIL','MEETING','NOTE') · subject VARCHAR(200) · notes TEXT NULL · occurred_at DATETIME NULL (it happened) · due_on DATE NULL (follow-up) · done_at DATETIME NULL · outcome TEXT NULL · assigned_to / created_by FK → users · timestamps + soft delete (audited). Logged activity = `occurred_at` set (done at that time); open follow-up = `due_on` set and `done_at` empty. The person must be linked to the school, proposal / invoice must belong to it; an activity that happened cannot be in the future. Rules in `ActivityService`.

### `customer_address`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| customer_id | INT UNSIGNED | NO | | FK → customer_list |
| address_type | ENUM('BILLING','SHIPPING') | NO | | |
| address_line | TEXT | NO | | |
| district | VARCHAR(100) | YES | | **(rev)** kecamatan (level 3 region, migration 900002); desa / kelurahan stays in `address_line` |
| city / province | VARCHAR(100) | YES | | |
| postal_code | VARCHAR(10) | YES | | |
| is_default | TINYINT(1) | NO | 0 | one default per type (enforced in PHP) |

### `sales_representative` **(rev)** — like Dolibarr's sales representatives; no login required
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| rep_name | VARCHAR(128) | NO | | |
| rep_phone | VARCHAR(32) | YES | | |
| rep_email | VARCHAR(255) | YES | | |
| user_id | INT UNSIGNED | YES | | UNIQUE, FK → users (optional login account; enables "My customers") |
| is_active | TINYINT(1) | NO | 1 | |

### `customer_sales_rep` **(rev)** — pivot, hard deletes (no `deleted_at`)
customer_id FK (CASCADE) · sales_rep_id FK · UNIQUE (customer_id, sales_rep_id) · created_at/updated_at.

---

## 4. Lookup Tables (seeded)

| Table | Columns | Seed |
|---|---|---|
| `payment_method` | code(32) UNIQUE, name(64) | TRANSFER Bank Transfer · CASH Cash |
| `payment_terms` | code(32) UNIQUE, due_days SMALLINT UNSIGNED, description(255) NULL | ON_DELIVERY 0 · NET_7 7 · NET_14 14 · NET_30 30 |
| `shipment` | code(32) UNIQUE, name(64) | PICK_AT_STORE · PRIVATE · THIRD_PARTY |
| `bank_account_list` | bank_name(64), bank_account_number(32, text), bank_account_name(128), is_active=1 | — (company data, entered in Settings) |
| `unit_of_measurement` | code(16) UNIQUE, name(64) | PCS · BOX · KG · SET |
| `tax_rate` | code(16), rate DECIMAL(5,2), valid_from DATE, valid_to DATE NULL | PPN 11.00 · NON_PPN 0.00 (from 2022-04-01) |
| `client_proposal_status` | code(32) UNIQUE, cps_description(255) NULL, sort_order TINYINT UNSIGNED | DRAFT, PROPOSE, UNDER_REVIEW, STORE_APPROVE, CANCEL_BY_CLIENT, CANCEL_BY_STORE |
| `invoice_status` | code(32) UNIQUE, **description**(255) NULL **(rev)**, sort_order | DRAFT, ISSUED, PARTIALLY_PAID, PAID, OVERDUE, VOID, **(rev)** CANCELLED (fully credited after issue) |
| `region` **(rev)** | code(13) UNIQUE (Kepmendagri), parent_code(13) NULL, name(100), level TINYINT (1 province, 2 regency/city, **(rev)** 3 district / kecamatan) | `RegionSeeder` from `Seeds/data/regions.csv` (Kepmendagri 300.2.2-2138/2025 via cahyadsn/wilayah: 38 provinces, 514 regencies/cities, 7,265 districts); reference data, not audited. Addresses store the **names** in `province` / `city` / `district` (lower case); the form offers cascading dropdowns province → regency/city → district, validated as one chain and matched case-insensitively (`RegionModel::childCode()`). Printed as "…, kec. <district>, <city>, <province> <postal code>" |
| `contact_role` **(rev)** | code(32) UNIQUE, name(64), sort_order | HEADMASTER, TREASURER, OPERATOR, COMMITTEE, FOUNDATION, OTHER (screen labels via `Labels.contactRole_*`) |
| `school_level` **(rev)** | code(16) UNIQUE, name(64), sort_order | PAUD, TK, RA, SD, MI, SMP, MTS, SMA, MA, SMK, MAK, SLB, PKBM, OTHER |
| `funding_source` **(rev)** | code(32) UNIQUE, name(128), is_bos TINYINT, description(255) NULL, sort_order | BOSP, BOS_MADRASAH (is_bos=1) · BOS_REGIONAL, APBD, APBN, COMMITTEE, FOUNDATION, OTHER (is_bos=0) |
| `tax_withholding_rule` **(rev)** | see §8a | PPN_COLLECT_GOV, PPH22_GOV, PPH23_SERVICE (valid from 2025-01-01) |

- **(rev)** `tax_rate` is UNIQUE on `(code, valid_from)` instead of `code`, so a rate can change over time (new row, close the old one with `valid_to`). The current rate = row where `valid_from <= today` and (`valid_to` IS NULL or `>= today`).
- PPN: since 2025 the statutory rate is 12% with a DPP of 11/12 for most goods (effective 11%). Confirm the treatment with your accountant; if DPP must be printed separately, extend the tax model.

---

## 5. Products

### `product_list`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| pl_sku | VARCHAR(32) | NO | | UNIQUE **(rev: lowercase, was `pl_SKU`)**; **(rev)** generated on create from the `SKU` numbering (default `ws{y}{m}{seq:4}` = `ws26110001`, monthly counter), never edited |
| pl_title | VARCHAR(200) | NO | | indexed |
| pl_alias | VARCHAR(200) | YES | | |
| pl_description | TEXT | YES | | **(rev)** default line description (specification, brand, size); copied to new document lines |
| image_path | VARCHAR(255) | YES | | **(rev)** picture, relative to `writable/uploads/` (`products/<random>.<ext>`), streamed by `GET products/{id}/image` |
| pl_type | ENUM('GOODS','SERVICE') | NO | GOODS | **(rev)** PPh 22 applies to goods, PPh 23 to services |
| unit_id | INT UNSIGNED | NO | | FK → unit_of_measurement |
| pl_price | DECIMAL(15,2) | NO | 0.00 | current default selling price |
| pl_price_incl_tax | TINYINT(1) | NO | 0 | **(rev)** price base: `pl_price` is entered incl. tax (new lines start in "incl. tax" mode) |
| pl_min_price | DECIMAL(15,2) | YES | | **(rev)** minimum selling price excl. tax (per unit after discount); variants: `pv_min_price` (else the product's) |
| tax_rate_id | INT UNSIGNED | YES | | FK → tax_rate |
| is_active | TINYINT(1) | NO | 1 | |

### `product_variant` **(rev)** — named variants of a product (size, thickness, colour, ...)
product_id FK · pv_sku VARCHAR(40) UNIQUE (= `<product SKU>-NN`, NN counts every variant of the product, never reused or edited) · pv_name VARCHAR(128) (unique per product) · pv_price DECIMAL(15,2) · image_path VARCHAR(255) NULL · is_active · sort_order · timestamps + soft delete. Unit, type and tax come from the product. When a product has active variants, document lines must choose one (the picker lists the variants instead of the product; line title = "product - variant"). A variant used on documents cannot be deleted (set it inactive).

---

## 6. Item Lines (shared shape)

`client_proposal_item`, `invoice_item` and `credit_note_item` share these columns (values copied from the product):

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| product_id | INT UNSIGNED | NO | | FK → product_list |
| variant_id | INT UNSIGNED | YES | | **(rev)** FK → product_variant (price and SKU copied from the variant) |
| item_title | VARCHAR(200) | NO | | copied |
| item_description | TEXT | YES | | **(rev)** copied from `pl_description`, editable per line (special request / custom description); printed under the title on screens and PDFs; proposal → invoice → credit note |
| unit_code | VARCHAR(16) | NO | | copied |
| item_type | ENUM('GOODS','SERVICE') | NO | GOODS | **(rev)** copied from `pl_type`; drives PPh 22 vs PPh 23 |
| quantity *(proposal: `cpi_quantity`)* | DECIMAL(12,3) | NO | | |
| unit_price | DECIMAL(19,6) **(rev)** | NO | | excl. tax; copied, editable during negotiation; up to `calc_unit_decimals` decimals (a price derived from an incl. price keeps them, e.g. 171171.1712) |
| discount_amount | DECIMAL(15,2) | NO | 0.00 | per line |
| tax_rate | DECIMAL(5,2) | NO | 0.00 | percent; **(rev)** default from the product's tax code, **changeable per line** on proposals and draft invoices (Dolibarr style); 0 when the company is not PKP |
| tax_code | VARCHAR(16) | YES | | **(rev)** code of the chosen `tax_rate` row (NULL = no tax); the rate is resolved for the document date |
| price_base | ENUM('EXCL','INCL') | NO | EXCL | **(rev)** how the price was typed; `unit_price` / `discount_amount` are always stored excl. tax |
| unit_price_incl | DECIMAL(15,2) | YES | | **(rev)** the price typed incl. tax (INCL lines); drives the line amounts (Dolibarr "TTC", see §11.4); `unit_price` = price_incl × 100 / (100 + rate) rounded to the unit decimals |
| discount_incl | DECIMAL(15,2) | YES | | **(rev)** the discount typed incl. tax (INCL lines); `discount_amount` = its excl. tax amount |
| tax_amount | DECIMAL(15,2) | NO | 0.00 | **(rev)** = round(line_total × tax_rate / 100, 2) |
| line_total | DECIMAL(15,2) | NO | | round(qty × unit_price − discount, 2), before tax |

Parent FK (ON DELETE CASCADE): `proposal_id` → client_proposal_list · `invoice_id` → invoice · `credit_note_id` → credit_note.

---

## 7. Proposals (Quotations)

### `client_proposal_list`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| cpl_id | VARCHAR(32) | NO | | UNIQUE, from `document_sequence` (QUO) |
| customer_id | INT UNSIGNED | NO | | FK → customer_list |
| contact_person_id | INT UNSIGNED | YES | | FK → customer_contact_person |
| cpl_proposal_title | VARCHAR(200) | NO | | |
| status_id | INT UNSIGNED | NO | | FK → client_proposal_status |
| revision_no | SMALLINT UNSIGNED | NO | 0 | **(rev)** latest revision sent (0 = never sent); printed on the PDF from revision 2 |
| funding_source_id | INT UNSIGNED | YES | | **(rev)** FK → funding_source (school's budget: BOSP, APBD, ...) |
| sales_rep_id | INT UNSIGNED | YES | | **(rev)** FK → sales_representative |
| status_updated_by | INT UNSIGNED | YES | | FK → users |
| status_updated_at | DATETIME | YES | | |
| valid_until | DATE | YES | | quote expiry |
| subtotal / discount_total / tax_total / grand_total | DECIMAL(15,2) | NO | 0.00 | |
| cpl_note | TEXT | YES | | |
| created_by | INT UNSIGNED | YES | | FK → users |
| payment_term_id / shipment_id | INT UNSIGNED | YES | | **(rev)** FKs; printed on the proposal and copied to the invoice |
| delivery_date | DATE | YES | | **(rev)** planned delivery date |

### `agreement` **(rev)** — Surat Perintah Kerja (SPK) from an approved proposal
agreement_number VARCHAR(32) UNIQUE (SPK sequence) · proposal_id FK UNIQUE · agreement_date DATE · completion_date DATE NULL · client_signatory_name / _position / _nip · store_signatory_name / _position · terms TEXT (copied from Settings, editable) · created_by. Items and amounts come from the proposal.

### `proposal_status_history` (append-only)
proposal_id FK · from_status_id FK NULL · to_status_id FK · changed_by FK → users · note TEXT NULL · created_at DATETIME NOT NULL.

### `proposal_revision` **(rev)** — negotiation history (append-only, not audited)
proposal_id FK · revision_no SMALLINT UNSIGNED (UNIQUE with proposal_id) · status_code VARCHAR(32) (PROPOSE or STORE_APPROVE) · items LONGTEXT (JSON: line_id, product_id, title, unit, type, quantity, unit_price, discount, tax_rate, line_total) · subtotal / discount_total / tax_total / grand_total · valid_until DATE NULL · note TEXT NULL (what changed) · created_by FK NULL · created_at.
Written by `ProposalRevisions` in the status-change transaction: every send (→ PROPOSE) is a new revision; an approval stores the approved version only when it differs from the last revision.

---

## 8. Invoices, Payments, Credit Notes

### `invoice`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| invoice_number | VARCHAR(32) | NO | | UNIQUE, e.g. `INV/2026/11/0001`; **(rev)** drafts store a placeholder `DRAFT-<random>` (shown as "Draft #id") and get the real number when issued, so deleted drafts leave no gap |
| customer_id | INT UNSIGNED | NO | | FK → customer_list |
| proposal_id | INT UNSIGNED | YES | | FK → client_proposal_list |
| billing_address | TEXT | NO | | copied from the customer's default billing address when the draft is created; editable while DRAFT, required to issue |
| customer_tax_id | VARCHAR(32) | YES | | copied NPWP |
| issue_date | DATE | NO | | **(rev)** set to today when issued (drafts hold the creation date) |
| due_date | DATE | NO | | issue_date + payment_terms.due_days |
| payment_term_id | INT UNSIGNED | NO | | FK → payment_terms |
| payment_method_id / shipment_id / bank_account_id | INT UNSIGNED | YES | | FKs |
| status_id | INT UNSIGNED | NO | | FK → invoice_status |
| funding_source_id / sales_rep_id | INT UNSIGNED | YES | | **(rev)** as on proposals (copied, editable while DRAFT) |
| subtotal / discount_total / tax_total / shipping_cost / grand_total | DECIMAL(15,2) | NO | 0.00 | |
| withholding_total | DECIMAL(15,2) | NO | 0.00 | **(rev)** expected withholding/collection (Σ invoice_withholding) |
| amount_paid | DECIMAL(15,2) | NO | 0.00 | sum of payments (cash received) |
| withheld_total | DECIMAL(15,2) | NO | 0.00 | **(rev)** actually withheld (Σ payment_withholding) |
| credit_total | DECIMAL(15,2) | NO | 0.00 | **(rev)** sum of credit notes |
| refunded_total | DECIMAL(15,2) | NO | 0.00 | **(rev)** sum of refunds not reversed |
| notes | TEXT | YES | | |
| issued_at | DATETIME | YES | | set when status → ISSUED |
| void_reason | TEXT | YES | | **(rev)** why the invoice was voided |
| voided_at / voided_by | DATETIME / INT UNSIGNED | YES | | **(rev)** when and by whom (FK → users) |
| created_by | INT UNSIGNED | YES | | FK → users |

Indexes: `(customer_id)`, `(status_id, due_date)`, `(issue_date)`.

### `payment`
invoice_id FK · **(rev)** receipt_number VARCHAR(32) UNIQUE NULL (kwitansi, KW sequence) · payment_date DATE · amount DECIMAL(15,2) **CHECK (amount > 0)** · payment_method_id FK · bank_account_id FK NULL · reference_number VARCHAR(64) NULL · note TEXT NULL · recorded_by FK → users · **(rev)** reversed_at DATETIME NULL · reversed_by FK → users NULL · reversal_reason TEXT NULL (a failed or mistaken payment is reversed, never deleted).

### `refund` **(rev)** — money paid back to the customer
invoice_id FK · refund_date DATE · amount DECIMAL(15,2) **CHECK (amount > 0)** · payment_method_id FK · bank_account_id FK NULL (paid from) · reference_number VARCHAR(64) NULL · reason TEXT · recorded_by FK → users · reversed_at / reversed_by / reversal_reason (failed refund transfer).

### `credit_note`
credit_note_number VARCHAR(32) UNIQUE · invoice_id FK · issue_date DATE · **(rev)** reason_type ENUM('RETURN','PRICE_CORRECTION','CANCEL_BY_CLIENT','CANCEL_BY_STORE','OTHER') · reason TEXT · subtotal / tax_total / **(rev)** shipping_amount / grand_total DECIMAL(15,2) (grand_total = subtotal + tax_total + shipping_amount) · created_by FK → users.

### `credit_note_item`
Shared item columns (§6), plus **(rev)** `invoice_item_id` INT UNSIGNED NULL FK → invoice_item: the invoice line being credited (title, unit, type, tax rate copied from it).

---

## 8a. Tax Withholding by the Customer's Treasurer **(rev)**

School treasurers may collect PPN and withhold income tax, so the company receives less cash than the invoice total. Rules are **data**, editable in Settings → Tax Withholding Rules, and must be confirmed by a tax adviser.

### `tax_withholding_rule` — UNIQUE (code, valid_from); a regulation change = new row
| Column | Type | Notes |
|---|---|---|
| code / name | VARCHAR(32) / VARCHAR(128) | e.g. `PPH22_GOV` |
| tax_kind | ENUM('PPN','PPH22','PPH23') | |
| base | ENUM('DPP','PPN') | DPP = line_total (excl. PPN); PPN = the lines' tax_amount |
| item_type | ENUM('ALL','GOODS','SERVICE') | which lines count |
| customer_status | ENUM('ALL','PUBLIC','PRIVATE') | which customers withhold (non-school customers = PRIVATE) |
| rate | DECIMAL(5,2) | percent of base (PPN collection: 100) |
| umkm_rate | DECIMAL(5,2) NULL | used instead (kind PPH_FINAL) when the company has a valid SK PP 55 |
| threshold / threshold_basis | DECIMAL(15,2) / ENUM('DPP','GROSS') | applies only when the invoice amount is **above** the threshold |
| skip_for_bos | TINYINT | not applied when the funding source `is_bos` |
| requires_pkp | TINYINT | only when `company_profile.is_pkp` |
| regulation | VARCHAR(255) NULL | legal reference shown on documents |
| valid_from / valid_to / is_active | DATE / DATE NULL / TINYINT | |

Seeded rules (valid from 2025-01-01):
| Code | Who | What | Rate | Threshold | Notes |
|---|---|---|---|---|---|
| PPN_COLLECT_GOV | Public schools | all lines, base = PPN | 100% | > Rp 2,000,000 incl. PPN | VAT Law Art. 16A, PMK 81/2024; tax invoice code 02 |
| PPH22_GOV | Public schools | goods, base = DPP | 1.5% (UMKM 0.5%) | > Rp 2,000,000 DPP | skipped for BOS funds (PMK 81/2024 Art. 219) |
| PPH23_SERVICE | All | services, base = DPP | 2% (UMKM 0.5%) | none | Income Tax Law Art. 23, PMK 141/2015 |

### `invoice_withholding` — expected, computed when the invoice is issued (`App\Services\WithholdingCalculator`)
invoice_id FK (CASCADE) · rule_id FK NULL · tax_kind ENUM('PPN','PPH22','PPH23','PPH_FINAL') · description(255) · base_amount · rate · amount · regulation(255) NULL (copied).

### `payment_withholding` — actual, recorded with a payment
payment_id FK (CASCADE) · invoice_withholding_id FK NULL · tax_kind (same ENUM) · amount DECIMAL(15,2) **CHECK (> 0)** · document_number VARCHAR(64) NULL (bukti potong / NTPN) · document_date DATE NULL.

---

## 9. Delivery

### `delivery_note`
delivery_number VARCHAR(32) UNIQUE (DO sequence) · **(rev)** handover_number VARCHAR(32) UNIQUE NULL (BAST sequence, assigned with the note; older notes derive it from the DO number) · invoice_id FK · shipment_id FK · **(rev)** ship_date DATE · shipping_address TEXT (copied from the customer's default shipping address, else billing) · courier_name VARCHAR(64) NULL (required for THIRD_PARTY) · tracking_number VARCHAR(64) NULL · **(rev)** shipped_at DATETIME NULL · delivered_at DATETIME NULL · received_by_name VARCHAR(128) NULL · **(rev)** note TEXT NULL · created_by FK · cancelled_at / cancelled_by / cancel_reason.
Status is derived: PREPARING (created; `ship_date` is the planned date) → SHIPPED (`shipped_at`; ship date = actual) → DELIVERED (`delivered_at` + receiver), or CANCELLED (`cancelled_at`, reason). PREPARING and SHIPPED are "open": header editable, tracking updates, cancel. Delivery straight from PREPARING is allowed (pickup at store).

### `delivery_note_event` **(rev)** — append-only tracking timeline
delivery_note_id FK (CASCADE) · event_type ENUM('CREATED','SHIPPED','UPDATE','DELIVERED','CANCELLED') · event_at DATETIME · location VARCHAR(128) NULL · note TEXT NULL · created_by FK NULL · created_at. Written by `DeliveryService` for every step; staff add UPDATE events (e.g. courier hub, delay). Not audited (it is a log).

### `delivery_note_item`
delivery_note_id FK (CASCADE) · invoice_item_id FK · quantity DECIMAL(12,3). Goods lines only; partial deliveries allowed; per invoice line, the quantity on non-cancelled notes ≤ invoiced quantity (enforced in `DeliveryService`). Lines are fixed once created.

---

## 10. Support Tables

- **`document_sequence`**: doc_type VARCHAR(16) (`INV`, `QUO`, `CN`, `DO`, `BAST`, `SPK`, `KW`, `SKU`; formats in `document_numbering`; customers use UUIDs instead) · period VARCHAR(7) (`2026-11`, `2026` for yearly, `ALL` for never) · last_number INT UNSIGNED. UNIQUE `(doc_type, period)`. Read with `SELECT ... FOR UPDATE` inside the same transaction that inserts the document.
- **`audit_log`** (append-only, BIGINT id): user_id FK NULL · table_name · record_id · action ENUM(CREATE, UPDATE, DELETE) · old_values JSON · new_values JSON · ip_address VARCHAR(45) · created_at. Index `(table_name, record_id)`. **(rev)** Backups are logged with table_name `backup`, record_id 0 (CREATE = created, DELETE = deleted, UPDATE = downloaded / restored, with the file name).
- **`document_verification`** **(rev)**: doc_type VARCHAR(16) (QUO, SPK, INV, KW, DO, BAST) · doc_id · token CHAR(32) UNIQUE (random) · **(rev)** code CHAR(8) UNIQUE (Crockford Base32, printed under the QR as `K7Q2-M9XD`, typed on `GET verify`; migration 2026-11-23-100002 backfilled existing rows) · created_at. UNIQUE (doc_type, doc_id). Backs the public "scan to verify" page.
- **(rev)** `audit_log` rows with table_name `order_bundle` (record_id = proposal id, new_values: parts, pages) record every combined order report.
- **`attachment`**: entity_type (`proposal`, `invoice`, `payment`, `delivery_note`) · entity_id · file_name (cleaned client name) · file_path (relative to `writable/uploads/`: `attachments/Y/m/<random>.<ext>`) · mime_type (detected from the content) · uploaded_by FK. Index `(entity_type, entity_id)`. **(rev)** Allowed types and max size in `Config\Documents` (**(rev)** PDF and images only since 2026-11-23, so every attachment can go into the combined order report; older Word / Excel files stay downloadable; 10 MB, capped by php.ini). Visible to everyone who may see the document (rule 16); removal = soft delete (file kept).

---

## 11. Business Rules

1. **Flow:** Proposal (DRAFT → PROPOSE → UNDER_REVIEW → STORE_APPROVE) → Invoice (DRAFT → ISSUED) → Payment(s) → PAID. Cancel states: CANCEL_BY_CLIENT, CANCEL_BY_STORE.
2. Only a proposal with status `STORE_APPROVE` can become an invoice. Copy all items into `invoice_item`.
3. **Copied values:** item rows keep their own title, unit, price and tax rate. Never read current product prices to show an old document.
4. **Totals** are calculated in PHP on save and stored (`DocumentTotals`, mirrored in `document-items.js`). R() = rounding to the amount decimals with the rounding mode of Settings → Calculation (`CalcSettings`; default 2 decimals, half away from zero). Per line: price excl. tax: `line_total = R(qty × unit_price − discount)`, `tax_amount = R(line_total × tax_rate / 100)`; **(rev)** price incl. tax (Dolibarr "TTC", exact for what was typed): `total_incl = R(qty × unit_price_incl − discount_incl)`, `line_total = R(total_incl × 100 / (100 + rate))`, `tax_amount = total_incl − line_total` (150 × 190.000 incl. 11% = 25.675.675,68 + 2.824.324,32 = 28.500.000,00). **(rev)** Tax mode TOTAL: per (rate, price base) group the tax is computed once on the group total and spread over the lines (remainder to the largest line), so Σ line tax = tax_total. Header: `subtotal = Σ line_total`, `discount_total = Σ discount_amount`, `tax_total = Σ tax_amount`, `grand_total = subtotal + tax_total (+ R(shipping_cost) on invoices)`. Changing the settings affects new documents and drafts when saved; issued documents keep their stored amounts.
5. **Locking:** once an invoice is `ISSUED`, its items and totals cannot be edited. Corrections go through a `credit_note` or `VOID`. **(rev)** A DRAFT can be edited or (soft-)deleted; at most one non-void invoice per proposal.
5a. **(rev) Void** (`invoices.void`: management, app_admin; reason required): only for ISSUED / PARTIALLY_PAID / OVERDUE invoices with no payment, withholding or credit note recorded. The number stays used; the proposal can be invoiced again. The proposal approval can only be revoked while it has no non-void invoice.
6. **(rev) Payments** are accepted only for invoices in ISSUED, PARTIALLY_PAID or OVERDUE. In one transaction: insert payment (+ its `payment_withholding` rows), update `amount_paid` and `withheld_total`, then with `balance = grand_total − credit_total − amount_paid − withheld_total + refunded_total`:
   - `credit_total >= grand_total` → `CANCELLED` (fully credited)
   - `balance <= 0` → `PAID`
   - `amount_paid + withheld_total > 0` → `PARTIALLY_PAID` (or stays `OVERDUE` if past due)
   - **(rev)** The payment date may not be in the future or before the invoice date. Cash + withheld above the open balance is only accepted when the user confirms an **overpayment**; the excess becomes a credit balance (negative balance) to refund. Only the proof fields (`document_number`, `document_date`) of a `payment_withholding` may be completed later.
6a. **(rev) Reversal** (permission `payments.reverse`, reason required): a failed (bounced) or mistaken payment is marked reversed (`reversed_at/_by/_reason`), never deleted or edited; `amount_paid` / `withheld_total` are reduced and the status recomputed. Reversed payments and their withheld taxes are excluded from all sums. A payment cannot be reversed while refunds exist on the invoice (reverse the refunds first). A failed refund transfer is reversed the same way.
6b. **(rev) Refunds** pay back a credit balance (overpayment, or credit notes after payment). Refundable = min(−balance, amount_paid − refunded_total): only cash we received. Taxes withheld by the treasurer are not refunded by us; the treasurer corrects them manually at the Dinas Pendidikan / tax office (nota retur / nota pembatalan, PMK 81/2024), so that part of a credit balance stays as information. Refund date not in the future.
7. **(rev) Credit notes** update `invoice.credit_total` in the same transaction and re-run the status check in rule 6. Allowed for ISSUED / PARTIALLY_PAID / OVERDUE / PAID invoices (not VOID / CANCELLED), with a reason type. Per line: quantity ≤ invoiced quantity, unit price ≤ invoiced unit price, discount credited in proportion to the quantity, and all credits of a line together ≤ its `line_total`; shipping up to the shipping cost not yet credited. The total may not exceed `grand_total − credit_total`. If it exceeds what is still unpaid, the invoice gets a credit balance (rule 6b). **Cancellation after issue** = credit note for everything (reason CANCEL_BY_CLIENT / CANCEL_BY_STORE) → `CANCELLED`, then refund the cash paid. Expected withholding is not recalculated. Tax corrections (nota retur / nota pembatalan) are made manually outside the app.
7a. **(rev) Withholding:** when an invoice is issued, `WithholdingCalculator` evaluates the rules valid on the issue date (customer school_status, funding source is_bos, line item_type, thresholds, company PKP/UMKM status) and stores `invoice_withholding` + `invoice.withholding_total`. The invoice/PDF shows the expected net amount `grand_total − withholding_total`. What the treasurer actually withheld is recorded per payment with its proof document.
8. **Overdue:** a scheduled command `php spark invoices:mark-overdue` (run daily) sets `OVERDUE` where `due_date < today` and status is ISSUED or PARTIALLY_PAID. Lists and the dashboard also flag unpaid invoices past their due date before the command has run.
9. Every proposal status change inserts a row into `proposal_status_history`.
10. Document numbers come from `document_sequence`, never `MAX(id)+1`.
11. Soft delete only for business records. Issued invoices, payments, refunds, credit notes and delivery notes are never deleted (reverse / cancel them with a reason instead).
11a. **(rev) Tamper protection:** database triggers (`App\Database\Support\ProtectionTriggers`) reject DELETE and changes of amounts/numbers on audit_log, proposal_status_history, proposal_revision, payment, payment_withholding, refund, credit_note(+_item), invoice_withholding, issued invoices and their lines, delivery notes(+_item, +_event), attachment (no hard delete, file/document fixed), for every application user including admins. The app DB user should not have the TRIGGER privilege; a DBA installs them with the SQL from `php spark db:protection-sql`. The development variant (`--dev`) allows `SET @allow_test_purge = 1` to remove ZZ test data. **(rev)** Restoring a backup rebuilds all tables, which removes the triggers: the DBA installs them again afterwards (`app:check` warns).
14. **(rev) Deliveries:** delivery notes only for issued invoices (ISSUED / PARTIALLY_PAID / OVERDUE / PAID), goods lines only, rules in §9.
15. **(rev) Order flow:** proposal → invoice → payment → delivery are linked (`invoice.proposal_id`, `delivery_note.invoice_id`); `App\Services\OrderFlow` derives the order state (no extra table). Work queues: proposals `to_invoice` (approved, no non-void invoice), invoices `to_ship` (issued, goods not on a non-cancelled delivery note), deliveries `open`, invoices `refund_due`.
12. Every create/update/delete on business tables writes an `audit_log` row (CI4 Model events `afterInsert`, `afterUpdate`, `afterDelete`).
16. **(rev) Record-level access** (`App\Services\AccessScope`): users with `records.view_all` (app_admin, management, admin, finance, warehouse) see everything. Others (sales) see a proposal when they created it, it belongs to a school assigned to their sales rep, or they are its sales rep; an invoice when they created it, its school is theirs, its proposal is visible or they are its rep. Payments, credit notes, deliveries, PDFs and attachments follow their invoice/proposal. Hidden records answer 404. **(rev)** Sales opens their own schools (`customers.view`, scoped): details, contacts (`contacts.manage`) and activities (`crm.manage`); creating/editing schools, addresses and sales reps stays with admin (`customers.manage`, `salesreps.view` for the reps list). New schools come from the proposal form (quick-add, assigned to their rep). People and activities follow the schools (`AccessScope::contacts()` / `canContact()` / `activities()`). Setting a sales rep on a proposal or invoice assigns the school to that rep (`CustomerService::assignRep()`, never removed automatically), so whoever handles a school's documents can select the school for new proposals.
17. **(rev) Negotiation:** PROPOSE → UNDER_REVIEW needs the customer's request as a note ("Customer requests changes") and opens the editor; "Save & send revision" saves and moves back to PROPOSE as the next revision (`proposal_revision`).
18. **(rev) Backups** (`BackupService`, Settings → Backups, `php spark backup:*`): every table + `writable/uploads/` in one `.tar.gz`; `backups.manage` creates / downloads / deletes, `backups.restore` restores (app_admin only). A restore replaces all data, makes a safety backup first and logs everybody out; backups made by a newer app version are refused.
13. **Who may do what** is defined by Shield permissions (`app/Config/AuthGroups.php`). **(rev)** Groups: `app_admin` (developer: everything, incl. the system-only `users.manage`, `backups.*`, `settings.system` = calculation & numbering, `audit.view`), `management` (sees all records and reports; approvals only, no data entry), `admin` (Administrator: business administration incl. company settings / lookups, no approvals and no system tasks), `sales`, `finance`, `warehouse`. Only `management` (and `app_admin` as fallback) can approve proposals (`proposals.approve`), void invoices (`invoices.void`), **(rev)** reverse payments/refunds (`payments.reverse`) and sell below the minimum price (`prices.override`). Refunds are recorded with `payments.record` (admin, finance). **(rev)** Delivery notes are created, edited, shipped, delivered and cancelled with `deliveries.manage` (admin, warehouse); sales and finance only view them. **(rev)** Reports need `reports.view` (admin, finance). **(rev)** Selling below a product's minimum price needs `prices.override` (management, app_admin); only new or changed lines are checked. **(rev)** Contacts: `contacts.manage` (admin, finance, sales); activities: `crm.view` / `crm.manage` (admin, finance, sales). An activity is edited / deleted by its creator, its assignee or an admin / app_admin; anyone who sees the school may complete a follow-up.
