# Mission Control Notification Event Bridge

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
000053_create_notification_event_bridge.php
```

## New pages

```text
/admin/notification-event-bridge
/admin/notification-event-bridge/export
/admin/notification-event-bridge/simulate
```

## What it adds

This package adds the internal event bridge for notifications.

It includes:

- Notification Event Bridge dashboard
- Manual event simulator
- Event rule coverage table
- Sample event payloads
- Run audit log
- Rule item audit log
- Duplicate-prevention/idempotency keys
- Dry-run event handling
- Live queueing through Dispatch Center
- Exportable bridge runs CSV
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_notification_event_bridge_runs
mission_control_notification_event_bridge_items
```

## Internal service entry point

Future modules can call:

```php
$bridge->handle('order.created', [
    'event_id' => 'order-created-10045',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'Jordan Customer',
    'order_number' => 'A10045',
    'store_name' => 'Demo Store',
    'order_total' => '$84.97',
]);
```

The bridge will:

```text
Receive the event
Check idempotency
Find matching enabled automation rules
Resolve the recipient
Render the connected template
Respect dry-run mode
Queue through Dispatch Center when live
Record every run and item
Avoid duplicate queueing
```

## Safe flow

Dry-run flow:

```text
Platform event or manual simulator
    ↓
Event Bridge
    ↓
Automation Rule
    ↓
Template render
    ↓
Bridge run/item log
    ↓
No outbox message
```

Live queueing flow:

```text
Platform event or manual simulator
    ↓
Event Bridge
    ↓
Enabled non-dry-run Automation Rule
    ↓
Dispatch Center
    ↓
Email Outbox
    ↓
Email Queue Processing
    ↓
SMTP
```

## Idempotency

The bridge accepts an explicit idempotency key. It can also derive one from payload values such as:

```text
idempotency_key
event_id
order_id
order_number
tracking_number
return_number
rma_number
purchase_order_number
```

That prevents accidentally queuing the same event/rule combination repeatedly.

## Safe scope

This package does not change:

- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Existing templates
- Existing dispatch behavior
- Existing email queue processor behavior
- SMTP settings
- Scheduled task frequency

It adds a central service and simulator that future platform modules can call.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Keep `.env` set to `EMAIL_QUEUE_TRANSPORT=log`.
4. Open `/admin/notification-event-bridge`.
5. Choose event `order.created`.
6. Put your email in Recipient Override.
7. Leave Dry Run checked.
8. Check `Include disabled rules for dry-run testing`.
9. Click `Simulate Event`.
10. Confirm a bridge run appears.
11. Confirm a bridge item appears with status `dry_run`.
12. Go to `/admin/notification-automations`.
13. Enable `Order Created → Customer Confirmation`.
14. Leave `Dry Run Only` checked and save.
15. Return to `/admin/notification-event-bridge`.
16. Simulate `order.created` again.
17. Confirm it logs a dry-run through the enabled rule.
18. To test queueing, uncheck `Dry Run Only` on the automation rule.
19. Simulate again with a new idempotency key.
20. Confirm it creates a Dispatch and Outbox ID.
21. Open `/admin/email-queue`.
22. Process the message manually.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control notification event bridge"
git push
```
