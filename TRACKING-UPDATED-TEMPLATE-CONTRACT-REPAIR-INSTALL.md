# Tracking Updated Template Contract Repair

## Install

Extract over:

```text
C:\xampp\htdocs\alasne.net
```

Then:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
php -l app\Services\Notifications\TrackingUpdatedNotificationPublisher.php
```

No migration is required.

## Before retesting

In:

```text
/admin/notification-automations
```

set:

```text
Tracking Updated → Customer Notice
Enabled: checked
Dry Run Only: checked
```

For the controlled audit, also set:

```text
Order Shipped → Customer Tracking
Dry Run Only: checked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

## Retest

Use an ALREADY SHIPPED order and change one real tracking field to a NEW
value:

```text
carrier
tracking number
or tracking URL
```

A new value is important because `tracking.updated` idempotency includes the
tracking-state fingerprint.

Expected Event Bridge result:

```text
event_key = tracking.updated
source = admin_fulfillment
status = completed
matched = 1
queued = 0
dry_run = 1
failed = 0
```

## Git

This package includes:

```text
docs/notifications/TRACKING-UPDATED-CONTRACT-REPAIR.md
```

After the retest passes:

```powershell
git status
git diff --check
git add app/Services/Notifications/TrackingUpdatedNotificationPublisher.php
git add docs/notifications/TRACKING-UPDATED-CONTRACT-REPAIR.md
git commit -m "Repair tracking updated notification template contract"
git push
```
