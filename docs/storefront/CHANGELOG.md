# Storefront Changelog

## 2026-08-23 — Phase 3 acceptance closeout

### Browser acceptance completed

- passwordless account access validated through real email delivery
- single-use token behavior validated
- customer dashboard/order history validated
- order detail, receipt, store credit, profile persistence, and sign out validated
- public tracking positive/negative ownership paths validated
- wrong-postal-code privacy behavior validated
- cross-customer order ownership returned `404 - Order not found`
- store-mismatch-token browser test deferred until a second store exists

### Acceptance repairs

- storefront checkout now persists the immutable shipping-address snapshot in the existing `order_addresses` table
- Mission Control order reads include current payment and shipping-method snapshot fields
- order item SKU compatibility normalized for storefront-created orders
- public tracking now reads the immutable shipping snapshot instead of depending on mutable customer-profile address data

### Regression gate

- checkout/payment rules preserved
- cart behavior preserved
- inventory behavior preserved
- `order.created` / `payment.captured` publication preserved
- supplier-routing integration boundary preserved
- existing return/RMA eligibility preserved
- public tracking ownership enforcement preserved
- email queue transport preserved

### Database impact

- no new migration
- existing order-address and order snapshot schema used consistently

## 2026-08-23 — Phase 3: Customer Account + Order Tracking

### Added / improved

- polished passwordless account access
- account dashboard and order-history experience
- customer order detail and receipt access
- store-credit presentation
- shipment cards and public timeline
- public tracking/account cross-navigation
- responsive account/tracking styling

### Security hardening

- one-time login token verified against route store before consumption
- atomic single-use token claim
- session identifier rotation after successful login
- CSRF-protected customer sign out
- CR/LF-normalized secure-link email subject
- HTML-safe secure-link email values

### Correctness / privacy

- failed payment checkout attempts excluded from customer purchase history
- Net Paid summary uses paid minus refunded amount
- supplier identity removed from customer-facing shipment query
- supplier costs/profit/provider/integration data not selected for customer shipment view

### Preserved

- migration 000044
- account route contracts
- email + postal lookup
- 30-minute one-time token lifetime
- 8-hour customer portal session
- order ownership filtering
- public-only order timeline
- checkout/payment/inventory behavior
- return/RMA business rules

### No migration

Phase 3 is code, presentation, security, and documentation only.

## 2026-08-23 — Phase 2: Cart + Checkout Experience

### Added

- checkout progress indicator
- numbered checkout sections
- improved shipping/payment selection presentation
- checkout Edit Cart shortcut
- server-verification context
- checkout confirmation completion state
- Continue Shopping confirmation action
- browser acceptance plan for Approved / Declined / Provider Error

### Improved

- cart line-item presentation through existing CSS contracts
- cart actions and summary
- checkout form hierarchy
- checkout responsive behavior
- payment test-mode visibility
- order-summary readability
- successful-order confirmation

### Preserved

- checkout form field names
- checkout route/action
- CSRF token contract
- shipping/payment IDs
- test scenario values
- controller behavior
- payment service/provider behavior
- inventory rules
- notification events
- supplier routing

### Not changed

- controllers
- services
- repositories
- routes
- database schema

## 2026-08-23 — Phase 1: Storefront Shell + Catalog Polish

### Added

- shared public storefront header
- store-aware brand identity
- Shop / Account / Track / Returns / Cart navigation
- responsive CSS-only mobile navigation
- shared public footer
- skip-to-content accessibility link
- visible keyboard focus treatment
- reduced-motion support
- Git-tracked storefront roadmap and testing documentation

### Improved

- storefront hero presentation
- category cards
- product cards
- stock pills
- product-detail presentation
- cart/checkout surface compatibility styling
- form focus styling
- responsive layouts
- empty-state presentation

### Preserved

- existing storefront CSS class contracts
- current catalog routes
- current cart actions
- current checkout/payment engine
- inventory behavior
- notification automation
- customer account services
- order tracking
- return/RMA services
- supplier routing

### Not included

- new payment logic
- new cart business logic
- new return logic
- database migration
- route replacement

Those areas move through later storefront phases.
