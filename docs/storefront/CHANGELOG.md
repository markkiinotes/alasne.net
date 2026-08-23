# Storefront Changelog

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
