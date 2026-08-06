# Mission Control Alerts & Notification Center

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
000046_create_mission_control_alerts.php
```

## New Mission Control pages

```text
/admin/alerts
/admin/alerts/export
/admin/alerts/rules/create
/admin/alerts/rules/RULE_ID
```

## What it adds

This package adds proactive Mission Control alerts for business and operational risk.

It includes:

- Alert rules
- Generated alerts
- Alert event history
- Alert scan button
- Alert status updates
- Alert resolution notes
- Severity levels
- Store and supplier scoped rules
- CSV alert export
- Mission Control navigation link
- Mission Control header quick link

## New tables

```text
mission_control_alert_rules
mission_control_alerts
mission_control_alert_events
```

## Default rules

The migration seeds these default alert rules:

```text
Tracking gaps detected
Open fulfillment exceptions
Failed supplier submissions
Open returns need review
Blocked store launches
Estimated margin below target
```

## Supported metrics

```text
tracking_gaps
open_exceptions
failed_submissions
open_returns
blocked_stores
estimated_margin_percent
paid_orders
sales_revenue
gross_profit
store_credit_redeemed
```

## Alert statuses

```text
Open
Acknowledged
Resolved
```

## Severity levels

```text
Critical
Warning
Info
```

## How it works

1. Open `/admin/alerts`.
2. Click `Run Alert Scan`.
3. The service evaluates enabled alert rules against current Alasne data.
4. If a rule threshold is crossed, an alert is opened or refreshed.
5. Alerts can be acknowledged or resolved with a note.
6. Alert events preserve the alert timeline.

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

It only adds alert tables, alert UI, and read-only metric scans.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/alerts`.
4. Confirm default rules appear.
5. Click `Run Alert Scan`.
6. Confirm alerts open if thresholds are crossed.
7. Acknowledge one alert.
8. Resolve one alert with a note.
9. Create a custom rule.
10. Edit the custom rule.
11. Export alerts CSV.
12. Open `/admin`.
13. Confirm the Mission Control hub has an Alerts link.
14. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control alerts notification center"
git push
```
