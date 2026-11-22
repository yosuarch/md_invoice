# Deployment — de'makmur Invoicing (Warung Sahabat)

Step-by-step installation on a production server, plus updates, backups and the daily job.
After every installation or update run `php spark app:check` — it lists what is still missing (FAIL blocks go-live, WARN should be fixed).

## 1. Server requirements

| Item | Requirement |
|---|---|
| PHP | 8.2+ (developed on 8.6) with `intl`, `mbstring`, `bcmath`, `mysqli`, `gd`, `fileinfo`, `dom`, `json`; OPcache recommended |
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

1. Copy the project to the server, e.g. `/var/www/md-invoice` (Linux) or `D:\apps\md-invoice` (Windows). Do **not** copy `.env`, `writable/logs/*`, `writable/session/*`, `writable/uploads/*` from a development machine.
2. Install dependencies without development tools:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Create `.env` from the template and fill it in:
   ```bash
   cp env.production.example .env
   php spark key:generate
   ```
4. Permissions: the web server user (e.g. `www-data`) needs write access to `writable/` (cache, logs, session, uploads) and nothing else.
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

Re-run this after updates that add protected tables (the release notes / `app:check` will say so).

## 4. First admin and company data

```bash
php spark shield:user create -n admin -e admin@example.com
php spark shield:user addgroup -n admin -g admin
php spark shield:user removegroup -n admin -g sales   # new users also get the default group "sales"
```

Log in, then in **Settings**:
1. **Company**: Warung Sahabat name, NPWP, address, phone, logo, PKP status, UMKM certificate (if any), signatory, invoice notes, SPK clauses.
2. **Bank accounts**, then choose the default account in Company (printed on invoices).
3. **Document numbering**: check the formats (invoice, proposal, SKU, ...) **before the first document** is created.
4. **Lookups → Tax withholding rules**: confirm the rates and thresholds with the tax adviser.
5. **Users**: create the staff accounts (admin / sales / finance / warehouse) and link sales users to a sales representative; assign schools to the reps.

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

## 6. Scheduled job (daily)

Marks unpaid invoices past their due date as OVERDUE. Idempotent; errors go to `writable/logs`.

- Linux cron (as the web server user):
  ```cron
  5 0 * * * cd /var/www/md-invoice && php spark invoices:mark-overdue >> writable/logs/cron.log 2>&1
  ```
- Windows Task Scheduler: daily at 00:05, program `php.exe`, arguments `spark invoices:mark-overdue`, "Start in" = the app folder, run whether the user is logged on or not.

## 7. Backups

The audit log and financial records are append-only, so backups are the only way back after a lost server.

- **Database**, daily (keep at least 30 days, store a copy off the server):
  ```bash
  mysqldump --single-transaction --routines --triggers md_invoice | gzip > /backup/md_invoice-$(date +%F).sql.gz
  ```
- **Files**: `writable/uploads/` (company logo, attachments) and `.env` (keep the encryption key).
- Test a restore once on a separate machine.

## 8. Go-live check

```bash
php spark app:check
```

Then a manual smoke test: log in → create a proposal → approve → create and issue the invoice → open the invoice PDF and scan its QR code → record a payment → create a delivery note and its PDFs → upload an attachment → open Reports.

## 9. Updating

```bash
# optional: announce a short maintenance window (or stop the web server) while updating
# 1. back up the database and writable/uploads (section 7)
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
