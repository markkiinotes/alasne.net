# Return Requested Notification Event Integration

## Purpose

This package wires the next real lifecycle event:

```text
return.requested
```

into the existing customer self-service return workflow.

It does not change return eligibility, quantities, policy enforcement, RMA
auto-approval, refunds, inventory, or the ReturnService transaction.

## Existing customer return flow

The working application already does:

```text
Customer looks up paid order
    ↓
Return policy eligibility checked
    ↓
Return items selected
    ↓
ReturnService::create()
    ↓
return header + items created
    ↓
return/order audit events created
    ↓
optional policy auto-approval + RMA
    ↓
database transaction COMMIT
    ↓
CustomerReturnController resumes
```

The new Event Bridge publisher runs only after `ReturnService::create()`
returns successfully, which means the return transaction has already committed.

## New notification flow

```text
Successful customer return request
    ↓
return.requested
    ↓
Notification Event Bridge
    ↓
Return Requested → Customer Receipt
    ↓
Return Request Received template
    ↓
Dispatch Center
    ↓
email_outbox
```

## Legacy email behavior

For a normal requested return:

```text
legacy "requested" email = removed
Event Bridge return.requested = used
```

This prevents duplicate return-receipt messages.

For a policy auto-approved return:

```text
return.requested = Event Bridge
approved/RMA email = existing legacy path temporarily preserved
```

That is intentional. The next milestone is `rma.approved`, which will replace
the remaining legacy approval email.

## Template variables

The seeded Return Request Received template requires:

```text
customer_name
order_number
return_number
return_reason
store_name
```

The publisher supplies all of them plus:

```text
return_id
return_status
request_source
return_reason_code
requested_refund_amount
currency
order_id
customer_id
customer_email
store_id
store_slug
rma_number
created_at
```

## Customer-content safety

`return_reason` is generated from the system-owned reason code:

```text
damaged → Damaged
defective → Defective
wrong_item → Wrong item
not_as_described → Not as described
changed_mind → Changed mind
other → Other
```

Raw customer notes and free-form reason details are intentionally not included
in this notification event payload.

## Duplicate protection

The Event Bridge idempotency key is:

```text
return.requested:return_id:RETURN_ID
```

The same return cannot queue a second receipt through this event.

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
Return Requested → Customer Receipt
Enabled: checked
Dry Run Only: checked
```

For the cleanest first test, use a store policy where customer return requests
are NOT automatically approved.

From the storefront, submit a new customer return request.

Expected customer behavior:

```text
return request success page displays normally
return number is created
return remains requested
```

Expected Event Bridge:

```text
event_key = return.requested
event_source = customer_self_service
status = completed
dry_run = 1
queued = 0
```

No requested-return email should be queued while the rule is dry-run.

## Auto-approved policy note

If the store policy auto-approves customer returns, the Event Bridge should
still capture `return.requested`.

The existing approved/RMA email may also run because `rma.approved` has not
yet been migrated to the Event Bridge. That is expected for this milestone.

## Live queue test

After dry-run passes:

```text
Return Requested → Customer Receipt
Enabled: checked
Dry Run Only: unchecked
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Submit a DIFFERENT new customer return request.

Expected Event Bridge:

```text
return.requested = queued
Dispatch ID present
Outbox ID present
```

Then open:

```text
/admin/email-queue
```

and process the receipt manually.

## Safety behavior

If Event Bridge notification processing fails:

```text
the return remains created
the customer success flow continues
the error is logged
a notification warning may appear on the success page
```

The log prefix is:

```text
[Alasne return.requested notification]
```

## Admin-created returns

Mission Control's admin "Create Return" path is intentionally unchanged in
this milestone. The seeded return.requested automation is specifically a
customer receipt after a customer self-service submission.

## Next milestone

After this passes:

```text
rma.approved
```

will replace the remaining legacy approval/RMA customer notification.

## Commit

```powershell
git status
git add .
git commit -m "Wire customer return requested notification event"
git push
```
