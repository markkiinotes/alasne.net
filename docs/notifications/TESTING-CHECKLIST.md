# Notification Testing Checklist

Use this checklist after installing the audit hardening package.

## A. Core rendering tests — can run now

### HTML escaping

Use a Template Center preview payload containing:

```json
{
  "customer_name": "<script>alert('x')</script>"
}
```

Expected HTML rendering contains escaped text similar to:

```text
&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;
```

The template's own `<p>`, `<strong>`, `<a>`, etc. remain HTML.

### Missing variable

Remove one required variable from a dry-run Event Bridge payload.

Expected:

```text
rule status = failed
error mentions missing required template variable(s)
no email_outbox row queued
```

### Admin recipient

For:

```text
Supplier Submission Failed → Admin Notice
```

set a valid **Default Recipient**.

A payload-provided `admin_email` should not replace it.

The manual simulator's explicit Recipient field may still override
deliberately for simulation.

## B. Event Bridge idempotency — can run now

Simulate the exact same event twice with the exact same idempotency key.

Expected:

```text
first call  → completed / dry_run / queued as configured
second call → duplicate
```

No second dispatch should be queued.

## C. Email queue worker claim — can run now

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Queue at least one test notification.

Run the queue processor.

Expected:

```text
pending/failed
    → processing
    → logged
```

A second worker that attempts the same row after the first has claimed it
should skip it.

Optional environment setting:

```dotenv
EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES=30
```

## D. Backend/admin event tests — can run without a finished storefront

- `order.shipped`: use the existing Mission Control fulfillment transition.
- `tracking.updated`: change carrier/tracking state on an already-shipped order.
- `rma.approved`: approve a valid requested return when a suitable test return exists.
- `store_credit.issued`: complete a received return with store credit.
- `purchase_order.created`: route a suitable paid test order to a mapped supplier.
- `supplier_submission.failed`: mark a prepared test supplier submission failed.

Some old development records may not be reusable because their return quantity
or supplier state is already consumed. Do not weaken business rules merely to
reuse old test data.

## E. Integration tests that need customer/storefront work

Mark these as:

```text
DEFERRED — STOREFRONT REQUIRED
```

until the corresponding public-facing flow is built and intentionally tested:

- customer browses catalog and product page
- customer cart
- customer checkout UX
- customer payment UX
- customer account/order history
- customer tracking experience
- customer return/RMA experience

The backend event can be validated independently; that is not the same as
passing the public customer journey.

## F. Live SMTP gate

Only after log-mode review:

1. Choose one low-risk test rule.
2. Use a controlled recipient.
3. Uncheck Dry Run Only.
4. Set `EMAIL_QUEUE_TRANSPORT=smtp`.
5. Queue one message.
6. Process one message.
7. Confirm receipt.
8. Review Mission Control attempt history.
9. Return to log mode while testing other rules if needed.
