# Phase 2 — Cart + Checkout Experience

## Scope

Phase 2 turns the existing cart/payment flow into a deliberate customer
journey while preserving the working transaction engine.

Changed customer-facing files:

```text
app/Views/storefront/checkout.php
app/Views/storefront/checkout-success.php
public/assets/css/storefront.css
```

No controller, service, route, repository, or migration is replaced.

## Transaction contract preserved

The checkout form still submits:

```text
_csrf_token
first_name
last_name
email
phone
address_line_1
address_line_2
city
state
postal_code
country
shipping_method_id
payment_method_id
test_scenario
```

The existing controller/service remain authoritative for:

- CSRF validation
- cart validation
- shipping-method validation
- tax calculation
- payment processing
- paid/failed order status
- inventory deduction
- cart clearing/preservation
- order-created/payment-captured notification events
- downstream supplier routing

## UX improvements

### Cart

The existing cart view inherits improved:

- line-item cards
- quantity input focus state
- Update button
- Remove button
- line totals
- sticky order summary
- responsive mobile layout

The cart PHP/controller was not replaced.

### Checkout

Added:

- Cart → Checkout → Complete progress indicator
- clearer Contact / Address / Shipping / Payment sections
- stronger selected shipping/payment states
- clearer Development Test Scenario treatment
- improved sticky order summary
- Edit Cart shortcut
- server-verification context
- better responsive behavior

### Confirmation

Added:

- completed checkout progress
- stronger payment-approved confirmation
- Track This Order action
- Continue Shopping action

## Development payment testing

The existing test provider supports:

```text
Approved
Declined
Provider Error
```

No real card information is collected by the development test scenario.

Use all three states during Phase 2 acceptance testing.
