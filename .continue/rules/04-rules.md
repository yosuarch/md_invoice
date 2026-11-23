---
name: Development Rules
alwaysApply: true
description: English-only code, coding standards, security, money handling, AI behavior
---

# Development Rules & Standards

## Coding Standards
- **Language:** PHP 8.6 (CodeIgniter 4 standards).
- **Code is written in English:** identifiers, DB enum values, lookup codes, comments, labels, validation messages and seed data (e.g. `PUBLIC`/`PRIVATE`, not `NEGERI`/`SWASTA`; `COMMITTEE`, not `KOMITE`). Only untranslatable Indonesian proper names/acronyms are kept: NPSN, NPWP, PPN, PPh, BOS/BOSP, PMK, PKP, UMKM, school level names (SD, MTs, ...).
- **(rev) UI text** comes from the language files (`app/Language/{id,en}/`, same keys in both); views, flash messages and exceptions use `lang()` / `code_label()`, never hard-coded text (see architecture.md "Screen language").
- **(rev) Names** are stored lower case (`BaseModel::$lowercase`) and displayed with `up()`; add new name/title/address columns to the model's `$lowercase` list.
- **Naming Convention:** 
    - Controllers: PascalCase
    - Methods: camelCase
    - Variables: camelCase
    - Database Tables and columns: snake_case (follow the exact names in `database.md`)
    - Lookup codes: UPPER_SNAKE_CASE; reference lookups by `code`, never by hardcoded id
- **Security:** 
    - Authentication and passwords are handled by Shield only (no manual `password_hash()`, no custom users table).
    - Every route requires the `session` filter; every action checks a Shield permission (`permission:` filter or `->can()`).
    - Record-level access: every list, detail page, PDF, API response and upload goes through `AccessScope` (sales only sees their own schools and related documents); hidden records return 404.
    - Use CSRF protection for all POST requests.
    - Validate all input with CI4 validation rules; escape all output with `esc()`.
    - Store uploads outside `public/` (`writable/uploads/`).

## Money & Business Data
- Money is `DECIMAL(15,2)`; never use float arithmetic for stored values. Round each line to 2 decimals, then sum (see `database.md` §11).
- Item lines copy product title, unit, price and tax rate; never recompute old documents from current product data.
- Issued invoices and payments are never edited or deleted; use credit notes or VOID.
- Operations touching several tables (issue invoice, record payment, credit note, document numbering) run in one DB transaction.
- Document numbers only come from `document_sequence`.
- Business records are soft-deleted only, and every create/update/delete writes `audit_log`.

## AI Behavior Instructions
- **Analysis First:** Before writing code, explain the logic in a brief "Thought" block.
- **Modular Code:** Break down large features into small, testable functions.
- **Error Handling:** Always include try-catch blocks and return meaningful error messages.
- **Documentation:** Every method must have a docblock explaining parameters and return types.
- **Self-Correction:** If a command fails, analyze the error log and propose a fix before asking the user.

## Testing
- Run `composer test` before finishing a change; add a unit test (`tests/unit/`) for pure logic and a feature test (`tests/feature/`, `AppTestCase`) for service flows that touch the database.
- Tests never use the working database (`test_md_invoice` only); every migration needs a working `down()`.

## File Management
- Do not overwrite existing files unless explicitly asked.
- Create new files for new features.
- Keep logic out of the Views (Thin Views, Fat Models/Controllers).
- Frontend: the staff layout navigates with htmx (body swap). Page scripts must be safe to run again after each navigation and must not add `document` listeners; shared code goes into the global scripts in `layouts/common/head.php` (see architecture.md).