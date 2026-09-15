# Alasne Production Deployment Checklist

This document is the deployment baseline for moving Alasne from the local XAMPP environment to a public production host without changing the application architecture.

## 1. Runtime and web server

Use PHP 8.1 or newer. The current development workstation runs PHP 8.2. Required Composer platform extensions are PDO, PDO MySQL, mbstring, OpenSSL, and JSON. Recommended extensions are fileinfo, GD with WebP support, cURL, and ZIP.

For Apache, enable SSL/TLS, `mod_rewrite`, and `mod_headers`. Compression may be provided by Apache, a reverse proxy, CDN, or hosting layer. Point the virtual-host/document root to the repository's `public/` directory, never the repository root.

Production install commands:

```bash
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
php alasne migrate
```

Keep `composer.lock` committed so dependency versions remain repeatable.

## 2. Production environment

Do not copy local secrets into source control. Configure production environment values in the hosting platform or in a protected `.env` outside public web access.

Minimum production application settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain.example
APP_TIMEZONE=America/New_York
APP_KEY=replace-with-a-long-random-secret
SESSION_NAME=ALASNESESSID
SESSION_SAMESITE=Lax
```

`APP_KEY` should be a long random secret and must never be committed.

### HTTPS and session cookies

Alasne marks session cookies Secure automatically when PHP sees an HTTPS request. You may also explicitly configure:

```dotenv
SESSION_SECURE=true
```

Use HTTPS for every customer and Mission Control request. Verify the production `Set-Cookie` header contains `Secure`, `HttpOnly`, and `SameSite=Lax`.

### Reverse proxies and load balancers

Default:

```dotenv
TRUST_PROXY_HEADERS=false
```

Set `TRUST_PROXY_HEADERS=true` only when requests cannot bypass a trusted reverse proxy/load balancer that correctly overwrites `X-Forwarded-Proto` and `X-Forwarded-Host`. Do not enable it simply because a host sends forwarded headers.

Set `APP_URL` to the canonical public HTTPS origin. Alasne prefers this value for canonical URLs, social URLs, robots, and sitemap output.

### HSTS

Recommended initial production values:

```dotenv
HSTS_ENABLED=true
HSTS_INCLUDE_SUBDOMAINS=false
HSTS_PRELOAD=false
```

Alasne emits HSTS only when `APP_ENV=production` and the request is HTTPS. Enable `includeSubDomains` only after every subdomain is HTTPS-ready. Enable preload only after intentionally meeting browser preload requirements; it is difficult to reverse quickly.

## 3. Database

Use a dedicated least-privilege database account, not MySQL/MariaDB root.

```dotenv
DB_CONNECTION=mysql
DB_HOST=database-host
DB_PORT=3306
DB_DATABASE=alasne_production
DB_USERNAME=alasne_app
DB_PASSWORD=strong-secret
```

Before launch:

- Create and test an automated database backup process.
- Test restoration from a backup, not only backup creation.
- Run `php alasne migrate` against a staging copy before production.
- Confirm the required application tables are present through Mission Control's production-readiness checks.
- Do not deploy SQL dumps, database passwords, or local `.env` files with the public application bundle.

## 4. Mail and email queue

Local development should normally use:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

For production SMTP delivery use:

```dotenv
EMAIL_QUEUE_TRANSPORT=smtp
EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES=30
MAIL_BATCH_SIZE=10
MAIL_MAX_ATTEMPTS=3
MAIL_HOST=smtp-provider.example
MAIL_PORT=587
MAIL_USERNAME=provider-user
MAIL_PASSWORD=provider-secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.example
MAIL_FROM_NAME="Alasne"
MAIL_FROM=no-reply@your-domain.example
```

Mission Control also accepts `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, and `SMTP_ENCRYPTION`; those values take precedence in its SMTP service when supplied. Prefer one documented credential source in production rather than maintaining two different SMTP accounts.

Before changing the queue to `smtp`, send a controlled test message and confirm SPF/DKIM/DMARC configuration for the production sending domain.

## 5. Scheduled operations

Mission Control's due-task CLI runner is:

```bash
php scripts/mission-control-run-due.php
```

Schedule this using the production host's scheduler (cron, systemd timer, Windows Task Scheduler, or equivalent) at the cadence appropriate for the configured Mission Control tasks. Prevent overlapping long-running invocations at the scheduler/process level and monitor failed task runs.

Email queue processing supports a lease timeout through:

```dotenv
EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES=30
```

## 6. Carrier integration

The current default carrier adapter is EasyPost. Mission Control stores the names of the environment variables it should read. The default names are:

```dotenv
EASYPOST_API_KEY=
EASYPOST_WEBHOOK_SECRET=
```

Use test credentials until label creation and webhook validation have been exercised on staging. Do not move an integration to live mode until return addresses, package defaults, labels, webhook routing, and failure handling have been verified.

## 7. Supplier integrations

Supplier integrations store credential environment-variable names in the database (`api_key_env`, `api_secret_env`, and `account_id_env`). The production host must define the exact names configured for each active supplier.

Do not commit supplier credentials. Before enabling live automatic order submission, run a non-customer test order and document how to stop/disable the adapter if the supplier endpoint fails.

## 8. Images and uploads

The storefront can serve committed WebP assets without GD, but keep GD with WebP support available for image-processing workflows. Use `fileinfo` for MIME validation when Mission Control image upload capability is enabled. Do not allow executable uploads or unrestricted SVG uploads.

Validate the production runtime with a WebP capability check when image transformation is enabled:

```bash
php -r "echo function_exists('imagewebp') ? 'WEBP=YES' : 'WEBP=NO';"
```

## 9. Security and error handling

Production should use:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

The application disables PHP error display in production and leaves error logging enabled. Send PHP/application logs to a protected location unavailable over HTTP.

Dynamic application responses should include at least:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: SAMEORIGIN
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

CSP is intentionally a separate hardening step because the checkout currently contains inline JavaScript. Do not deploy a restrictive CSP without testing checkout, customer account, tracking/returns, and Mission Control workflows.

## 10. Static assets and caching

`public/.htaccess` currently gives CSS, JavaScript, images, icons, and fonts a seven-day public cache lifetime. Confirm the production Apache/hosting layer preserves those headers. When changing versioned assets, use a cache-busting strategy if the same URL can contain new content.

## 11. Launch verification

Before public launch, verify all of the following on the real HTTPS origin:

1. `composer check-platform-reqs` passes.
2. `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://...` are effective.
3. Session cookies include Secure, HttpOnly, and SameSite.
4. HSTS appears only over HTTPS.
5. Canonical URLs, `robots.txt`, and `sitemap.xml` use the production host.
6. Database uses a non-root account and backups/restores have been tested.
7. Outbound email sends successfully and failed queue items can be retried.
8. Scheduled Mission Control tasks execute unattended and failures are visible.
9. Carrier/supplier integrations remain test/manual until their production credentials and rollback plans are validated.
10. Storefront purchase, inventory decrement, fulfillment, tracking, customer account, return/RMA, restock, refund, and privacy regressions pass on staging.
11. Error pages do not expose stack traces, credentials, filesystem paths, or internal payment identifiers.
12. Apache/host access controls prevent direct public access outside `public/`.

## 12. Post-launch operations

Monitor application/PHP errors, failed email sends, failed supplier submissions, fulfillment exceptions, payment/refund failures, tracking gaps, and database backup status. Keep deployment changes reversible and take a fresh verified backup before schema migrations.
