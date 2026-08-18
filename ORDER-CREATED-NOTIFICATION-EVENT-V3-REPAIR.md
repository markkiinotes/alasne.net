# Order Created Notification Event Integration v3

## Critical repair

The earlier v1/v2 integration packages were accidentally based on an older tax-only CheckoutService.

That service did not contain the installed payment workflow. It could create and commit an order without changing:

```text
payment_status = paid
```

The CheckoutController then correctly redirected to:

```text
/store/{slug}/checkout/success
```

but the success action only displays orders where:

```sql
o.payment_status = 'paid'
```

so it immediately redirected to the storefront homepage.

## What v3 preserves

v3 is based on the payment-enabled CheckoutService and retains:

```text
PaymentMethodRepository
PaymentTransactionRepository
PaymentProviderInterface
TestPaymentProvider
PaymentResult
CheckoutPaymentFailedException
payment transaction records
payment_status updates
amount_paid
paid_at
inventory reduction only after payment approval
```

Only the old hard-coded paid-order confirmation-email path is replaced.

## Correct flow

```text
Complete Order
    ↓
Create order + items
    ↓
Run payment provider
    ↓
Payment approved
    ↓
status = paid
payment_status = paid
    ↓
Reduce inventory
    ↓
COMMIT
    ↓
Publish order.created
    ↓
Notification Event Bridge
    ↓
Return order ID
    ↓
/checkout/success displays paid order
```

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

No new migration is required.

Migrations through `000053` should already be installed.

## First test

Before focusing on email, verify checkout itself:

1. Add a product to the cart.
2. Open checkout.
3. Select the test payment method.
4. Choose the `approved` scenario.
5. Click Complete Order.
6. Confirm `/checkout/success` displays the order.
7. In Mission Control confirm:
   - status = paid
   - payment_status = paid
   - amount_paid is populated
   - paid_at is populated
   - successful payment transaction exists
8. Confirm inventory decreased once.

## Notification dry-run

Set:

```text
Order Created → Customer Confirmation
Enabled: checked
Dry Run Only: checked
```

Place a NEW approved order.

Expected Event Bridge result:

```text
event_key = order.created
event_source = storefront_checkout
dry_run = 1
queued = 0
```

## Live queue test

Then set:

```text
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Place another NEW approved order.

Expected:

```text
payment_status = paid
queued = 1
Dispatch ID present
Outbox ID present
```

Process the message manually from:

```text
/admin/email-queue
```

## Duplicate protection

Each order uses:

```text
order.created:order_id:ORDER_ID
```

as its Event Bridge idempotency key.

## Existing v1/v2 test orders

The orders created while v1/v2 were installed should be treated as test artifacts.

They may show:

```text
status = pending
payment_status != paid
```

and the older service may also have reduced inventory.

Do not delete or change them until you inspect their status and inventory impact. We can clean them up safely after v3 checkout is confirmed.

## Commit

```powershell
git status
git add .
git commit -m "Repair paid checkout order created notification integration"
git push
```
