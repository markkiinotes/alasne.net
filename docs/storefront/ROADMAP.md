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

Status: package built / acceptance test next

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

No new migration and no checkout/payment behavior changes.

## Phase 4 — Returns + RMA Customer Experience

Goals:

- returns policy presentation
- order lookup for return eligibility
- return request workflow
- authorization/RMA presentation
- return tracking
- shipping-label/authorization UX
- store-credit resolution messaging

Business eligibility and quantity rules remain server-owned.

## Phase 5 — Storefront Launch Audit

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
