# Phase 3 — Customer Account + Order Tracking

## Purpose

Turn the existing customer portal and public tracking surfaces into a cohesive
post-purchase customer experience without rebuilding authentication, checkout,
payments, returns, or fulfillment.

## Existing architecture preserved

The existing portal already provides:

```text
email + postal-code account lookup
single-use 30-minute access token
8-hour store-specific customer session
order ownership checks
public-only order timeline
store-credit account/history
returns history
profile/address maintenance
```

Phase 3 builds on those contracts.

## Security and correctness hardening

### Store-bound one-time links

Before this milestone, a valid token could be looked up and marked used before
the controller rejected a mismatched store URL.

Now `consumeToken()` receives the expected store ID and verifies it before the
token is consumed.

Result:

```text
valid token + correct store URL
    → token claimed once
    → login succeeds

valid token + wrong store URL
    → rejected
    → token remains usable at its correct store URL
```

### Atomic single-use claim

`markTokenUsed()` now returns success only when its conditional UPDATE changes
one row.

Two simultaneous requests cannot both authenticate with the same one-time
token.

### Session rotation

A successful customer-portal login rotates the PHP session identifier while
preserving session data.

### CSRF-protected sign out

The account Sign Out POST now carries and validates the existing CSRF token.

### Failed checkout attempts

Phase 2 proved that Declined and Provider Error attempts intentionally create
failed/cancelled payment records for auditability while preserving the cart and
inventory.

Those records are operational payment attempts, not purchases.

Customer account:

```text
order history
order count
last order
```

now exclude orders whose `payment_status = failed`.

Account "Net paid" uses:

```text
amount_paid - amount_refunded
```

floored at zero per order.

### Customer-safe shipment data

The account portal no longer selects or renders supplier identity from internal
purchase orders.

Customer shipment data is limited to:

```text
shipment sequence
status / tracking status
carrier
tracking number / URL
expected ship time
shipped time
delivered time
```

Not exposed:

```text
supplier name/code/id
supplier reference
supplier cost
estimated profit
provider/integration metadata
submission payloads/responses
```

## Magic-link email boundary

The one-time login URL is intentionally delivered directly through
`email_outbox`.

It is not copied into the generic Notification Event Bridge because the URL
contains a secret bearer token.

This still benefits from the hardened email-queue claim/retry pipeline.

## No migration

Migration `000044` already established the customer portal schema.

This milestone does not add or change database schema.
