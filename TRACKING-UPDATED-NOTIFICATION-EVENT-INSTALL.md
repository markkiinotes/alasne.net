# Tracking Updated Notification Event Integration

## Purpose

This package wires:

```text
tracking.updated
```

into the existing Mission Control fulfillment workflow.

It builds directly on the tested `order.shipped` integration.

## Event separation

The fulfillment workflow now distinguishes:

```text
First shipment:
    shipped_at changes empty → populated
    event = order.shipped

Later carrier/tracking change:
    order was already shipped
    carrier, tracking number, or tracking URL changes
    event = tracking.updated

Notes-only or other non-tracking fulfillment edit:
    existing fulfillment notification path
```

## Tracking fields watched

```text
shipping_carrier
tracking_number
tracking_url
```

A change to `shipped_at` alone on an already-shipped order does not create a `tracking.updated` event.

## Duplicate prevention

The Event Bridge idempotency key includes:

```text
order ID
+
SHA-256 fingerprint of:
    carrier
    tracking number
    tracking URL
```

Example shape:

```text
tracking.updated:order_id:123:<fingerprint>
```

This prevents repeated notification of the same tracking state.

## Event payload

```text
event_id
order_id
order_number
order_status
payment_status
customer_email
customer_name
store_id
store_name
carrier
shipping_carrier
tracking_number
tracking_url
shipped_at
previous_shipping_carrier
previous_tracking_number
previous_tracking_url
fulfillment_notes
```

The previous tracking values are included so templates and future audit views can explain exactly what changed.

## Install

Extract over:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No migration is required.

## Dry-run test

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Open:

```text
/admin/notification-automations
```

Set:

```text
Tracking Updated → Customer Notice
Enabled: checked
Dry Run Only: checked
```

Choose an order that is ALREADY shipped.

Change one tracking field, for example:

```text
Tracking Number:
9400111899223859123456
→
9400111899223859129999
```

Save.

Expected Event Bridge:

```text
event_key = tracking.updated
event_source = admin_fulfillment
status = completed
dry_run = 1
queued = 0
```

## Notes-only test

On the same shipped order, change only:

```text
Fulfillment Notes
```

Do not change carrier, tracking number, or tracking URL.

Expected:

```text
no tracking.updated event
```

## Unchanged tracking test

Save the same carrier/tracking values again.

Expected:

```text
no new tracking.updated event
```

## Live queue test

After dry-run passes:

```text
Tracking Updated → Customer Notice
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Change a tracking field on a shipped test order.

Expected:

```text
tracking.updated = queued
Dispatch ID present
Outbox ID present
```

Process from:

```text
/admin/email-queue
```

## Safety

The fulfillment update is saved first.

Notification exceptions are isolated and logged as:

```text
[Alasne tracking.updated notification]
```

A notification problem cannot undo the fulfillment update.

## Existing order.shipped behavior

The tested first-shipment behavior remains:

```text
old shipped_at = empty
new shipped_at = populated
→ order.shipped
```

and continues using:

```text
order.shipped:order_id:ORDER_ID
```

for duplicate prevention.

## Next milestone

After this passes:

```text
return.requested
```

will be the next real Event Bridge integration.

## Commit

```powershell
git status
git add .
git commit -m "Wire tracking updated notification event"
git push
```
