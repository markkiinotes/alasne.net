# Mission Control Scheduled Operations

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
000048_create_mission_control_scheduled_operations.php
```

## New pages

```text
/admin/scheduled-operations
/admin/scheduled-operations/export
/admin/scheduled-operations/TASK_ID
```

## What it adds

This package adds a safe Mission Control scheduler layer.

It includes:

- Scheduled task configuration
- Manual `Run Now` for each task
- `Run Due Operations`
- Run history audit log
- CSV export for scheduled runs
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_scheduled_tasks
mission_control_scheduled_runs
```

## Default scheduled tasks

The migration seeds:

```text
Hourly Alert Scan
Daily Admin Briefing Snapshot
Daily KPI Checkpoint
Weekly Admin Briefing Snapshot
```

## Supported task types

```text
alert_scan
briefing_snapshot
kpi_checkpoint
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
- Alert thresholds
- Existing briefing snapshots

It only runs existing Mission Control services and records scheduled operation audit logs.

## Cron-ready note

This phase does not expose a public unauthenticated cron URL.

For now, run due tasks inside Mission Control with:

```text
/admin/scheduled-operations
```

Later, we can add a protected CLI command such as:

```powershell
php alasne mission-control:run-due
```

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/scheduled-operations`.
4. Confirm default scheduled tasks appear.
5. Click `Run Now` on Hourly Alert Scan.
6. Confirm a run record appears.
7. Click `Run Now` on Daily Admin Briefing Snapshot.
8. Confirm a briefing is created in `/admin/briefings`.
9. Click `Run Due Operations`.
10. Edit one scheduled operation.
11. Export scheduled runs CSV.
12. Open `/admin`.
13. Confirm the Mission Control hub has a Schedule link.
14. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control scheduled operations"
git push
```
