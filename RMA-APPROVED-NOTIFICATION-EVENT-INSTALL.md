# RMA Approved Notification Event Integration

## Purpose

This package wires:

```text
rma.approved
```

into both real approval paths:

```text
1. Customer self-service policy auto-approval
2. Mission Control manual approval
```

It builds on the already-working `return.requested` integration.

## Existing approval behavior preserved

`ReturnService` still owns all business logic:

```text
status validation
approved quantities/refund amount
RMA generation
authorization issued/expires timestamps
return-address snapshot
return-instructions snapshot
shipping-responsibility snapshot
return audit events
order audit events
transaction commit
```

This package changes only the customer notification layer after those
transactions succeed.

## Auto-approved customer path

```text
Customer submits return
    ↓
ReturnService::create()
    ↓
return.requested recorded
    ↓
policy auto-approves return
    ↓
RMA authorization issued
    ↓
COMMIT
    ↓
return.requested Event Bridge
    ↓
rma.approved Event Bridge
```

The previous legacy approved/RMA email is removed from this path.

## Manual Mission Control approval

```text
Mission Control → Return → Approve
    ↓
ReturnService::approve()
    ↓
approved status + RMA authorization
    ↓
COMMIT
    ↓
rma.approved Event Bridge
```

The previous:

```text
queueNotification(returnId, 'approved')
```

call is replaced only in the approval action.

Receive/refund/cancel/shipping notifications remain unchanged.

## Automation rule

Use:

```text
RMA Approved → Customer Instructions
event_key = rma.approved
template = RMA Approved
recipient = customer_email
```

## Template payload

The seeded RMA Approved template requires:

```text
customer_name
order_number
rma_number
return_instructions
store_name
```

The publisher supplies all of them plus:

```text
return_id
return_number
return_status
request_source
authorization_issued_at
authorization_expires_at
return_address
return_shipping_responsibility
approved_refund_amount
currency
order_id
customer_id
customer_email
store_id
store_slug
approved_at
created_at
```

Raw internal approval notes are not included.

Return instructions/address snapshots are stripped of markup before entering
the notification payload.

## Duplicate protection

Both approval paths use the same key:

```text
rma.approved:return_id:RETURN_ID
```

So an RMA approval cannot generate duplicate Event Bridge customer
instructions for the same return.

## Event sources

Policy auto-approval:

```text
customer_policy_auto_approval
```

Manual Mission Control approval:

```text
mission_control_returns
```

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

## First dry-run test: manual approval

For the cleanest test:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Open:

```text
/admin/notification-automations
```

Set:

```text
RMA Approved → Customer Instructions
Enabled: checked
Dry Run Only: checked
```

Use a customer return currently in:

```text
requested
```

status.

Approve it from Mission Control.

Expected:

```text
return status = approved
RMA number populated
authorization issued/expires populated
Mission Control success message remains normal
```

Event Bridge expected:

```text
event_key = rma.approved
event_source = mission_control_returns
status = completed
dry_run = 1
queued = 0
```

## Auto-approval dry-run test

With the same rule still in dry-run, configure/use a store return policy with:

```text
auto_approve_customer_requests = enabled
```

Submit a new eligible customer return.

Expected Event Bridge events:

```text
return.requested
    source = customer_self_service

rma.approved
    source = customer_policy_auto_approval
```

Both should represent the same newly created return.

## Duplicate test

Re-processing the same approval state through the Event Bridge cannot queue
another `rma.approved` customer instruction because the idempotency key is
bound to the return ID.

## Live queue test

After dry-run passes:

```text
RMA Approved → Customer Instructions
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Approve a DIFFERENT requested return.

Expected:

```text
rma.approved = queued
Dispatch ID present
Outbox ID present
```

Process it from:

```text
/admin/email-queue
```

## Failure isolation

Manual approval:

```text
RMA approval remains committed even if Event Bridge notification fails
```

Customer auto-approval:

```text
return remains created/approved
RMA remains valid
return.requested remains independent
notification warning may appear on the customer success page
```

Errors are logged with:

```text
[Alasne rma.approved notification]
```

## Next milestone

After this passes:

```text
store_credit.issued
```

will be the next customer lifecycle event.

## Commit

```powershell
git status
git add .
git commit -m "Wire RMA approved notification event"
git push
```
