# Deployment — de'makmur Invoicing (Warung Sahabat)

Step-by-step installation on a production server, plus updates, backups / restore and the daily jobs.
After every installation or update run `php spark app:check` — it lists what is still missing (FAIL blocks go-live, WARN should be fixed).

## 1. Server requirements

| Item | Requirement |
|---|---|
| PHP | 8.2+ (developed on 8.6) with `intl`, `mbstring`, `bcmath`, `mysqli`, `gd`, `fileinfo`, `dom`, `json`, `phar` and `zlib` (backups); OPcache recommended |
| Database | MariaDB 10.6+ (InnoDB, utf8mb4) |
| Web server | Apache 2.4 with `mod_rewrite` (uses `public/.htaccess`), or Nginx + PHP-FPM |
| HTTPS | Required (login, session cookies) |
| Time zone | The app uses `Asia/Makassar` (WITA) for document dates and monthly numbers |

`php.ini` (production):

```ini
display_errors = Off
log_errors = On
upload_max_filesize = 12M      ; attachments up to 10 MB
post_max_size = 14M
memory_limit = 256M            ; PDF generation
max_execution_time = 60
date.timezone = Asia/Makassar
session.cookie_secure = 1
expose_php = Off
```

**PHP 8.6 note:** `app/Config/Events.php` contains a workaround for a CodeIgniter session warning on PHP 8.6 (still needed in CodeIgniter 4.7.6). On PHP 8.2–8.5 it does nothing.

## 2. Install the code

1. Copy the project to the server, e.g. `/var/www/md-invoice` (Linux) or `D:\apps\md-invoice` (Windows). Do **not** copy `.env`, `writable/logs/*`, `writable/session/*`, `writable/uploads/*`, `writable/backups/*` from a development machine.
2. Behind a reverse proxy: set `Config\App::$proxyIPs` to the proxy address / network with its client-IP header (default `10.0.0.0/8` → `X-Forwarded-For`), otherwise the audit log records the proxy IP and the public verify rate limit (20 / min per IP) treats all visitors as one.
3. Install dependencies without development tools (also after every update that changes `composer.lock`, e.g. 2026-11-23: setasign/fpdi + setasign/fpdf for the combined order report):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Create `.env` from the template and fill it in:
   ```bash
   cp env.production.example .env
   php spark key:generate
   ```
5. Permissions: the web server user (e.g. `www-data`) needs write access to `writable/` (cache, logs, session, uploads, backups) and nothing else. Run the scheduled jobs (§6) as the same user.
   ```bash
   chown -R www-data:www-data writable && chmod -R 775 writable
   ```

## 3. Database

As a database administrator:

```sql
CREATE DATABASE md_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'md_app'@'localhost' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON md_invoice.* TO 'md_app'@'localhost';
-- no TRIGGER, no SUPER: the app user must not be able to remove the protection triggers
```

Then, in the app folder:

```bash
php spark migrate --all          # app + Shield tables
php spark db:seed DatabaseSeeder # lookups, regions, number formats (safe to re-run)
```

**Protection triggers** (financial records cannot be deleted or changed, even by admins). The DBA runs:

```bash
php spark db:protection-sql > protection.sql      # never the --dev variant in production
mysql -u root -p md_invoice < protection.sql
php spark db:protection-sql --check                # should report all triggers installed
```

Re-run this after updates that add protected tables (the release notes / `app:check` will say so), and **after every restore of a backup** (a restore rebuilds the tables, which removes their triggers; §7).

The grants above are also what backups and restores need (SELECT to read, CREATE / DROP / INSERT to rebuild the tables); no extra privileges.

## 4. First admin and company data

```bash
php spark shield:user create -n admin -e admin@example.com
php spark shield:user addgroup -n admin -g app_admin
php spark shield:user removegroup -n admin -g sales   # new users also get the default group "sales"
```

Log in, then in **Settings**:
1. **Company**: Warung Sahabat name, NPWP, address, phone, logo, PKP status, UMKM certificate (if any), signatory, invoice notes, SPK clauses.
2. **Bank accounts**, then choose the default account in Company (printed on invoices).
3. **Document numbering**: check the formats (invoice, proposal, SKU, ...) **before the first document** is created.
4. **Lookups → Tax withholding rules**: confirm the rates and thresholds with the tax adviser.
5. **Users**: create the staff accounts (app_admin / management / admin / sales / finance / warehouse) and link sales users to a sales representative; assign schools to the reps.

## 5. Web server

Point the document root to the **`public/`** folder only (never the project root).

Apache virtual host:

```apache
<VirtualHost *:443>
    ServerName invoice.example.com
    DocumentRoot /var/www/md-invoice/public
    <Directory /var/www/md-invoice/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/invoice.crt
    SSLCertificateKeyFile /etc/ssl/private/invoice.key
</VirtualHost>
<VirtualHost *:80>
    ServerName invoice.example.com
    Redirect permanent / https://invoice.example.com/
</VirtualHost>
```

Nginx (PHP-FPM):

```nginx
server {
    listen 443 ssl;
    server_name invoice.example.com;
    root /var/www/md-invoice/public;
    index index.php;
    client_max_body_size 14m;
    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

Restart PHP-FPM / Apache after changing `php.ini` or enabling extensions (PDFs fail with "ext-gd not loaded" otherwise).

## 6. Scheduled jobs (daily)

Two daily jobs, run as the web server user (so both can write to `writable/`). Errors go to `writable/logs`.

| Time | Command | What it does |
|---|---|---|
| 00:05 | `php spark invoices:mark-overdue` | marks unpaid invoices past their due date as OVERDUE (idempotent) |
| 00:30 | `php spark backup:create --prune` | backup of the database and uploaded files, removes old scheduled backups (§7) |

- Linux cron (as the web server user):
  ```cron
  5 0 * * * cd /var/www/md-invoice && php spark invoices:mark-overdue >> writable/logs/cron.log 2>&1
  30 0 * * * cd /var/www/md-invoice && php spark backup:create --prune >> writable/logs/cron.log 2>&1
  ```
- Windows Task Scheduler: two daily tasks, program `php.exe`, "Start in" = the app folder, run whether the user is logged on or not: at 00:05 with arguments `spark invoices:mark-overdue`, at 00:30 with arguments `spark backup:create --prune`. Then a third task copies the backup folder off the server (§7).

## 7. Backups and restore

The audit log and financial records are append-only, so backups are the only way back after a lost server or a serious mistake.

**What a backup is.** One file `backup-YYYYMMDD-HHMMSS[-label].tar.gz` (plus a small `.json` description next to it) containing:
- `database.sql`: every table (app, users, migrations), read in a consistent snapshot, so users can keep working while it runs;
- `uploads/`: everything in `writable/uploads/` (company logo, product pictures, attachments);
- `manifest.json`: date, app version, row counts and a checksum.

Not included: `.env` (secrets) and the protection triggers (the DBA installs them, see §3). Backups contain personal and financial data: keep them as protected as the database.

**Where.** `writable/backups/` (outside `public/`; set `backup.path` in `.env` to use another folder, e.g. a second disk). Retention: scheduled backups are kept 30 days, and the newest 7 always stay (`backup.keepDays`, `backup.keepMin`). Manual, command-line and safety backups are never removed automatically; delete them in the app when no longer needed.

**Daily, automatically:** `php spark backup:create --prune` (scheduled in §6).

**Off-site copy (required):** a backup on the same disk does not survive a lost server. Copy the backup folder daily to another machine or cloud storage, e.g.:
```bash
rsync -a --delete /var/www/md-invoice/writable/backups/ backup-host:/backups/md-invoice/     # Linux
```
```bat
robocopy "C:\apps\md-invoice\writable\backups" "\\nas\backups\md-invoice" /MIR /R:2 /W:5   :: Windows (Task Scheduler)
```
Keep a copy of `.env` (at least `encryption.key` and the database password) in a password manager or safe, separately from the backups.

**Manual backups:**
- In the app: **Settings → Backups → Create backup now** (app_admin; list, download and delete are there too).
- On the server: `php spark backup:create --label before-update`, `php spark backup:list`.

**Restore** (replaces ALL data, database and uploaded files, with the backup):
1. In the app: **Settings → Backups → Restore** on the wanted backup, and type `RESTORE` to confirm (app_admin only, permission `backups.restore`). Or on the server: `php spark backup:restore <file>` and type the database name to confirm. A backup from another machine is first copied into the backup folder (the web upload limit is too small for backups).
2. The app first makes a **safety backup** of the current data (type "Before restore"). If anything goes wrong, restore that one with `php spark backup:restore <safety file>`.
3. Everybody is logged out. If the backup came from an older app version, the migrations run automatically; a backup from a newer version is refused (update the code first).
4. The restore rebuilds all tables, which **removes the protection triggers**: the DBA runs the protection SQL again (§3), then `php spark app:check`.
5. Smoke test: log in, open a few invoices and their PDFs, and check the latest payment.

A restore from the app runs in the web request; for a large database (or when the web server has a short request timeout) use the command line instead.

**Test a restore** once before going live and then every few months, on a separate machine (install the app as in §2–§3 with an empty database, copy a backup into `writable/backups/`, then run `php spark backup:restore <file>`).

**DBA alternative:** a server-level dump can be made in addition (it also contains the triggers):
```bash
mariadb-dump --single-transaction --routines --triggers md_invoice | gzip > /backup/md_invoice-$(date +%F).sql.gz
```
It does not contain `writable/uploads/`; back that folder up separately.

## 8. Go-live check

```bash
php spark app:check
```

Then create a first backup (**Settings → Backups**), check that the scheduled backup and the off-site copy run the next night, and do a manual smoke test: log in → create a proposal → approve → create and issue the invoice → open the invoice PDF and scan its QR code → record a payment → create a delivery note and its PDFs → upload an attachment → open Reports.

## 9. Updating

```bash
# optional: announce a short maintenance window (or stop the web server) while updating
# 1. back up the database and uploaded files (section 7)
php spark backup:create --label before-update
# 2. copy the new code (keep .env and writable/)
composer install --no-dev --optimize-autoloader
php spark migrate --all
php spark db:seed DatabaseSeeder           # new lookups / number formats (existing rows are kept)
php spark cache:clear
php spark app:check                        # a DBA re-runs the protection SQL if triggers are missing
```

## 10. Operations

- Logs: `writable/logs/log-YYYY-MM-DD.log` (errors are logged with context, never with passwords). Rotate or delete old files.
- Sessions: `writable/session/` (file handler); old files are cleaned up by PHP's session garbage collection.
- Users who leave: ban them in **Users** (never delete; their name stays on documents and in the audit log).
- Development tools must not be on the server: no `--dev` triggers, `CI_ENVIRONMENT = production`, no test database.
