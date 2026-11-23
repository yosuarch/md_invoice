# UI/UX & Layout Guidelines

## Design Philosophy
- **Consistency:** All pages share the same sidebar, top bar and footer.
- **Clarity:** Data-dense but readable. Primary actions (buttons) are prominent; destructive actions need confirmation.
- **Responsiveness:** Fully responsive with the Bootstrap 5 grid. The sidebar becomes an offcanvas menu below `lg`.
- **Audience:** Internal staff (Admin, Sales, Finance, Warehouse). Customers never use the app.
- **Single-page feel:** navigation and forms run through htmx (no full reloads), with a thin brand-gradient progress bar at the top; success messages appear as toasts (top right, auto-hide), errors stay as alerts on the page.

## Layout Architecture (CodeIgniter 4 View Layouts)

### 1. Layouts
- `layouts/app_layout.php`: the staff layout — white sidebar with the de'makmur logo, top bar (brand gradient line) with page title and profile dropdown.
- `layouts/auth_layout.php`: Shield login and related pages (set in `Config\Auth::$views['layout']`), full logo on a soft mint background.
- `layouts/common/`: shared partials — `head.php`, `scripts.php`, `footer.php`, `flash_messages.php`, `user_menu.php`, `sidebar.php`, `topbar.php`.
- Sidebar items live in `app/Config/Navigation.php`, grouped in sections (Sales, Finance, Master Data, Administration), each with a Shield `permission`. Items the user can't access are hidden.
- Bootstrap 5.3.3 and Bootstrap Icons are served locally from `public/assets/vendor/`. App styles: `public/assets/css/app.css`; app JS: `public/assets/js/app.js`.

### 2. View Structure
- Every view is a content file: `$this->extend('layouts/app_layout')`, filling `title` and `content` (optional `pageStyles` / `pageScripts`).
- Folder per module: `app/Views/<module>/index.php`, `form.php`, `show.php` (e.g. `invoices/show.php`).
- Reusable pieces in `app/Views/components/` (e.g. `stat_card.php`).

## Screens per Module
- **Lists** (customers, products, proposals, invoices, payments, delivery notes): table with search, status filter and pagination; status as badges; row click opens detail. Results update while typing / choosing a filter (live filter); the Filter button still works.
- **Quick search** in the top bar: numbers, titles, school names, NPSN across proposals, invoices, delivery notes and customers (only what the user may see); dropdown while typing, Enter opens the full result page, Esc closes.
- **Document forms** (proposal, invoice, credit note): header fields + editable item table (add product → copies title/unit/price/tax; qty, price, discount editable; **(rev)** per-line tax select and excl./incl. tax selector under the price with the converted price as hint and a red below-minimum warning) with live line and grand totals. Read-only once locked (e.g. invoice ISSUED).
- **Document detail:** printable layout (company profile header, customer, items, totals in IDR, bank account, terms), status history, and actions allowed for the current status and user permission.
- **Documents & files** card (`components/order_files`) on proposal, invoice and delivery pages: every PDF of the whole order (penawaran, SPK, invoices, kwitansi, surat jalan, BAST; view in a new tab or download) and all attachments of the order, with "Belongs to" + upload. Everyone related to the proposal sees the same card.
- **Negotiation:** on a sent proposal, "Customer requests changes" asks for the request and opens the editor; the editor shows the request and the last revision with "Save & send revision N" / "Save, send later". The proposal page's Negotiation card lists revisions (total change as a badge: green = lower, amber = higher) with line changes (Added / Removed / Changed old → new) and unsent changes highlighted.
- **New school from a proposal:** the "New school" button next to the customer search opens a quick-add modal (name, NPSN, level, status, phone, address, contact) and selects the new school.
- **Dashboard:** stat cards (open proposals, unpaid/overdue invoices, received this month), invoices due soon and **(rev)** "My follow-ups".
- **(rev) Contacts:** school page card (name → person page, role / job title, phone with WhatsApp link, email, primary / left badges; add = pick an existing person or enter a new one). Contacts list (Master Data) with each person's schools and roles; person page with schools and activities.
- **(rev) Activities & follow-ups:** card on school, proposal and invoice pages (timeline: open follow-ups first, overdue in red; "Log activity" with optional next follow-up, "Plan follow-up"; "Mark done" with outcome and optional next one). Activities page (Sales menu) with filters.
- **Reports** (Finance → Reports): overview cards; **Receivables aging** (bucket cards with a coloured left border: teal not due → yellow → orange → red → dark for > 90 days; tabs per school / per invoice; taxes pending vs. cash expected) and **Withholding reconciliation** (period, tax and state filters; state badges: green reconciled, amber proof missing, red amount differs, grey not withheld, blue unexpected). Both export CSV (opens in Excel with Indonesian settings).
- **Products:** list with picture thumbnail and variant count; product page (picture, description, variants; read-only for sales); edit page with picture upload / remove, description and the variants table (name, price, picture, order, active; each row saves on its own). Product picker on document forms shows thumbnails and lists variants. Every document line has a description box under the title (special request / custom description), filled from the product.
- **Settings:** company profile, bank accounts, lookups, document numbering (format with live example), **(rev)** calculation (tax per line / per total, rounding, whole rupiah, unit price decimals; live worked examples; saving reloads the page so every form uses the new settings), **(rev)** backups (admin: create with optional label, list with type badge / contents / size, download, delete; restore in a modal that explains the consequences and needs the word RESTORE; warning when the last backup is older than 2 days). **Users:** create staff, assign groups, ban/unban.

## Formatting
- Money: `format_idr($amount)` → `Rp 1.234.567,00`. Quantities: `format_qty()`. Dates: `d/m/Y` on screen and documents.
- **(rev) Language:** the staff screens are Indonesian by default and switch to English with the ID | EN toggle (top bar, login page; saved per user). Printed documents are always Indonesian.
- **(rev) Names** (schools, people, products, titles, addresses) are shown in UPPER CASE everywhere (stored lower case); paragraphs, emails and codes are shown as typed.

## Component Library (Bootstrap 5)
- **Buttons:** `.btn-primary` for the main action, `.btn-outline-*` for secondary, `.btn-danger` (with confirm modal) for void/delete.
- **Cards:** `.card` with `.card-header` and `.card-body`.
- **Tables:** `.table .table-hover .table-striped`, wrapped in `.table-responsive`; amounts right-aligned.
- **Modals:** quick actions (record payment, change status with note, confirm void).
- **Status badges:**
  - Proposal: DRAFT `bg-secondary`, PROPOSE `bg-primary`, UNDER_REVIEW `bg-warning text-dark`, STORE_APPROVE `bg-success`, CANCEL_* `bg-danger`.
  - Invoice: DRAFT `bg-secondary`, ISSUED `bg-primary`, PARTIALLY_PAID `bg-info text-dark`, PAID `bg-success`, OVERDUE `bg-danger`, VOID `bg-dark`, CANCELLED `bg-danger-subtle text-danger-emphasis` (outlined).
  - Delivery note: preparing `bg-secondary`, on the way `bg-primary`, delivered `bg-success`, cancelled `bg-dark`.
- **Order tracker** (`components/order_flow`): on proposal, invoice and delivery pages, a 4-step stepper (proposal → invoice → payment → delivery; green check = done, teal = in progress, red = cancelled) and the linked documents of the order; vertical below `md`.
- **Dashboard order pipeline:** work queues (proposals to invoice, invoices to ship, deliveries open, refunds due) as linked stat cards; a queue with 0 items is grey.
  - Reversed payment/refund: `Reversed` badge (danger-subtle), amount struck through; the record stays visible.
- **Nested dropdowns** (e.g. province → regency/city): `components/region_selects` + `cascade-select.js`; the server renders the current options so the form works without waiting for the API.

## Brand & Color Palette (de'makmur)
Colors are sampled from the company logo. Tokens live in `public/assets/css/app.css` (`--brand-*`); Bootstrap's primary/success/info, buttons, forms, pagination and dark tables are re-colored there. Use tokens or Bootstrap classes, never new hex values.

| Token | Hex | Use |
|---|---|---|
| `--brand-navy` | `#194350` | Headings, table headers, body accents (logo wordmark) |
| `--brand-teal-dark` | `#19545e` | **Primary**: buttons, links, active menu (white text contrast ≈ 8.5:1) |
| `--brand-teal-darker` | `#123f48` | Hover/active of primary |
| `--brand-teal` | `#4fab9b` | Accent, `info`, focus rings |
| `--brand-green` / `--brand-green-light` | `#86be7f` / `#99cb8a` | Highlights, active-menu marker, gradients (not for text on white) |
| `--brand-mint` | `#eaf5f0` | Hover surfaces |
| `--brand-surface` | `#f4f8f7` | Page background |
| `--brand-gradient` | teal-dark → teal → green | Top bar line, decorative only |

Semantic colors: success `#2e8b6a` (brand-tuned green), warning/danger keep Bootstrap defaults.

Logo files in `public/assets/img/`: `logo-original.png` (source, 1024²), `logo-full.png` (mark + wordmark, transparent), `logo-mark.png`, `logo-wordmark.png`, `favicon-64.png`; `public/favicon.ico` is built from the mark. Use `logo-full.png` on printed documents.
