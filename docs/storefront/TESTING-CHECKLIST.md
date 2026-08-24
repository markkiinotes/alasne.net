# Storefront Phase 3 Testing Checklist

No database migration is required.

Use the customer created by a successful Phase 2 storefront checkout.

## A. Account access request

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

For the first test, keeping:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

is safest.

Process or inspect the queued access-link email in Mission Control and open the
one-time URL.

Expected:

```text
account dashboard opens
customer name appears
session is store-specific
```

## B. One-time token

After successful login, open the SAME magic-link URL again.

Expected:

```text
link rejected as invalid or expired
```

The original successful account session should remain usable.

## C. Account dashboard

Verify:

- successful/active order appears
- Phase 2 Declined attempt does NOT appear as a purchase
- Phase 2 Provider Error attempt does NOT appear as a purchase
- order count does not count those failed-payment attempts
- Net Paid reflects paid amount less recorded refunds
- return count renders
- store-credit balance renders
- no PHP warnings/notices

## D. Order detail

Open the successful order.

Verify:

- order number/status/payment/total render
- items render
- order-level carrier/tracking render when present
- customer-visible timeline renders
- Print Receipt opens the existing protected public receipt
- Request Return opens the existing return workflow
- Track Order opens public tracking

If purchase-order shipment records exist:

- cards are named `Shipment 1`, `Shipment 2`, etc.
- carrier/tracking may render
- supplier name/code is NOT displayed
- supplier cost/profit/provider data is NOT displayed

## E. Store credit

Open:

```text
/store/<store_slug>/account/store-credit
```

Expected with no credit:

```text
$0.00
No store-credit activity yet
```

Expected after a recorded store-credit return resolution:

```text
balance
transaction type
amount
balance after
note
```

## F. Profile update

Change a harmless test value such as Phone and save.

Expected:

```text
profile saves
success message appears
CSRF remains valid for later actions
```

Restore the value if desired.

## G. Sign out

Use the account Sign Out button.

Expected:

```text
redirect to customer account login
success message says signed out
dashboard requires a new secure login
```

Sign out is now CSRF-protected.

## H. Public tracking regression

Open:

```text
/store/<store_slug>/track
```

Look up the successful order with order number + email.

Verify:

- no undefined-array-key warnings
- shipping information renders safely
- order totals render
- carrier/tracking render
- public timeline renders
- items render
- Print Receipt works
- My Account link works
- only one global storefront footer appears

## I. Security checks

### Wrong postal code

Request an account link using the correct email but wrong postal code.

Expected:

```text
same generic "check email" style response
no new access-link email for that mismatch
```

### Another customer's order ID

If a second customer test record exists, while signed in as Customer A try:

```text
/store/<store_slug>/account/orders/<customer_B_order_id>
```

Expected:

```text
404 - Order not found
```

Do not weaken ownership filtering to simplify this test.

### Store mismatch token

If a second store exists, open a valid Store A account token under a Store B
session-link URL.

Expected:

```text
Store B rejects it
the token can still be used once at Store A
```

If only one store exists, mark this test:

```text
DEFERRED — SECOND STORE REQUIRED
```

## J. Regression gate

Phase 3 is PASS only if these remain unchanged:

```text
checkout/payment behavior
cart behavior
inventory behavior
order.created/payment.captured events
supplier routing
return/RMA eligibility
public tracking ownership check
email queue transport
```
