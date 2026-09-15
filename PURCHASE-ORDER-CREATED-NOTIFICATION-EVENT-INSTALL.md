# Purchase Order Created Notification Event Integration

## Purpose

This package wires:

```text
purchase_order.created
```

into the real dropshipping supplier-routing flow.

It uses the latest `DropshipFulfillmentService.php` from the supplier
integration-adapters package. No checkout, supplier adapter, repository,
database schema, or submission controller is replaced.

## Existing routing flow preserved

The service still performs:

```text
paid-order validation
unrouted-item lookup
supplier mapping selection
supplier grouping
purchase order creation/reuse
purchase order item creation
PO cost/revenue/profit recalculation
automatic supplier-submission preparation
dropship exception handling
order routing totals/status
order timeline event
routing transaction COMMIT
```

Only newly-created purchase order IDs are collected during the transaction.

After COMMIT:

```text
new PO
    ↓
purchase_order.created
    ↓
Notification Event Bridge
    ↓
Purchase Order Created → Supplier Notice
    ↓
Supplier Purchase Order Notification template
    ↓
Dispatch Center / email_outbox
```

## Existing purchase orders do not re-fire

Routing retries may encounter an existing purchase order for the same:

```text
customer order + supplier
```

The existing `findOrCreatePurchaseOrder()` logic is preserved.

A by-reference creation flag now distinguishes:

```text
existing PO → no purchase_order.created
new PO      → publish after commit
```

This avoids duplicate supplier notices during routing retries.

## Event source

```text
dropship_fulfillment
```

## Duplicate protection

```text
purchase_order.created:purchase_order_id:PURCHASE_ORDER_ID
```

Event Bridge provides a second layer of protection even if a publisher is
ever called again manually.

## Seeded automation rule

```text
Purchase Order Created → Supplier Notice
event_key = purchase_order.created
template = Supplier Purchase Order Notification
recipient = supplier_email
```

## Seeded template variables

The template requires:

```text
supplier_name
purchase_order_number
order_number
ship_to_name
item_summary
store_name
```

The publisher supplies all of them.

It also supplies:

```text
purchase_order_id
purchase_order_status
supplier_id
supplier_code
supplier_email
order_id
payment_status
store_id
item_count
unit_count
currency
items_subtotal
total_cost
customer_revenue
estimated_profit
expected_ship_at
submission_status
provider_code
created_at
```

## Data safety

The supplier notice does NOT include:

```text
payment credentials
payment tokens
API keys
integration secrets
customer payment data
```

Names, supplier codes, product names, and SKUs used in the notification are
normalized to plain text before entering the template payload.

The notification contains `ship_to_name`, but not the full customer address
or customer email. The supplier submission adapter continues to own the
actual fulfillment payload.

## Missing supplier email

The publisher intentionally does not suppress the business event when the
supplier email is missing or invalid.

Event Bridge owns recipient validation. Therefore an enabled rule with an
invalid supplier address is recorded as a failed rule in the bridge rather
than hiding the fact that the purchase order was created.

## Install

Extract over:

```text
C:\xampp\htdocs\alasne.net
```

Then:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No migration is required.

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
Purchase Order Created → Supplier Notice
Enabled: checked
Dry Run Only: checked
```

For the cleanest test, use:

```text
a supplier with a valid email address
an active product → supplier mapping
known supplier availability sufficient for the test quantity
a fresh paid storefront order
```

The existing checkout supplier-routing hook should automatically route the
paid order.

Expected commerce result:

```text
checkout remains successful
order remains paid
dropship routing runs
new purchase order created
purchase order items created
PO totals populated
supplier submission may be prepared depending on integration settings
```

Expected Event Bridge:

```text
event_key = purchase_order.created
event_source = dropship_fulfillment
status = completed
dry_run = 1
queued = 0
```

Expected payload includes:

```text
purchase_order_number
supplier_name
supplier_email
order_number
ship_to_name
item_summary
store_name
```

## Manual routing alternative

If a paid order has not yet been routed, use the existing Mission Control
supplier-routing action for that order.

The event fires only when that routing action creates a NEW purchase order.

If the order already has its PO, retrying routing should NOT generate a
second `purchase_order.created` event.

## Multiple suppliers

If one customer order routes to two suppliers:

```text
PO #1 created
PO #2 created
COMMIT
purchase_order.created for PO #1
purchase_order.created for PO #2
```

Each event has its own PO-based idempotency key.

## Live queue test

After dry-run passes:

```text
Purchase Order Created → Supplier Notice
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Place a DIFFERENT fresh paid order that creates a new supplier PO.

Expected:

```text
purchase_order.created = queued
Dispatch ID present
Outbox ID present
```

Process it from:

```text
/admin/email-queue
```

Do not switch the supplier rule to SMTP until the dry-run and log-mode
message have been reviewed for the test supplier address.

## Failure isolation

A Notification Event Bridge failure is logged with:

```text
[Alasne purchase_order.created notification]
```

The purchase order and customer order remain committed.

## Regression checks

Confirm:

```text
paid-order supplier routing still works
routing retries still reuse existing purchase orders
unmapped items still create dropship exceptions
supplier submission auto-preparation still works
PO totals/profit still calculate
order dropship status still updates
notification failure does not undo routing
```

## Next event

After this passes:

```text
supplier_submission.failed
```

will connect supplier transmission/preparation failures to Mission Control
admin alerts.

## Commit

```powershell
git status
git add .
git commit -m "Wire purchase order created notification event"
git push
```
