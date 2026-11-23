# Plan: exposing the public pages to the internet (staff app stays internal)

Status: proposal, to execute after development · 2026-11-23 · nothing in here has been changed yet

## Goal
- **Public (internet):**
  - the document verification pages: `GET verify`, `GET verify/{token}`;
  - later, the e-commerce style landing page / catalogue;
  - only the static assets these pages need (`assets/css`, `assets/vendor/bootstrap*`, `assets/img`, `favicon.ico`).
- **Internal only (LAN, or VPN for remote staff):** everything else: login, staff screens, APIs, PDFs, attachments, settings, backups.
- A mistake in one layer must not open the staff app. That is why there are two independent layers: the network / proxy, and the app itself.

## Current state (found 2026-11-23)
- **Containers and proxy:** the app runs in a container (Apache + PHP-FPM) behind an **openresty** reverse proxy on a container network (`10.0.0.0/8`). The proxy sends the visitor IP in `X-Forwarded-For`; since 2026-11-23 the app reads it (`Config\App::$proxyIPs`), for the audit log and the rate limit.
- **Public reachability: check first.** `invoice.yonako.site` has a public DNS name and a valid certificate, so the staff login may already be reachable from the internet. Test from a phone on mobile data (not Wi-Fi): `https://invoice.yonako.site/login`.
- **Redis:** it has **no password**, while `.env` sends one, so the app cache silently falls back to "dummy" (nothing cached; an error in the log on every request, ~870 on 2026-11-23). Sessions also use Redis. Decide: give Redis a password (preferred) or remove it from `.env`. Redis must never be reachable from outside the container network.
- **Already in place:** `forcehttps`, CSRF on every form, Shield sessions, record-level access, rate limit on `verify*` (20 / min / IP, file cache fallback), `Options -Indexes`, server tokens hidden, `X-Content-Type-Options` and `Referrer-Policy`.
- **Development mode on production:** `.env` has `CI_ENVIRONMENT = development`, so the CodeIgniter debug toolbar (bottom-right flame icon) and detailed error pages are shown to every user, including file paths, queries and session data. Switch to `production` **before anything is public** (and ideally now): first check `php spark app:check`, and remember `php spark` commands then also run in production mode.
- **Not active yet:** the `secureheaders` filter, CSP, HSTS, login throttling beyond Shield's defaults, 2FA.
- **Network delay:** 1 in ~3 TCP connections from the app container to the proxy takes 4 s to connect, so it is not the app. Worth checking (proxy upstream, IPv6 / hairpin NAT, Docker DNS).

## Step 1: separate public host on the proxy
1. New host name for the public part, e.g. `cek.<domain>` (verification) and later `www.<domain>` (catalogue). It points to the same app container.
2. openresty server block for the public host:
   - only `location = /verify`, `location ^~ /verify/`, the landing paths and the listed asset paths, proxied to the app;
   - `return 404` for everything else (no login page, no API);
   - `limit_req` zone (e.g. 30 r/min per IP, burst 10);
   - `proxy_set_header X-Forwarded-For $remote_addr` (**overwrite**, not append, so a visitor cannot spoof its IP);
   - security headers (step 4).
3. The staff host is **not** published to the internet:
   - either only an internal DNS name (split DNS), with no port forward;
   - or, if it must stay on the same public IP: `allow <LAN CIDR>; allow <VPN CIDR>; deny all;` in its server block.
4. `app.baseURL` stays the staff host. The verification URL in the QR code and the "atau buka …/verify" text on PDFs need the **public** host: add `Config\Documents::$publicBaseUrl` (env `documents.publicBaseUrl`), used by `DocumentVerification::url()` and `PrintDocuments` (`verifyPage`). Older PDFs carry the current host: keep that host answering `verify*` (or redirect it to the public host).

## Step 2: app-side defence in depth (`lanOnly` filter)
- New filter `App\Filters\LanOnly`: allows a request only when the client IP (after `proxyIPs`) is in `Config\Network::$internalRanges` (LAN + VPN CIDRs, from `.env`). Otherwise 404. Applied globally, `except` `verify`, `verify/*`, the landing routes and `assets/*`.
- `Config\App::$proxyIPs`: narrow from `10.0.0.0/8` to the proxy's real address, so a container on another network cannot fake `X-Forwarded-For`.
- Feature test: staff routes return 404 from an outside IP and work from a LAN IP; public routes work from both.

## Step 3: remote staff = VPN
- WireGuard on the router or a small VM: one peer per staff device, a dedicated VPN range (e.g. `10.99.0.0/24`) added to `internalRanges`.
- No staff page is ever published to the internet. Removing a peer = access gone.

## Step 4: hardening
- **HTTPS and headers:**
  - HTTPS only; HSTS on the public host (`max-age=31536000`, after testing);
  - enable `secureheaders` (X-Frame-Options DENY, etc.);
  - a CSP for the public pages (`default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'`; no inline scripts on those pages).
- **Cookies:** `Secure`, `HttpOnly`, `SameSite=Lax`; session cookie only on the staff host (no shared `cookie.domain`).
- **Login (Shield):**
  - throttling, and a lock after N failed attempts;
  - strong password rules (already on);
  - 2FA (Shield email / TOTP action) for `app_admin`, later for everyone, once email is configured.
- **App:**
  - `CI_ENVIRONMENT=production` (no toolbar, no detailed errors);
  - `php spark app:check` extended: `proxyIPs` set, `secureheaders` on, the `lanOnly` filter active, Redis reachable with auth, cache handler not "dummy".
- **Public verify page:** shows only what is printed on the document (already true). The 8-character code space (32⁸ ≈ 1.1 × 10¹²) plus the rate limit makes guessing useless. Log 404 bursts.
- **Landing page / catalogue (later):** read-only product data; any "request a quote" form gets a honeypot, a rate limit and a captcha. It creates a lead or draft, never a customer login (see the roadmap idea).

## Step 5: server and network
- Firewall: only 443 (and 80 → 443 redirect) public on the proxy; SSH only from the LAN / VPN with keys; MariaDB and Redis bound to the container network, never published.
- fail2ban (or the openresty `limit_req` log) for repeated 404s / 429s on the public host.
- Automatic security updates for the host and regularly rebuilt container images (PHP, Apache, openresty). Note: packages installed by hand inside the container (e.g. Chromium for screenshots, 2026-11-23) disappear when it is rebuilt; that is intended.
- Backups: the daily `backup:create --prune` + an **off-site, encrypted** copy; a restore drill into `test_md_invoice` every quarter.
- Monitoring: uptime check of the public verify page, disk space, backup age (`app:check` warns after 2 days), error-log alerts.

## Step 6: verification before go-live
1. From outside (mobile data): the public host shows the verify pages; `/login`, `/proposals`, `/api/*`, `/attachments/*`, `/settings` give 404. The staff host does not resolve or is refused.
2. External port scan (`nmap` from a VPS): only 443/80 open.
3. Spoofing test: `X-Forwarded-For: 10.0.0.5` from outside does not open staff routes (the proxy overwrites the header, `lanOnly` sees the real IP).
4. Basic penetration test against the OWASP ASVS level 1 checklist (headers, cookies, auth, rate limits, error pages).
5. Restore drill and a rollback plan (DNS / proxy config can be reverted in minutes).

## Open questions
- Public host names (verification and catalogue)?
- Who runs the proxy and the router (port forwards, split DNS)?
- VPN: router-based or a separate VM? How many remote staff?
- Should old printed QR codes (with the current host) keep working from the internet? If yes, the current host needs the same public / staff split as in step 1.
