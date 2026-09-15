# Notification Implementation History

Last updated: **2026-08-17**

## Foundation

1. Email Queue Processing — migration `000049`
2. SMTP Transport
3. Notification Template Center — migration `000050`
4. Notification Dispatch Center — migration `000051`
5. Notification Automation Rules — migration `000052`
6. Notification Event Bridge — migration `000053`

## Live Event Hooks

- `order.created`
- `payment.captured`
- `order.shipped`
- `tracking.updated`
- `return.requested`
- `rma.approved`
- `store_credit.issued`
- `purchase_order.created`
- `supplier_submission.failed`

## Key Decisions

### Checkout
The payment-enabled checkout service remains authoritative. Event publishing is post-commit and failure-isolated. The old private hard-coded order-confirmation method remains as unused legacy code; this hardening release does not modify stable checkout code.

### Fulfillment
First shipment emits `order.shipped`; later carrier/tracking changes emit `tracking.updated`. Non-tracking fulfillment edits remain on the older notification path.

### Returns
Customer submission emits `return.requested`; approval emits `rma.approved`. Non-seeded return lifecycle emails (shipping label, received, completed, refund failed, cancelled) remain on the legacy return notification path. `store_credit.issued` is a dedicated balance notice and intentionally coexists with the generic Return Completed summary.

### Supplier Operations
`purchase_order.created` fires only for newly inserted POs after routing commits. `supplier_submission.failed` is one alert per failed submission attempt.

## 2026-08-17 Production Hardening
- HTML-escape payload values in HTML templates.
- Normalize rendered subjects to one safe line.
- Merge declared variables with actual template placeholders.
- Block live queueing when template variables are missing.
- Fail dry-runs when required variables are missing.
- Prefer configured admin default recipient over event payload recipient fields.
- Add Git-tracked lifecycle/audit/deployment documentation.
