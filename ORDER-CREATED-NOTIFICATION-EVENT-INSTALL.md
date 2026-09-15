# Alasne Order Created Notification Event Integration

## Purpose

This package wires the first real platform event into the Mission Control notification stack:

```text
storefront checkout
    ↓
order transaction commits successfully
    ↓
order.created
    ↓
Notification Event Bridge
    ↓
Notification Automation Rule
    ↓
Order Confirmation template
    ↓
Notification Dispatch Center
    ↓
email_outbox
    ↓
Email Queue Processing
    ↓
SMTP
```

## Install

Extract this ZIP into:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

There is no new database migration in this package.

This package assumes migrations through `000053` have already been installed.

## Files

```text
app/Services/Checkout/CheckoutService.php
app/Services/Notifications/OrderCreatedNotificationPublisher.php
```

## What changed

The storefront CheckoutService previously:

1. Created the order.
2. Created order items.
3. Queued a hard-coded confirmation in `email_outbox`.
4. Committed the database transaction.
5. Tried immediate email delivery.

This package replaces steps 3 and 5 for order confirmation with the Notification Event Bridge.

The order is now committed first. Only after a successful commit does checkout publish:

```text
order.created
```

Notification errors are caught and logged so they cannot roll back or invalidate a successful customer order.

## Event payload

The publisher builds the event payload from the saved order, store, and customer records.

Example shape:

```json
{
  "event_id": "order-created-123",
  "order_id": 123,
  "order_number": "WEB-20260812-182700-1234",
  "order_status": "pending",
  "customer_id": 45,
  "customer_email": "customer@example.com",
  "customer_name": "Jordan Customer",
  "store_id": 1,
  "store_name": "Demo Store",
  "store_slug": "demo-store",
  "subtotal": "75.00",
  "tax_total": "4.97",
  "shipping_total": "5.00",
  "discount_total": "0.00",
  "order_total": "$84.97",
  "grand_total": "84.97"
}
```

## Duplicate protection

Each real storefront order uses:

```text
order.created:order_id:ORDER_ID
```

as the explicit Event Bridge idempotency key.

That prevents the same saved order from creating the same notification twice if the publisher is accidentally invoked again.

## Important automation setting

Before expecting real order confirmation messages, open:

```text
/admin/notification-automations
```

Find:

```text
Order Created → Customer Confirmation
```

For a safe first test:

```text
Enabled: checked
Dry Run Only: checked
```

Place one storefront test order.

Then open:

```text
/admin/notification-event-bridge
```

You should see a real event with:

```text
event_key: order.created
event_source: storefront_checkout
status: completed
dry-run item: yes
```

No outbox message will be created while the rule remains Dry Run Only.

## Live queue test

After the dry-run event works:

```text
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Place a NEW storefront test order.

A new Event Bridge run should create:

```text
Dispatch ID
Email Outbox ID
```

Then open:

```text
/admin/email-queue
```

and process the message manually.

## SMTP test

After the log-mode queue test passes, use your confirmed Turbify SMTP configuration:

```dotenv
EMAIL_QUEUE_TRANSPORT=smtp
```

Place one new storefront order and process the queue.

The customer should receive the editable `Order Confirmation` template from:

```text
/admin/notification-templates
```

## Important: use a new order for every test

Because the Event Bridge has duplicate protection, repeatedly publishing the same order ID is intentionally ignored.

## Safety behavior

A notification exception after checkout commit is logged with:

```text
[Alasne order.created notification]
```

but does not cause the order to fail.

## Existing hard-coded method

The older `queueOrderConfirmationEmail()` method remains in CheckoutService for compatibility/history, but the storefront checkout path no longer calls it.

This prevents duplicate order confirmation emails while minimizing unrelated changes.

## Regression checks

After installation confirm:

1. Storefront checkout still creates an order.
2. Order items still save.
3. Inventory still reduces.
4. Existing order timeline still records `order_created`.
5. Checkout redirects/succeeds normally.
6. Event Bridge records `order.created`.
7. With Dry Run Only enabled, no outbox message is created.
8. With Dry Run Only disabled, exactly one outbox message is created.
9. Reprocessing the same order event is blocked by idempotency.
10. SMTP/email errors do not undo the completed order.

## Commit

```powershell
git status
git add .
git commit -m "Wire storefront order created notification event"
git push
```
