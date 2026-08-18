# Order Shipped Notification Event Integration

## Purpose

This package wires the third real notification event:

```text
order.shipped
```

The trigger is the existing Mission Control order fulfillment form.

## Trigger rule

The event fires only when:

```text
old shipped_at = empty
new shipped_at = populated
```

That means the initial shipment is treated as `order.shipped`.

Later edits to tracking/carrier details do not fire `order.shipped` again. Those remain on the existing fulfillment-notification path until `tracking.updated` is wired in the next milestone.

## Existing flow preserved

The current order administration flow remains intact:

```text
CSRF validation
tracking URL validation
OrderRepository::updateFulfillment()
success/error flash messages
redirect back to order detail
payment management
refund management
status updates
packing slips
invoices
```

The OrderController constructor is not changed.

## Notification flow

```text
Mission Control order detail
    ↓
Fulfillment details saved
    ↓
shipped_at transitions empty → populated
    ↓
order.shipped
    ↓
Notification Event Bridge
    ↓
Order Shipped automation rule
    ↓
Order Shipped template
    ↓
Dispatch Center
    ↓
email_outbox
```

## Duplicate protection

The event uses:

```text
order.shipped:order_id:ORDER_ID
```

as the Event Bridge idempotency key.

Editing the same shipped order again cannot queue a second `order.shipped` notification.

## Event payload

The publisher exposes:

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
fulfillment_notes
```

Both `carrier` and `shipping_carrier` are supplied for template compatibility.

## Install

Extract this ZIP over:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

## First dry-run test

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
Order Shipped → Customer Tracking
Enabled: checked
Dry Run Only: checked
```

Choose an existing paid test order that has no `shipped_at` value yet.

In its fulfillment section enter test values, for example:

```text
Shipping Carrier: USPS
Tracking Number: 9400111899223859123456
Tracking URL: https://tools.usps.com/go/TrackConfirmAction
Shipped At: current local date/time
```

Save the fulfillment details.

Expected Event Bridge result:

```text
event_key = order.shipped
event_source = admin_fulfillment
status = completed
dry_run = 1
queued = 0
```

## Duplicate test

Edit the same order again and change only the tracking URL or fulfillment notes.

Expected:

```text
no second order.shipped event
```

The existing fulfillment update behavior remains available for that edit.

## Live queue test

After dry-run works:

```text
Order Shipped → Customer Tracking
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Use a DIFFERENT test order that has never had `shipped_at` populated.

Save its first shipment details.

Expected:

```text
order.shipped = queued
Dispatch ID present
Outbox ID present
```

Then process the message from:

```text
/admin/email-queue
```

## Safety

Notification exceptions are caught and logged as:

```text
[Alasne order.shipped notification]
```

A notification failure cannot undo the saved fulfillment data.

## Next milestone

After this passes, wire:

```text
tracking.updated
```

for subsequent carrier/tracking changes after an order has already shipped.

## Commit

```powershell
git status
git add .
git commit -m "Wire order shipped notification event"
git push
```
