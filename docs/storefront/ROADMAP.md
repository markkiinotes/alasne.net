# Public Storefront Roadmap

## Phase 1 — Storefront Shell + Catalog Polish

Status: complete

Delivered:

- shared public header/footer
- store identity
- responsive navigation
- accessibility baseline
- home/category/product polish
- Git documentation

## Phase 2 — Cart + Checkout Experience

Status: complete / browser validated

Validated:

- approved payment
- declined payment
- provider error
- cart preservation on failure
- inventory preservation on failure
- successful tracking lookup
- tracking shipping-information repair

## Phase 3 — Customer Account + Order Tracking

Status: complete / browser acceptance passed 2026-08-23

Delivered:

- passwordless one-time-link customer login
- store-bound access-token consumption
- atomic single-use token claim
- session ID rotation after authentication
- CSRF-protected sign out
- customer account dashboard
- order history
- failed-payment checkout attempts hidden from purchase history
- net-paid account summary
- order detail
- receipt access
- store credit
- customer-safe shipment tracking
- supplier identity removed from customer shipment data
- public tracking presentation polish
- immutable shipping-address snapshot persistence for new storefront orders
- shipping-method/payment snapshot visibility in Mission Control
- public tracking address sourced from the immutable order snapshot

Acceptance verified:

- real secure-link email delivery and login
- single-use token rejection
- dashboard/order-history data
- customer order detail
- store-credit history
- profile persistence
- secure sign out
- public tracking positive and negative ownership checks
- wrong-postal-code privacy behavior
- cross-customer order ownership protection
- return/RMA eligibility regression
- checkout/payment/inventory and notification-event behavior
- supplier-routing integration boundary preserved

The store-mismatch-token test is deferred until a second store is available.

No new migration was added. Existing `order_addresses` infrastructure is now
used consistently by storefront checkout and public tracking. Payment approval,
refund, cart, and inventory business rules remain unchanged.

## Phase 4 — Returns + RMA Customer Experience

Status: complete / browser acceptance passed 2026-09-11

Delivered:

- public returns policy presentation
- public order lookup for return eligibility
- authenticated My Account direct-return entry
- server-owned eligibility and quantity enforcement
- remaining-returnable-quantity accounting
- return request workflow
- return approval and RMA issuance
- printable return authorization
- manual return-shipping/tracking workflow
- customer-safe return tracking
- package-identification label presentation
- receiving, inspection, restock, and discard workflow
- inventory-ledger restock integration
- return resolution by original-payment refund
- return resolution by store credit
- replacement-resolution infrastructure
- external-payment settlement snapshot repair for new orders
- over-refund prevention
- reconciliation of already-issued successful refunds
- return lifecycle email notifications
- Event Bridge email-outbox order/store/customer metadata propagation
- status-aware return authorization presentation in email and public tracking
- customer account/order timeline synchronization after return completion
- privacy-safe failed return lookup behavior

Acceptance verified:

- ineligible account order blocked before shipment/completion
- authenticated account direct-return entry
- cross-customer order-ID tampering rejected with `404 - Order not found`
- requested quantities reduced by existing open returns
- approval produced an RMA with expiration, address, and instructions
- approved-state public tracking retained authorization and print controls
- manual shipping label/tracking records rendered in Mission Control and storefront
- in-transit and delivered shipment events rendered correctly
- received merchandise recorded correctly
- restocked quantity restored inventory exactly once
- store-credit resolution completed and ledger balance updated
- original-payment refund created a linked successful refund transaction
- payment charge tracked cumulative refunded amount
- partially-refunded order status updated correctly
- duplicate/over-refund attempts blocked by remaining settled amount
- an existing successful refund could be reconciled to a return without issuing a second refund
- new checkout orders persist `external_payment_amount`
- completed-return email reported correct resolution and refund
- completed-return email no longer presents stale shipping instructions
- approved RMA email queued and delivered successfully
- completed public return tracking hides stale authorization/print actions
- completed order timeline shows return completion and refund issuance
- bad return-email credential returned a generic not-found response with no return data exposure

Phase 4 closeout commits:

```text
6f396d2 Complete Phase 4 returns and refund workflow
57ad103 Complete Phase 4 customer return communications and tracking
```

## Phase 5 — Storefront Launch Audit

Status: active / kickoff 2026-09-11

Goals:

- complete browser journey
- mobile/browser regression
- accessibility pass
- SEO metadata
- empty/error/loading states
- performance review
- notification delivery review
- payment/inventory/supplier-routing verification
- return/RMA verification
- production deployment checklist

Planned milestones:

- Phase 5A — documentation alignment + launch baseline
- Phase 5B — complete storefront browser journey
- Phase 5C — mobile/responsive + accessibility regression
- Phase 5D — SEO, empty/error/loading states, and performance
- Phase 5E — payments, inventory, supplier routing, notifications, and returns regression
- Phase 5F — production deployment/readiness checklist
