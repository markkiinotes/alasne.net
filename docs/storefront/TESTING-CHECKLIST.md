# Storefront Phase 2 Testing Checklist

## Prerequisite

Phase 1 storefront shell should already be installed.

No database migration is required for Phase 2.

Use the development/test payment method for transaction testing. Do not enter
real card data into a simulated provider.

## A. Cart journey

Start at:

```text
/store/<store_slug>
```

1. Open an in-stock product.
2. Add quantity 1 to the cart.
3. Open the Cart link.
4. Verify the product, price, quantity, and line total.
5. Change quantity and use the existing Update action.
6. Confirm subtotal changes correctly.
7. Remove the item.
8. Confirm the empty-cart state.
9. Re-add an item for checkout.

PASS requires the existing cart actions to behave exactly as before the
presentation package.

## B. Checkout presentation

Open:

```text
/store/<store_slug>/checkout
```

Verify:

- progress shows Cart → Checkout → Complete
- Checkout is the current step
- Contact Information is visible
- Shipping Address is visible
- active shipping methods are selectable
- active payment methods are selectable
- selected cards have a clear visual state
- Development Test Scenario appears only for the test provider
- Order Summary lists cart items
- Edit Cart returns to the cart
- selected shipping changes the estimated-before-tax display
- page works at desktop and mobile widths

## C. Approved test transaction

Use a fresh cart/order test.

Select:

```text
Development Test Scenario = Approved
```

Submit checkout.

Expected:

```text
payment approved
checkout success page shown
progress shows Complete
order number shown
order/payment totals shown
cart cleared
order is paid
inventory reduced once for purchased quantity
Track This Order link works
```

Also verify Mission Control records the expected order/payment transaction
and that the notification/Event Bridge behavior remains intact.

## D. Declined test transaction

Use a fresh cart state and select:

```text
Development Test Scenario = Declined
```

Expected:

```text
checkout returns with a payment failure message
cart remains available
inventory is NOT deducted
customer can correct/retry checkout
```

A failed order/payment-attempt record may still exist in Mission Control
because the checkout engine creates the order and payment transaction before
the provider result is finalized. Do not treat that record alone as a bug.

## E. Provider Error test

Select:

```text
Development Test Scenario = Provider Error
```

Expected customer behavior:

```text
checkout displays an error
cart remains available
inventory is NOT deducted
```

Verify the error does not expose credentials, tokens, or a PHP stack trace.

## F. Server-authority checks

The browser estimate is not authoritative.

Confirm on an approved order:

```text
final tax comes from the server
final shipping matches selected active method
final total is server-calculated
inventory changes only after payment approval
```

## G. Regression gate

Phase 2 is PASS only if the following remain unchanged:

```text
CheckoutController behavior
CheckoutService behavior
payment provider behavior
payment transaction records
inventory calculations
order.created event
payment.captured event
supplier routing
return/RMA logic
```

## H. Git

After PASS, follow:

```text
docs/storefront/GIT-CHECKLIST.md
```
