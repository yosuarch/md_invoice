# Plan: modular permissions (Dolibarr style)

Status: proposal, not started · 2026-11-23

## Context
Permissions live only in code (`app/Config/AuthGroups.php`). Changing who may do what means a code change and a deploy (example: `prices.override` should go to Administrator). The names grew one at a time (`.manage`, `.record`, `.void`, the global `records.view_all`), so modules don't all offer the same choices.

Goal, like Dolibarr's user/group rights:
- every module has the same set of actions;
- an App Administrator ticks rights per group and per user on a screen;
- "only my records / all records" can be set per module;
- groups can be created on the screen.

Code keeps deciding **which** permissions exist and **what they protect**. The database decides **who** has them.

## Why it fits Shield without hacks
- `Authorizable::can()` reads `setting('AuthGroups.matrix')`, `addGroup()` checks `setting('AuthGroups.groups')`, and permission names come from `setting('AuthGroups.permissions')`. The installed `codeigniter4/settings` package stores overrides in its `settings` table, so a matrix saved there replaces the config file at runtime.
- User-level rights are built in (`$user->addPermission()` / `removePermission()`, table `auth_permissions_users`).
- Rights only ever add up, as in Dolibarr: a user's rights = their groups' rights + their own. There is no "deny".
- Route filters (`permission:x`), `can()`, `user_can()` and `Navigation.php` keep working unchanged; only the names change.

## Phases (each one can be shipped on its own)

### Phase 1: permission catalogue + new names (no new screen yet)
1. **Catalogue** `app/Config/Permissions.php` (new): modules, their actions, and flags. It is the single list from which `AuthGroups::$permissions` is built (in the `AuthGroups` constructor).
   Standard actions:

   | Action | Meaning |
   |---|---|
   | `read` | see the module (own records) |
   | `read_all` | see every record, not only your own |
   | `write` | create / edit |
   | `delete` | delete drafts / unused records |
   | `approve` | the module's approval step |
   | `export` | CSV export |

   Some modules add their own actions: `payments.reverse`, `products.price_override`, `backups.restore`, `settings.system`.
   Flags: `system` (only grantable to `app_admin`), `requires` (e.g. `write` needs `read`; the screen ticks it automatically).
2. **Rename map** (old → new). Applied in one sweep to `Routes.php`, services (`$can('…')` closures, `ProposalWorkflow` transitions), controllers, views (`user_can`), `Navigation.php`, `AccessScope` and the tests:

   | Old | New |
   |---|---|
   | `customers.view` / `customers.manage` | `customers.read` / `customers.write` (+ `customers.delete`) |
   | `salesreps.view` | `salesreps.read`; rep editing moves from `customers.manage` to `salesreps.write` |
   | `contacts.manage` | `contacts.write` (reading follows `customers.read`) |
   | `crm.view` / `crm.manage` | `crm.read` / `crm.write` |
   | `products.view` / `products.manage` | `products.read` / `products.write` (+ `products.delete`) |
   | `prices.override` | `products.price_override` |
   | `proposals.view` / `.manage` / `.approve` | `proposals.read` / `.write` (+ `.delete` for drafts) / `.approve` |
   | `invoices.view` / `.manage` / `.void` | `invoices.read` / `.write` (+ `.delete` for drafts) / `.void` |
   | `payments.view` / `.record` / `.reverse` | `payments.read` / `.write` / `.reverse` |
   | `credit_notes.manage` | `credit_notes.read` + `credit_notes.write` |
   | `deliveries.view` / `.manage` | `deliveries.read` / `.write` |
   | `reports.view` | `reports.read` + `reports.export` (CSV) |
   | `settings.manage` / `settings.system` | `settings.write` / `settings.system` |
   | `users.manage` | `users.write` (system) |
   | `audit.view` | `audit.read` (system) |
   | `backups.manage` / `.restore` | `backups.write` / `backups.restore` (system) |
   | `records.view_all` | `customers.read_all`, `proposals.read_all`, `invoices.read_all`, `deliveries.read_all` |

3. **Per-module "all records"** in `App\Services\AccessScope`. The single `$this->all` flag is replaced by one flag per entity: customers (contacts and activities follow), proposals, invoices (payments, credit notes, refunds and attachments follow), deliveries.
4. **Default matrix** (still in `AuthGroups::$matrix`), written so every group keeps **exactly** today's effective rights, apart from the intended fixes:
   - `products.price_override` is added to `admin`;
   - `reports.export` goes to everyone who has `reports.read`.
5. **Data migration** `2026-11-2x-…_RenamePermissions.php`: renames existing rows in `auth_permissions_users` (currently empty, but kept safe) using the same map. `down()` maps them back.
6. **Language:** new `app/Language/{en,id}/Permissions.php` with module names, action names and help texts (same keys in both locales).

### Phase 2: rights screen
1. **`App\Services\PermissionService`**:
   - `matrix()`: the effective matrix (DB setting, else defaults);
   - `saveGroup($group, $perms)`, `saveUser($userId, $perms)`;
   - `createGroup()` / `renameGroup()` / `deleteGroup()` (only custom groups, only when nobody is in them);
   - `resetDefaults()`.

   All changes are written with `setting()->set('AuthGroups.matrix' | 'AuthGroups.groups', …)` in a transaction, with an `AuditLogger` row (table_name `permissions`, old/new rights). `$permissions` is never stored in the DB (it always comes from the catalogue), so permissions that no longer exist in code disappear automatically.
2. **Guard rails** (enforced in the service, not only in the UI):
   - `app_admin` always has every permission and cannot be edited or deleted. Nobody can lock themselves out, and the existing "last active app_admin" rule stays.
   - `system` permissions can only be granted to `app_admin` (not to other groups or single users).
   - Built-in groups (`app_admin`, `management`, `admin`, `sales`, `finance`, `warehouse`) can be renamed (title only) but not deleted.
   - Unknown permission names are rejected (validated against the catalogue).
   - "Reset to defaults" restores the code matrix (confirm dialog).
3. **UI** (permission `users.write`, so app_admin only):
   - `Users → Groups`: list of groups with member count; create / rename / delete custom groups.
   - Group rights page: one row per module, one checkbox column per action (empty cells where an action does not exist), a "select all" per row, `requires` ticked automatically by a small page script (htmx-safe, no `document` listeners).
   - User edit page, new "Rights" card: rights from the user's groups shown ticked and greyed, plus checkboxes for extra rights for this user alone.
   - The same grid partial (`components/permission_grid`) is used on both pages.
   - Controllers: `UserGroups` (index / new / create / edit / update / delete) and `Users::permissions` (POST). Routes in the `users` group.
4. **`app:check`:** warn when the stored matrix holds permissions that no longer exist in the catalogue (they are ignored).

### Phase 3 (optional, later)
- A `delete` action wherever deletes exist today but sit under `write`.
- Approval for "below minimum price" by management (a request that management approves) instead of the editor needing `products.price_override`.

## Tests
- **Unit:**
  - the catalogue is valid (unique names, `requires` points to existing permissions, `system` flags);
  - the rename map covers every old name;
  - the default matrix only uses catalogue names.
- **Feature:**
  - **Equivalence:** for every built-in group, the old effective rights mapped through the rename map = the new effective rights (apart from the two intended additions).
  - `PermissionService`: saving a group changes `can()` straight away; user extra rights; `system` rights refused for non-app_admin; app_admin cannot be edited; deleting a group with members is refused; reset; audit rows written.
  - `AccessScope`: e.g. `proposals.read_all` without `invoices.read_all` shows all proposals but only your own invoices.
  - The existing feature tests updated to the new names.
- Run `composer test` on the dev machine (this server's DB user cannot reach `test_md_invoice`).

## Docs
- `.guidelines/architecture.md` (Authentication & Authorization: catalogue, action vocabulary, rights screen, guard rails)
- `.guidelines/database.md` (rule 13 rewritten as the default matrix table; `settings` table holds `AuthGroups.matrix` / `.groups`)
- `.guidelines/ui_ux_guidelines.md` (Groups and Rights screens)
- `.guidelines/roadmap.md`
- `CLAUDE.md` and `DEPLOYMENT.md` (permission names in the commands)
- copy the guidelines again into `.continue/rules/`

## Verification
1. `php spark migrate`, then `composer test`.
2. Log in as each built-in group: the menu, buttons and 403s are the same as before the refactor (checklist per group).
3. On the rights screen: give `proposals.approve` to `admin` → an Administrator sees "Approve". Remove it → the button is gone. The audit log shows both changes.
4. Try to give `backups.restore` to `management` → refused. Try to edit `app_admin` → read-only.
5. Reset to defaults → the matrix matches the code again.

## Effort and risk
- **Phase 1:** about 110 permission references plus `AccessScope`. Purely a rename, protected by the equivalence test.
- **Phase 2:** new service, two screens and a component.
- **Main risk:** rights become data, so a wrong tick takes effect immediately. It is mitigated by the guard rails, the audit log and "reset to defaults".
- **Backups** already include the `settings` table, so restoring a backup also restores the rights.
