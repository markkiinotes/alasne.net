# Phase 3 — Customer Account + Order Tracking

Status: COMPLETE — browser acceptance passed 2026-08-23

## Purpose

Turn the existing customer portal and public tracking surfaces into a cohesive
post-purchase customer experience without rebuilding authentication, payments,
returns, or fulfillment.

## Existing architecture preserved

The existing portal provides:

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

`consumeToken()` receives the expected store ID and verifies it before the token
is consumed.

Result:

```text
valid token + correct store URL
    -> token claimed once
    -> login succeeds

valid token + wrong store URL
    -> rejected
    -> token remains usable at its correct store URL
```

### Atomic single-use claim

`markTokenUsed()` returns success only when its conditional UPDATE changes one
row. Two simultaneous requests cannot both authenticate with the same one-time
token.

### Session rotation

A successful customer-portal login rotates the PHP session identifier while
preserving session data.

### CSRF-protected sign out

The account Sign Out POST carries and validates the existing CSRF token.

Browser acceptance confirmed that sign out invalidates account access and that
browser Back or a consumed magic link cannot restore the signed-out session.

### Failed checkout attempts

Declined and Provider Error attempts remain operational payment records for
auditability rather than purchases.

Customer account:

```text
order history
order count
last order
```

exclude orders whose `payment_status = failed`.

Account Net Paid uses:

```text
amount_paid - amount_refunded
```

floored at zero per order.

### Customer-safe shipment data

The account portal does not expose supplier identity from internal purchase
orders.

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

## Immutable order shipping snapshot

Phase 3 acceptance uncovered a pre-existing checkout gap: a new paid storefront
order could have valid shipping/customer data and a shipping charge while still
lacking its immutable `order_addresses` shipping snapshot.

That was corrected during acceptance.

For new storefront orders, checkout now persists the shipping-address snapshot
using the existing `order_addresses` schema. Mission Control invoice/packing
surfaces and public order tracking read that immutable order-time snapshot.

This prevents a later customer profile edit from changing the historical ship-to
address shown for an earlier order.

Historical orders that never received a snapshot remain unchanged rather than
being backfilled from mutable profile data.

## Payment and shipping-method visibility

The order repository now exposes the newer payment and shipping-method snapshot
fields required by Mission Control. Acceptance verified:

```text
Payment Status: Paid
payment method/provider
amount paid/refundable
paid timestamp
shipping method name/code
shipping estimate
shipping charge
```

The underlying payment approval, refund, cart, and inventory business rules were
not changed by this repair.

## Public tracking ownership and address behavior

Public tracking continues to require the matching store, order number, and
customer email.

Acceptance verified:

```text
valid order + matching email
    -> public order details shown
    -> immutable shipping snapshot shown

valid order + wrong email
    -> generic not-found response
    -> no customer/order data exposed
```

## Magic-link email boundary

The one-time login URL is intentionally delivered directly through
`email_outbox`.

It is not copied into the generic Notification Event Bridge because the URL
contains a secret bearer token.

This still benefits from the hardened email-queue claim/retry pipeline.

## Acceptance summary

Passed:

- secure-link request and real email delivery
- one-time token enforcement
- dashboard/order history
- customer order detail
- receipt access
- store-credit balance/history
- profile persistence
- secure sign out
- public tracking positive lookup
- public tracking wrong-email ownership check
- wrong-postal-code account privacy check
- cross-customer account-order ownership check
- return/RMA eligibility regression
- checkout/payment/inventory regression
- post-payment notification event publication

Deferred:

```text
store-mismatch-token browser test — second store required
```

## Database impact

No new migration was added for Phase 3 closeout.

The acceptance repair uses the already-existing `order_addresses` table and
shipping-method/payment snapshot columns.

## Result

```text
STOREFRONT PHASE 3 COMPLETE
```

Next:

```text
Phase 4 — Returns + RMA Customer Experience
```
