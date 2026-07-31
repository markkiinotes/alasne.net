# Alasne Store Credit Redemption at Checkout

## Install

Extract this package into:

`C:\xampp\htdocs\alasne.net`

Allow the package to replace matching files, then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

Migration `000037_add_store_credit_checkout_redemption.php` requires
migration `000036_create_exchange_store_credit_system.php` to have already run.

## Included behavior

- Secure balance lookup using the order email and postal code
- Partial or full store-credit application
- Split settlement between store credit and an external payment method
- Thirty-minute credit reservations
- Automatic reservation release after failed payment
- Immutable checkout-redemption ledger entries
- Full-credit checkout without an external charge
- Order, invoice, email, success-page, and return-tracking settlement details
- Proportional restoration of redeemed credit during returns
- Separate external-payment refund amount
- Reserved, total, and available balances in Mission Control

## Acceptance tests

1. Apply partial credit with an approved test payment.
2. Confirm the order is paid and inventory is reduced once.
3. Confirm a negative `Checkout Redemption` ledger entry.
4. Complete an order entirely with store credit.
5. Confirm no external charge transaction was created.
6. Attempt a declined split payment.
7. Confirm the reservation is released and inventory remains unchanged.
8. Return a split-tender order.
9. Confirm redeemed credit is restored and only the external portion is sent to the payment provider.
10. Verify the invoice, confirmation email, checkout success page, public tracking, and Mission Control ledger.
