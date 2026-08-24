# Storefront Phase 3 Testing Checklist

Phase 3 browser acceptance completed on 2026-08-23.

No new database migration was required. The acceptance repair uses the existing
`order_addresses` table for immutable shipping snapshots.

Use the customer created by a successful Phase 2 storefront checkout.

## A. Account access request — PASS

Open:

```text
/store/<store_slug>/account
```

Enter the same:

```text
email
postal code
```

used on the successful order.

Expected:

```text
Check your email
generic privacy-safe response
no indication that another email does/does not exist
```

Validated:

- generic privacy-safe response rendered
- secure account-link email entered the email outbox
- real SMTP delivery succeeded
- one-time URL opened the correct store-bound customer dashboard

## B. One-time token — PASS

After successful login, open the SAME magic-link URL again.

Expected:

```text
link rejected as invalid or expired
```

Validated:

- reused link was rejected
- the original authenticated session remained usable
- after sign out, the used link could not restore access
- a newly requested secure link established a fresh session

## C. Account dashboard — PASS

Verified:

- successful/active orders appear
- failed-payment checkout attempts are excluded from purchase history
- failed-payment attempts do not inflate order count
- Net Paid reflects paid amount less recorded refunds
- return count renders
- store-credit balance renders
- no PHP warnings/notices observed

## D. Order detail — PASS

Verified:

- order number/status/payment/total render
- items render
- order-level carrier/tracking render when present
- customer-visible timeline renders
- Print Receipt opens the protected public receipt
- Request Return opens the existing return workflow
- customer-facing controls do not expose Mission Control fulfillment actions
- supplier identity/cost/provider details are not exposed

## E. Store credit — PASS

Verified:

- available balance renders correctly
- return-credit issuance history renders
- checkout-redemption history renders
- running balance remains consistent

## F. Profile update — PASS

Verified:

- profile save succeeded
- success message appeared
- updated value remained after browser refresh
- later CSRF-protected account actions remained usable

## G. Sign out — PASS

Verified:

- sign out redirected to customer account login
- signed-out success message appeared
- browser Back did not restore the authenticated dashboard
- direct account access required a new secure login
- previously consumed secure link remained invalid

## H. Public tracking regression — PASS

Open:

```text
/store/<store_slug>/track
```

Verified with a fresh paid order:

- no undefined-array-key warnings
- order totals render
- carrier/tracking state renders safely
- public timeline renders
- items render
- Print Receipt works
- My Account link works
- immutable shipping-address snapshot renders correctly
- wrong email with a valid order number returns a generic not-found response
- no customer/order detail is exposed on a failed ownership lookup

### Shipping-snapshot acceptance repair

Acceptance exposed an older gap: checkout-created orders were not always
persisting an immutable shipping-address row even though the schema already
supported it.

The repair now ensures:

```text
paid storefront order
    -> shipping-method snapshot stored on orders
    -> immutable shipping-address snapshot stored in order_addresses
    -> Mission Control invoice/packing data reads the snapshot
    -> public tracking reads the same immutable snapshot
```

Historical orders that never received a snapshot are not silently rewritten
from the customer's current profile.

## I. Security checks

### Wrong postal code — PASS

Validated:

```text
correct email + wrong postal code
    -> same generic "check email" response
    -> no new secure-link email queued
```

### Another customer's order ID — PASS

Validated while signed in as Customer A:

```text
/store/<store_slug>/account/orders/<customer_B_order_id>
    -> 404 - Order not found
```

Ownership filtering was not weakened.

### Store mismatch token — DEFERRED

```text
DEFERRED — SECOND STORE REQUIRED
```

The store-bound token implementation remains in place; browser validation will
be completed when a second storefront test store is available.

## J. Regression gate — PASS

Validated or preserved during Phase 3 acceptance:

```text
checkout/payment behavior              PASS
cart behavior                          PASS
inventory behavior                     PASS
order.created/payment.captured events  PASS
supplier routing boundary              PASS
return/RMA eligibility                 PASS
public tracking ownership check        PASS
email queue transport                  PASS
```

Supplier note: the current test configuration generated no purchase order for
the acceptance order. Phase 3 did not add or alter supplier-routing rules, and
the post-payment notification/event publication boundary remains intact.

Return/RMA note: a paid order that had not yet shipped or completed was
correctly rejected with the existing eligibility rule.

## Phase 3 result

```text
PASS — STOREFRONT PHASE 3 COMPLETE
```

Next milestone:

```text
Phase 4 — Returns + RMA Customer Experience
```
