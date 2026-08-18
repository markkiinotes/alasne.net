# Payment Captured Notification Event Integration

## Purpose

This package adds the second real storefront notification event:

```text
payment.captured
```

It builds directly on the stable working `order.created` v3 integration.

## Correct storefront flow

```text
Customer submits checkout
    ↓
Order and items are created
    ↓
Payment provider approves charge
    ↓
Payment transaction marked successful
    ↓
Order marked paid
    ↓
Inventory reduced
    ↓
Database transaction COMMIT
    ↓
order.created published
    ↓
payment.captured published
    ↓
Checkout success page
```

Both notification publishers run only after the commerce transaction safely commits.

## Files

```text
app/Services/Checkout/CheckoutService.php
app/Services/Notifications/OrderCreatedNotificationPublisher.php
app/Services/Notifications/PaymentCapturedNotificationPublisher.php
```

## No migration

There is no new database migration.

This package assumes the Notification Event Bridge and automation rules through migration `000053` are already installed.

## Payment event

The publisher emits:

```text
payment.captured
```

with:

```text
event_source = storefront_checkout
```

and an explicit duplicate-prevention key:

```text
payment.captured:payment_transaction_id:PAYMENT_TRANSACTION_ID
```

## Event payload

The payment event includes:

```text
payment_transaction_id
order_id
order_number
order_status
payment_status
payment_method_id
payment_method_name
payment_method_code
payment_provider
currency
payment_amount
amount_paid
order_total
customer_id
customer_email
customer_name
store_id
store_name
store_slug
paid_at
created_at
```

The payload intentionally includes `payment_amount`, because the seeded `Payment Received` template can use that value.

## Safety behavior

`payment.captured` is published only when:

```text
payment provider returned success
payment transaction was marked successful
order.payment_status = paid
order.payment_transaction_id matches the successful transaction
inventory updates succeeded
transaction committed
```

If payment notification processing fails, checkout remains successful.

The error is logged with:

```text
[Alasne payment.captured notification]
```

`order.created` and `payment.captured` also use separate try/catch blocks. A problem in one event cannot suppress the other.

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
Order Created → Customer Confirmation
Enabled: checked
Dry Run Only: checked

Payment Captured → Customer Receipt
Enabled: checked
Dry Run Only: checked
```

Place one NEW storefront order with:

```text
Test Payment
Scenario: Approved
```

Expected checkout result:

```text
success page displays normally
status = paid
payment_status = paid
```

Expected Event Bridge:

```text
order.created
source = storefront_checkout
dry_run = 1

payment.captured
source = storefront_checkout
dry_run = 1
```

No new outbox message should be created by either rule while Dry Run Only remains enabled.

## Live queue test

After both dry runs work, keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Then change only:

```text
Payment Captured → Customer Receipt
Enabled: checked
Dry Run Only: unchecked
```

Place another NEW approved storefront order.

Expected:

```text
order.created = dry_run
payment.captured = queued
payment.captured Dispatch ID present
payment.captured Outbox ID present
```

Open:

```text
/admin/email-queue
```

and process the receipt manually.

## SMTP test

After log-mode passes:

```dotenv
EMAIL_QUEUE_TRANSPORT=smtp
```

Place one new approved order and process the queue.

The `Payment Received` template should be delivered through the already-working SMTP transport.

## Regression checks

Confirm after installation:

1. Checkout success page still works.
2. Approved test payments still create paid orders.
3. Declined/error scenarios still follow the existing failure path.
4. Inventory only reduces on successful payment.
5. `order.created` still appears once.
6. `payment.captured` appears once.
7. Reusing the same payment transaction id is blocked by Event Bridge idempotency.
8. Notification failures cannot undo a paid order.
9. No legacy hard-coded confirmation email path is re-enabled.

## Commit

```powershell
git status
git add .
git commit -m "Wire payment captured notification event"
git push
```
