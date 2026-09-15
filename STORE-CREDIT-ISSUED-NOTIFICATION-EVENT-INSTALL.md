# Store Credit Issued Notification Event Integration

## Purpose

This package wires:

```text
store_credit.issued
```

into the existing return-resolution system.

It does not change:

```text
return eligibility
return receiving
approved merchandise value
store-credit account math
store-credit transaction creation
exchange creation
cash-refund allocation
payment refund behavior
checkout redemption
store-credit restoration
```

## Correct trigger point

The existing resolution service creates store credit inside its protected
database transaction:

```text
Return received
    ↓
Resolution amounts validated
    ↓
StoreCreditRepository::creditForReturn()
    ↓
store_credit_transactions row created
    ↓
account balance updated
    ↓
return resolution/audit events written
    ↓
COMMIT
    ↓
store_credit.issued Event Bridge
```

The event is deliberately published immediately after that COMMIT.

## Why this occurs before the external refund attempt

A mixed return can contain:

```text
store credit
+
external/card refund
```

The non-cash return resolution commits before the separate payment-refund
attempt.

Therefore:

```text
store credit committed successfully
external refund later fails
```

must still produce:

```text
store_credit.issued
```

The customer really has that credit even if a different tender refund needs
attention.

## Existing Return Completed notification

The existing general Return Completed message remains intact.

It is a return-resolution summary.

The new `store_credit.issued` event is an account/balance notice and contains
the customer's resulting store-credit balance.

## Automation rule

Use:

```text
Store Credit Issued → Customer Notice
event_key = store_credit.issued
template = Store Credit Issued
recipient = customer_email
```

## Seeded template variables

The Store Credit Issued template requires:

```text
customer_name
credit_amount
credit_balance
store_name
```

All are supplied by this event.

Additional payload fields include:

```text
store_credit_transaction_id
store_credit_account_id
transaction_type
credit_amount_value
credit_balance_value
currency
return_id
return_number
return_status
resolution_type
resolution_status
order_id
order_number
customer_id
customer_email
store_id
store_slug
issued_at
```

## Duplicate protection

The Event Bridge idempotency key is:

```text
store_credit.issued:transaction_id:STORE_CREDIT_TRANSACTION_ID
```

The underlying store-credit repository already separately protects return
credit creation with:

```text
return-credit-RETURN_ID
```

So both the financial transaction and the notification event are
idempotent at their respective layers.

## Event source

```text
return_resolution
```

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
Store Credit Issued → Customer Notice
Enabled: checked
Dry Run Only: checked
```

Use a return that has reached:

```text
received
```

status.

In Mission Control's return-resolution section allocate some or all of the
approved merchandise value to:

```text
Store Credit
```

For the cleanest first test, use:

```text
Cash Refund: $0.00
Store Credit: full approved merchandise value
Exchange: none
```

Complete the return resolution.

Expected financial result:

```text
return resolution completes
store_credit_accounts balance increases once
store_credit_transactions contains one return_credit row
return timeline records Store credit issued
```

Expected Event Bridge:

```text
event_key = store_credit.issued
event_source = return_resolution
status = completed
dry_run = 1
queued = 0
```

Expected payload:

```text
credit_amount = $xx.xx
credit_balance = $xx.xx
customer_email populated
store_name populated
return_number populated
```

## Mixed-resolution test

A later test may use:

```text
Store Credit: > $0
Cash Refund: > $0
```

If store credit commits but the test payment refund is intentionally made to
fail, `store_credit.issued` should still appear because the credit is already
real and durable.

## Live queue test

After dry-run passes:

```text
Store Credit Issued → Customer Notice
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Complete a DIFFERENT received return using store credit.

Expected:

```text
store_credit.issued = queued
Dispatch ID present
Outbox ID present
```

Process it from:

```text
/admin/email-queue
```

## Failure isolation

Event Bridge failures are logged with:

```text
[Alasne store_credit.issued notification]
```

A notification error cannot roll back or reduce an already-issued store
credit balance.

## RMA test status

`rma.approved` is implemented but remains pending a clean fresh-order test
because existing historical test orders had all merchandise quantities
already allocated to prior returns.

## Next lifecycle events

After this event:

```text
purchase_order.created
supplier_submission.failed
```

are the remaining seeded operational notification hooks.

## Commit

```powershell
git status
git add .
git commit -m "Wire store credit issued notification event"
git push
```
