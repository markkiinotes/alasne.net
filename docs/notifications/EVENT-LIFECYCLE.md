# Notification Event Lifecycle

Last audited: **2026-08-17**

| Event | Source | Audience | Idempotency |
|---|---|---|---|
| `order.created` | `storefront_checkout` | Customer | `order.created:order_id:<id>` |
| `payment.captured` | `storefront_checkout` | Customer | `payment.captured:payment_transaction_id:<id>` |
| `order.shipped` | `admin_fulfillment` | Customer | `order.shipped:order_id:<id>` |
| `tracking.updated` | `admin_fulfillment` | Customer | Order ID + tracking-state SHA-256 fingerprint |
| `return.requested` | `customer_self_service` | Customer | `return.requested:return_id:<id>` |
| `rma.approved` | `mission_control_returns` / `customer_policy_auto_approval` | Customer | `rma.approved:return_id:<id>` |
| `store_credit.issued` | `return_resolution` | Customer | `store_credit.issued:transaction_id:<id>` |
| `purchase_order.created` | `dropship_fulfillment` | Supplier | `purchase_order.created:purchase_order_id:<id>` |
| `supplier_submission.failed` | `supplier_submission_workflow` | Admin | Submission ID + attempt count |

## Flow

```text
Paid checkout
  ├─ order.created
  ├─ payment.captured
  └─ supplier routing → purchase_order.created
        ↓
Fulfillment
  ├─ order.shipped
  └─ tracking.updated

Customer return
  ├─ return.requested
  ├─ rma.approved
  └─ store_credit.issued (when applicable)

Supplier submission failure
  └─ supplier_submission.failed → internal admin
```

## Rules

1. Publish only after relevant commerce state is durable.
2. Notification failures must not roll back commerce state.
3. Idempotency must identify the business event/attempt.
4. Recipient source must match audience.
5. Event payloads exclude payment credentials, API secrets, and raw supplier request/response payloads.
6. Template-owned HTML may remain raw; payload values inserted into HTML are escaped.
