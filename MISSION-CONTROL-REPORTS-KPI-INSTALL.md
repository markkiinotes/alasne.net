# Mission Control Reports & KPI Center

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

## New Mission Control pages

```text
/admin/reports
/admin/reports/export
```

## What it adds

This package adds a read-only executive reporting dashboard for Mission Control.

It includes:

- Sales revenue
- Paid order count
- Average order value
- Supplier cost
- Gross profit
- Estimated margin percentage
- Refund activity
- Store credit issued and redeemed
- Tracking gaps
- Open fulfillment exceptions
- Failed supplier submissions
- Open returns
- Blocked store launches
- Approved product sourcing count
- Sales by store
- Supplier performance table
- Operations KPI table
- Returns by status
- Store readiness table
- Product sourcing summary
- Recent orders
- CSV export

## Filters

The Reports & KPI Center supports:

```text
Date from
Date to
Store
Supplier
```

## Export

Use:

```text
/admin/reports/export
```

The export respects the same filters as the dashboard.

## Safe scope

This package is read-only and UI/reporting focused.

It does not change:

- Database schema
- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Customer-facing pages

## Schema tolerance

The report service checks whether optional tables and columns exist before
querying them. This lets the KPI center work across installed Alasne modules
without hard-failing when an optional metric is unavailable.

## Files included

```text
app/Controllers/Admin/ReportsKpiController.php
app/Services/Admin/ReportsKpiService.php
app/Services/Admin/MissionControlNavigationService.php
app/Views/admin/reports/index.php
app/Views/admin/mission-control/index.php
config/routes.php
```

## Test sequence

1. Extract the package.
2. Run `composer dump-autoload -o`.
3. Open `/admin/reports`.
4. Confirm KPI cards load.
5. Change the date range.
6. Filter by store.
7. Filter by supplier if supplier data exists.
8. Open `/admin/reports/export`.
9. Confirm the CSV downloads.
10. Open `/admin`.
11. Confirm the Mission Control hub now has a Reports link.
12. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control reports KPI center"
git push
```
