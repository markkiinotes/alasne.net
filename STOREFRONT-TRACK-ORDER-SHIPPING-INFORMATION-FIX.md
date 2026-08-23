# Storefront Track Order — Shipping Information Fix

## Scope

This is a view-only repair for `app/Views/storefront/track-order.php`.

It fixes PHP `Undefined array key` warnings in the Shipping Information section without changing checkout, payment, order status, fulfillment, timeline, routing, or database logic.

## What changed

The tracking view no longer assumes that `$order` always contains legacy keys such as `first_name`, `last_name`, `email`, `address_line_1`, `state`, and `country`.

The view now resolves shipping/contact display values in this order:

1. Immutable shipping-address snapshot fields, if `$shippingAddress` is supplied by the controller.
2. Current order/customer aliases such as `customer_name`, `customer_email`, and `customer_phone`.
3. Common prefixed shipping/customer aliases.
4. Legacy checkout/customer keys for backward compatibility.
5. A clean em dash (`—`) when no value is available.

Address lines are assembled conditionally so missing components do not create stray commas, blank lines, or warnings.

## Install

From the Alasne project root, back up the current view if desired, then copy the package contents over the project root so this file replaces:

`app/Views/storefront/track-order.php`

No migration is required.

Composer autoload regeneration is not required for a view-only change, but it is safe to run:

```powershell
composer dump-autoload -o
```

## Test

1. Open the storefront order-tracking page.
2. Track the same successful order that previously showed warnings.
3. Confirm Name, Email, Phone, and Address render without PHP warnings.
4. Confirm Order Details, Order Timeline, Print Receipt, and Items Ordered still render normally.

### Expected result

- No `Undefined array key` warnings.
- Name/email/phone display when present in the tracking result.
- Address displays when the current tracking result already exposes address data or when the controller supplies `$shippingAddress`.
- If the current tracking controller does not expose any address data, Address displays `—` rather than a PHP warning.

If Address displays `—` for an order that definitely has an immutable shipping-address snapshot, the next repair is in `app/Controllers/OrderTrackingController.php`: pass `OrderRepository::addressForOrder($orderId, 'shipping')` to the view as `shippingAddress`. This package is already compatible with that future controller change.

## Validation performed

- PHP 8.4 syntax lint: PASS.
- Render test using current customer aliases: PASS.
- Render test using legacy customer/address keys: PASS.
- Render test using immutable shipping-address snapshot keys: PASS.
- No warnings/notices/fatal errors in those render tests.
- ZIP integrity verified.
