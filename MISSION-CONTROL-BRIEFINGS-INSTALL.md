# Mission Control Alert Digest & Admin Briefing

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

This package adds migration:

```text
000047_create_mission_control_briefings.php
```

## New pages

```text
/admin/briefings
/admin/briefings/export
/admin/briefings/BRIEFING_ID
/admin/briefings/BRIEFING_ID/export
```

## What it adds

This package converts Mission Control alerts and KPIs into a management-ready briefing.

It includes:

- Live briefing preview
- Saved briefing snapshots
- Executive summary generation
- Priority action items
- Copy-ready briefing text
- Saved briefing history
- CSV export for previews
- CSV export for saved briefings
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_briefings
mission_control_briefing_items
```

## Briefing metrics

Each briefing snapshot stores:

```text
Open alerts
Critical alerts
Warning alerts
Acknowledged alerts
Resolved alerts
Tracking gaps
Open fulfillment exceptions
Failed supplier submissions
Open returns
Blocked store launches
Sales revenue
Gross profit
Estimated margin percentage
```

## Filters

The briefing preview supports:

```text
Period start
Period end
Store
Supplier
```

## Safe scope

This package does not change:

- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Customer-facing pages
- Alert rule thresholds
- Alert statuses

It reads alerts and KPIs, then saves briefing snapshots.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/briefings`.
4. Confirm the live briefing preview loads.
5. Change the period.
6. Filter by store.
7. Click `Export Preview`.
8. Click `Save Briefing`.
9. Open the saved briefing.
10. Export the saved briefing CSV.
11. Return to `/admin`.
12. Confirm the Mission Control hub has a Briefings link.
13. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control alert digest briefings"
git push
```
