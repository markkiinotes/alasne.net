# Alasne Notification & Automation

This directory is the Git-tracked operating record for the Mission Control
notification pipeline.

Last production-readiness audit: **2026-08-23**

## Current architecture

```text
Business event
    ↓
Notification Event Publisher
    ↓
MissionControlNotificationEventBridgeService
    ↓
Notification Automation Rule
    ↓
Notification Template
    ↓
Notification Dispatch Center
    ↓
email_outbox
    ↓
MissionControlEmailQueueService
    ↓
log / SMTP
```

## Wired event inventory

| Event | Intended audience | Live publisher wired | Notes |
|---|---|---:|---|
| `order.created` | customer | Yes | Checkout integration exists; full customer-journey UI test is deferred until storefront work. |
| `payment.captured` | customer | Yes | Payment-success publisher is wired. |
| `order.shipped` | customer | Yes | First shipment transition owns this event. |
| `tracking.updated` | customer | Yes | Subsequent carrier/tracking changes own this event. |
| `return.requested` | customer | Yes | Customer return controller publisher is wired; polished public returns UI is deferred. |
| `rma.approved` | customer | Yes | Manual approval and policy auto-approval publishers are wired. |
| `store_credit.issued` | customer | Yes | Published after durable store-credit issuance. |
| `purchase_order.created` | supplier | Yes | Published after supplier-routing transaction commits. |
| `supplier_submission.failed` | admin | Yes | Published after failed submission state and PO mirror are persisted. |

## Audit hardening in this package

- HTML template payload values are escaped while template-owned HTML remains raw.
- Subject/header values have CR/LF collapsed after rendering.
- Declared variables and actual `{template_variables}` are validated together.
- Live dispatch and Event Bridge dry runs fail when required variables are absent.
- `admin_default_recipient` rules use the rule's configured Default Recipient rather than event-payload email fields.
- Event Bridge handles simultaneous duplicate events cleanly against the database unique idempotency key.
- Mission Control email queue workers atomically claim rows before processing.
- Stale `processing` rows become retryable after a configurable lease timeout.

## Important scope boundary

The backend and Mission Control infrastructure are substantially ahead of the
public storefront. A true customer browser journey is **not** a prerequisite
for this audit.

Tests that require a finished public storefront are marked:

```text
DEFERRED — STOREFRONT REQUIRED
```

They must not be reported as passed until the public-facing flow actually
exists and is exercised.

See:

- `EVENT-LIFECYCLE.md`
- `PRODUCTION-READINESS.md`
- `TESTING-CHECKLIST.md`
- `GIT-CHECKLIST.md`
- `CHANGELOG.md`
