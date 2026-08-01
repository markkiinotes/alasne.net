# Production Readiness and Security Hardening

## Prerequisites

Install the prior fulfillment and operations milestones first, including:

- `000038` Supplier Dropshipping Foundation
- `000039` Supplier Integration Adapters
- `000040` Product Sourcing Scanner
- `000041` Supplier Performance Reporting
- `000042` Automated Tracking Reconciliation

This package adds migration `000043`.

## Install

Extract into:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

## New page

```text
/admin/production-readiness
```

## What it adds

This package adds a Mission Control audit center for moving Alasne from
local development toward a hosted production environment.

It checks:

- Application environment
- Debug mode
- HTTPS application URL
- Application key presence
- PHP version
- Required PHP extensions
- display_errors and log_errors
- Session cookie security flags
- Composer autoload and composer.lock
- public/index.php presence
- Database connection
- Database user privilege warning
- Required platform milestone tables
- Supplier integration environment-variable references
- Live auto-submit supplier warnings
- Open dropshipping exceptions
- Failed supplier submissions
- Shipped orders missing tracking
- Payment records needing attention, when payment tables exist

## New tables

```text
production_readiness_runs
production_readiness_items
```

## Saved audits

The page shows a live readiness check each time it loads. Use **Save Audit
Run** before and after deployment changes so you have a timeline of what was
fixed.

Saved runs can be opened and exported as CSV.

## Important notes

This package is read-only except for saving audit results. It does not change:

- Checkout
- Supplier routing
- Payment processing
- Tracking reconciliation
- Customer order state
- Existing production data

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/production-readiness`.
4. Confirm the live checklist loads.
5. Click **Save Audit Run**.
6. Open the saved run.
7. Export the saved audit CSV.
8. Confirm warnings are useful in local XAMPP, especially APP_ENV, APP_URL, cookies, and debug settings.
9. Fix one environment setting locally.
10. Refresh and confirm the check changes.
