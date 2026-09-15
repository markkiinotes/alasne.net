# Supplier Submission Failed Notification Event Integration

## Purpose

This package wires the final seeded operational event:

```text
supplier_submission.failed
```

into the existing supplier-submission workflow.

The alert is internal to Mission Control. It is not sent to the customer or
supplier.

## Existing supplier workflow preserved

The current supplier integration model remains:

```text
Purchase order created
    ↓
Supplier submission prepared
    ↓
Submission status updated
    ↓
supplier_order_submissions updated
    ↓
purchase_orders.submission_status mirrored
    ↓
supplier_submission.failed Event Bridge (only when status = failed)
```

No supplier adapter, provider registry, PO schema, supplier schema, export
format, or routing logic is changed.

## Trigger point

`SupplierSubmissionService::markStatus()` already performs two writes:

```text
1. supplier_order_submissions
2. purchase_orders submission mirror
```

The new event publisher runs only after both writes succeed.

If either write throws, the Event Bridge publisher is never reached.

## Attempt-level idempotency

One supplier submission record may be retried and fail more than once.

The existing repository tracks:

```text
attempt_count
```

and the Mission Control status action increments that count for:

```text
processing
submitted
succeeded
failed
```

The failure event therefore uses:

```text
supplier_submission.failed:submission_id:SUBMISSION_ID:attempt:ATTEMPT_COUNT
```

This gives the desired behavior:

```text
submission 42, attempt 1 failed → one alert
same failed state reprocessed as same attempt → blocked
submission 42, attempt 2 failed → new alert
```

## Event source

```text
supplier_submission_workflow
```

## Seeded automation rule

```text
Supplier Submission Failed → Admin Notice
event_key = supplier_submission.failed
template = Failed Supplier Submission Notice
audience = admin
recipient source = admin_default_recipient
```

## Important: Default Recipient

Before testing, open:

```text
/admin/notification-automations
```

Find:

```text
Supplier Submission Failed → Admin Notice
```

Enter a valid internal email address in:

```text
Default Recipient
```

For example, use the Mission Control operations mailbox you want to receive
supplier-failure alerts.

Do not use the supplier or customer email as this rule's default recipient.

## Template variables

The seeded Failed Supplier Submission Notice requires:

```text
order_number
supplier_name
error_message
workflow_url
```

All four are supplied.

Additional operational payload fields include:

```text
supplier_submission_id
attempt_count
submission_status
previous_submission_status
provider_code
channel
external_order_id
purchase_order_id
purchase_order_number
purchase_order_submission_status
order_id
supplier_id
supplier_code
store_id
store_name
failed_at
```

## Workflow URL

The notification links directly to:

```text
/admin/supplier-submissions/SUBMISSION_ID
```

using Alasne's existing:

```php
app_url()
```

helper to generate the absolute URL used in email.

## Data safety

The event does NOT expose:

```text
payload_json
response_json
supplier API credentials
API keys
API secrets
authorization headers
customer address
customer email
payment data
payment tokens
```

The recorded error message is stripped of markup/control characters and
limited to a safe operational length before it enters the notification
template.

If a failure is recorded without an error message, the event supplies:

```text
Supplier submission was marked failed without a recorded error message.
```

so the admin alert is never silently suppressed just because the operator or
future adapter did not provide a detailed error.

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
Supplier Submission Failed → Admin Notice

Enabled: checked
Dry Run Only: checked
Default Recipient: your internal test/admin email
```

Then open a prepared supplier submission:

```text
/admin/supplier-submissions
```

Choose a test submission and update it to:

```text
Status: failed
Error Message: Test supplier rejection - dry run
```

Save.

Expected supplier result:

```text
supplier_order_submissions.status = failed
attempt_count increments
error_message saved
purchase_orders.submission_status = failed
```

Expected Event Bridge:

```text
event_key = supplier_submission.failed
event_source = supplier_submission_workflow
status = completed
dry_run = 1
queued = 0
```

Expected payload includes:

```text
order_number
supplier_name
error_message
workflow_url
attempt_count
purchase_order_number
provider_code
```

## Retry test

Take a submission through a later retry/status attempt and fail it again.

If the attempt count advances:

```text
attempt 1 → failed event
attempt 2 → failed event
```

Both are legitimate alerts because they represent separate recorded attempts.

Event Bridge still prevents duplicate handling of either exact attempt.

## Live queue test

After dry-run passes:

```text
Supplier Submission Failed → Admin Notice
Enabled: checked
Dry Run Only: unchecked
Default Recipient: internal operations email
```

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Fail a DIFFERENT test attempt.

Expected:

```text
supplier_submission.failed = queued
Dispatch ID present
Outbox ID present
```

Process it from:

```text
/admin/email-queue
```

Review the message before switching this admin rule to SMTP.

## Failure isolation

If the Event Bridge or email pipeline has a problem:

```text
supplier submission remains failed
attempt_count remains recorded
purchase order remains marked failed
```

The notification error is logged with:

```text
[Alasne supplier_submission.failed notification]
```

## Regression checks

Confirm after installation:

```text
supplier submission preparation still works
CSV export still works
status changes still work
attempt count still increments as before
purchase order submission status still mirrors
successful statuses do not fire failure alerts
failed status fires one event per attempt
notification problems do not undo supplier state
```

## Event Bridge lifecycle after this package

```text
order.created
payment.captured
order.shipped
tracking.updated
return.requested
rma.approved
store_credit.issued
purchase_order.created
supplier_submission.failed
```

At this point all seeded live lifecycle/operational events are wired.

## Recommended next milestone

Do not add another event immediately.

Run a production-readiness notification audit covering:

```text
full lifecycle dry runs
idempotency
legacy notification overlap
HTML variable escaping
admin/supplier/customer recipient rules
log-mode queueing
SMTP delivery
failure recovery
audit history
```

## Commit

```powershell
git status
git add .
git commit -m "Wire supplier submission failed notification event"
git push
```
