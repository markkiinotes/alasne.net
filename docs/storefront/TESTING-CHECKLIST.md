# Storefront Acceptance Testing Checklist

## Phase 3 — Customer Account + Order Tracking

Status: PASS / completed 2026-08-23

The detailed Phase 3 acceptance included:

- passwordless account login through real email delivery
- one-time token reuse rejection
- dashboard/order-history correctness
- customer order detail and protected receipt
- store-credit history
- profile persistence
- secure sign out
- public order-tracking positive/negative ownership checks
- wrong-postal-code privacy behavior
- cross-customer order ownership protection
- immutable shipping-address snapshot repair
- checkout/payment/inventory regression
- supplier-routing integration boundary preservation

Deferred:

```text
Store mismatch token — SECOND STORE REQUIRED
```

## Phase 4 — Returns + RMA Customer Experience

Status: PASS / completed 2026-09-11

### A. Account return eligibility and request — PASS

Validated:

- authenticated customer could enter return flow directly from My Account
- customer did not need to re-enter order number/email
- unshipped/ineligible order was rejected by server eligibility
- shipped eligible order displayed return deadline
- purchased quantity displayed correctly
- already-requested quantity displayed correctly
- available-to-return quantity displayed correctly
- cross-customer `order_id` tampering returned `404 - Order not found`
- submission of one unit created a return request
- reopening the return form reduced remaining available quantity

### B. Approval and RMA — PASS

Validated:

- Mission Control return approval succeeded
- RMA number was generated
- authorization issue/expiration timestamps were stored
- return address displayed correctly
- shipping responsibility displayed correctly
- customer instructions displayed
- printable authorization rendered customer/order/RMA/item data
- approved-state public tracking retained authorization details and print action

### C. Return shipment and tracking — PASS

Validated:

- manual carrier/tracking fallback worked without EasyPost
- carrier/service/tracking number could be saved
- package-identification label rendered
- `label_ready` event rendered
- `in_transit` event rendered
- `delivered` event rendered
- customer-facing tracking showed shipment status/history
- completed/received-state tracking no longer exposes stale package-label action

### D. Receiving and inventory — PASS

Validated:

- received quantity recorded
- condition recorded
- restock/discard quantities enforced
- one returned unit restored one inventory unit
- `return_restock` inventory-ledger movement was created
- receiving did not double-adjust already-restocked inventory during resolution

### E. Store-credit resolution — PASS

Validated:

- approved return value allocated to store credit
- return completed
- store-credit balance updated
- customer-facing resolution reflected store credit correctly
- no external refund was created for the store-credit-only resolution

### F. Original-payment settlement and refund — PASS

Validated with a fresh post-fix checkout order:

```text
grand_total                  60.94
amount_paid                  60.94
store_credit_applied_amount   0.00
external_payment_amount      60.94
amount_refunded               0.00 before return refund
```

Return resolution produced:

```text
external payment refund      49.99
refund status                succeeded
```

Payment ledger verified:

```text
charge #31  succeeded  60.94  refunded_amount 49.99
refund #32  succeeded  49.99  parent_transaction_id 31
```

Order state verified:

```text
amount_refunded          49.99
external_payment_amount  60.94
payment_status           partially_refunded
```

### G. Over-refund protection — PASS

Validated:

- prior successful refund reduced remaining settled amount
- second refund attempt above the remaining settled amount was rejected
- return workflow did not duplicate the already-issued payment refund

### H. Existing-refund reconciliation — PASS

Validated:

- a successful admin-side refund already existed
- return workflow found the matching unlinked successful refund
- existing refund transaction was linked to the return
- no second refund transaction was created
- return completed with the existing refund
- activity recorded `Existing refund reconciled`

### I. Return communications — PASS

Validated:

- return-request email queued/sent
- received-merchandise email queued/sent
- completed-return email queued/sent
- completed-return email showed correct refund amount and status
- completed/received email no longer presented stale shipping instructions
- approved RMA notification queued through Event Bridge
- queued RMA notification was delivered successfully
- Event Bridge outbox path now preserves order/store/customer metadata for new messages

### J. Public return tracking — PASS

Completed return:

- status `Completed`
- correct order number
- correct approved value
- refund status `Succeeded`
- RMA retained as historical reference
- resolution `Refund`
- external-payment refund `$49.99`
- returned item/quantities correct
- customer-safe activity timeline correct
- stale authorization instructions hidden
- stale Print Return Authorization action hidden

Approved return regression:

- status `Approved`
- RMA visible
- expiration visible
- return address visible
- shipping instructions visible
- Print Return Authorization visible

### K. My Account synchronization — PASS

Validated:

- original fulfillment status remained historically accurate
- order timeline displayed `Return completed`
- order timeline displayed `Refund issued`
- customer-facing refund amount was `$49.99`
- original order total remained unchanged
- internal transaction/provider identifiers were not exposed

### L. Privacy regression — PASS

Validated with:

```text
valid return number + incorrect email address
```

Expected and observed:

```text
generic matching-return failure
no customer name
no order details
no RMA details
no refund details
no returned-item details
no return activity
```

## Phase 4 result

```text
PASS — STOREFRONT PHASE 4 COMPLETE
```

Closeout commits:

```text
6f396d2 Complete Phase 4 returns and refund workflow
57ad103 Complete Phase 4 customer return communications and tracking
```

## Phase 5 — Storefront Launch Audit

Status: ACTIVE

### Phase 5A — Documentation alignment + launch baseline

- [x] Phase 4 code committed and pushed
- [x] Phase 4 customer acceptance completed
- [x] working tree clean at Phase 5 kickoff
- [x] branch confirmed as `feature/returns-and-restocking`
- [ ] documentation closeout staged/committed
- [ ] launch-audit baseline recorded

### Phase 5B — Complete browser journey

- [ ] storefront landing/catalog
- [ ] category/product detail
- [ ] cart
- [ ] checkout success
- [ ] checkout decline/provider failure
- [ ] customer account login
- [ ] account dashboard/order history
- [ ] public order tracking
- [ ] return request
- [ ] return tracking
- [ ] receipt/authorization print views

### Phase 5C — Responsive + accessibility

- [ ] mobile navigation
- [ ] cart/checkout mobile layout
- [ ] account/tracking mobile layout
- [ ] returns mobile layout
- [ ] keyboard-only journey
- [ ] focus visibility
- [ ] form labels/errors
- [ ] reduced-motion behavior
- [ ] contrast spot check

### Phase 5D — SEO / states / performance

- [ ] page titles
- [ ] meta descriptions where appropriate
- [ ] canonical/robots behavior
- [ ] empty catalog/cart/account states
- [ ] not-found/error states
- [ ] loading/submission states
- [ ] duplicate form submission protection
- [ ] basic response/render performance review

### Phase 5E — Business-flow regression

- [ ] payment approval/decline/provider error
- [ ] inventory sale/restock behavior
- [ ] supplier-routing boundary
- [ ] notification delivery
- [ ] return/RMA lifecycle
- [ ] over-refund protection
- [ ] customer privacy/ownership

### Phase 5F — Production readiness

- [ ] environment/config review
- [ ] production URL/base-path review
- [ ] HTTPS/security headers
- [ ] mail transport configuration
- [ ] payment provider production configuration
- [ ] carrier integration configuration
- [ ] cron/scheduled worker review
- [ ] logging/error-display review
- [ ] database backup/migration plan
- [ ] deployment/rollback checklist
