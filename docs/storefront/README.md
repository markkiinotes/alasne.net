# Alasne Public Storefront

This directory documents the public-facing customer experience separately
from Mission Control and the commerce engine.

## Phase 1 — Storefront Shell + Catalog Polish

This milestone deliberately changes presentation only.

Changed:

```text
app/Views/layouts/storefront.php
public/assets/css/storefront.css
```

Not changed:

```text
CheckoutController
CheckoutService
CartController
payment services
inventory services
return services/controllers
notification publishers
routes
database schema
```

The goal is to make the existing customer-facing functionality feel like one
cohesive store without creating a second commerce implementation.

## Existing public capabilities underneath the shell

Alasne already has public routes and/or views for:

```text
store home
categories
product detail
cart
checkout
order confirmation
order tracking
receipt
customer account
returns / RMA self-service
```

Phase 1 makes those surfaces share a modern public header, navigation,
responsive design system, accessibility affordances, and footer.

## Public navigation

The shared shell exposes:

```text
Shop
Account
Track Order
Returns
Cart
```

The store name remains dynamic so the shell works for Alasne's multi-store
architecture.

## Design principles

- Store-owned identity; no hard-coded consumer brand.
- No external font or JavaScript dependency.
- Responsive by default.
- Keyboard-visible focus states.
- Skip-to-content link.
- Reduced-motion support.
- Existing CSS class contracts preserved.
- Existing form actions and business logic preserved.

## Important cart badge note

The shared layout shows a numeric cart badge when the controller/view already
passes `cartQuantity`.

Pages that do not currently pass `cartQuantity` still show the Cart link but
do not invent or duplicate cart-state business logic in the layout.

A later storefront milestone can centralize this presentation value cleanly
if needed.

## Next phases

See `ROADMAP.md`.


## Phase 2 — Cart + Checkout Experience

Phase 2 keeps the payment-enabled checkout engine intact and formalizes the
browser journey from Cart through Checkout to Complete.

See:

```text
PHASE-2-CART-CHECKOUT.md
TESTING-CHECKLIST.md
```

The test provider now serves as the official storefront acceptance path for:

```text
Approved
Declined
Provider Error
```

This lets the customer-facing purchase journey be tested without entering
real payment-card data.


## Phase 3 — Customer Account + Order Tracking

Phase 3 productizes the post-purchase customer experience already present in
Alasne.

Delivered:

- secure passwordless customer account access
- polished account dashboard
- customer order history
- customer-safe order detail
- receipt access
- store-credit history
- customer-visible shipment tracking
- public order timeline polish
- tracking-page integration
- customer portal security/correctness hardening

Phase 3 deliberately keeps one-time access-link delivery on the secure direct
email-outbox path. The secret login token is not copied into Event Bridge
payload/audit records.

See:

```text
PHASE-3-CUSTOMER-ACCOUNT-TRACKING.md
TESTING-CHECKLIST.md
```
