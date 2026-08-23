# Notification Event Lifecycle

## Customer/order lifecycle

```text
Order created
    → order.created

Payment captured
    → payment.captured

First shipment
    → order.shipped

Later carrier/tracking change
    → tracking.updated

Return requested
    → return.requested

RMA approved
    → rma.approved

Store credit issued
    → store_credit.issued
```

## Supplier lifecycle

```text
Paid order routed to supplier
    ↓
Purchase order + lines persisted
    ↓
Routing transaction COMMIT
    ↓
purchase_order.created

Supplier submission attempted / status updated
    ↓
submission row saved
    ↓
purchase order submission mirror saved
    ↓
if status = failed
    ↓
supplier_submission.failed
```

## Idempotency strategy

The database has a unique Event Bridge run key. Publishers also provide
business-specific keys.

Current publisher strategy:

```text
order.created
  order.created:order_id:<id>

payment.captured
  payment.captured:payment_transaction_id:<id>

order.shipped
  order.shipped:order_id:<id>

tracking.updated
  tracking.updated:order_id:<id>:<tracking-state-fingerprint>

return.requested
  return.requested:return_id:<id>

rma.approved
  rma.approved:return_id:<id>

store_credit.issued
  store_credit.issued:transaction_id:<id>

purchase_order.created
  purchase_order.created:purchase_order_id:<id>

supplier_submission.failed
  supplier_submission.failed:submission_id:<id>:attempt:<attempt_count>
```

This audit also hardens the race where two identical events arrive at nearly
the same time. The database unique key remains the final authority.

## Recipient ownership

```text
customer_email
    → resolved from event payload

supplier_email
    → resolved from event payload

admin_default_recipient
    → resolved from the automation rule's configured Default Recipient
```

An admin event payload does not get to redirect an operations alert to a
payload-provided address. The manual Event Bridge simulator may still supply
an explicit recipient deliberately.

## Rendering ownership

```text
Template HTML
    → trusted/template-owned markup

Payload values inserted into HTML
    → HTML escaped

Plain-text body
    → plain text

Subject
    → plain text + CR/LF collapsed
```
