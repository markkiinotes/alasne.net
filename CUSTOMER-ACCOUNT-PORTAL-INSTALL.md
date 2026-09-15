# Customer Account Portal

## Prerequisites

Install the prior ecommerce, returns, store-credit, supplier, tracking, and production-readiness milestones first. This package adds migration `000044`.

## Install

Extract into:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

## New storefront pages

```text
/store/{store_slug}/account
/store/{store_slug}/account/dashboard
/store/{store_slug}/account/orders/{order_id}
/store/{store_slug}/account/store-credit
```

## What it adds

The Customer Account Portal gives customers a self-service area to view:

- Recent orders
- Order status
- Payment status
- Supplier shipment tracking
- Public order timeline events
- Returns
- Store credit balance and activity
- Profile and shipping address

## Access model

This package uses a passwordless access-link flow instead of customer passwords.

A customer enters:

```text
Email address
Postal code
```

If the details match a customer record, the system queues a secure one-time link through `email_outbox`.

For privacy, the login screen does not reveal whether the customer exists.

## Security features

- 32-byte random token
- SHA-256 token hash stored in the database
- Token expires after 30 minutes
- Token can only be used once
- Session expires after 8 hours
- CSRF protection on link requests and profile updates
- Rate limiting for account-link requests
- Portal events logged for access-link requests, sign-ins, and profile updates

## New tables

```text
customer_portal_access_tokens
customer_portal_events
```

## Customer table additions

```text
portal_last_login_at
portal_login_count
portal_profile_updated_at
```

## Customer synchronization

The portal uses the existing `customers`, `orders`, `order_items`, `order_events`, `purchase_orders`, `returns`, and `store_credit_*` tables when available.

It does not change checkout, payments, supplier routing, or fulfillment.

## Email sending

The package queues portal links in `email_outbox`. Use the existing Mission Control email outbox sender to send pending emails.

## Test sequence

1. Open `/store/STORE_SLUG/account`.
2. Enter the email and postal code for an existing customer.
3. Confirm an email is queued in Mission Control → Email Outbox.
4. Open the email body and copy the secure account link in local development.
5. Visit the link.
6. Confirm the customer account dashboard loads.
7. Open an order detail page.
8. Confirm items, tracking, and public timeline events appear.
9. Open store credit.
10. Confirm balance and ledger activity appear when available.
11. Update the customer profile.
12. Confirm the customer record updates and a portal event is logged.
13. Sign out and confirm the dashboard is no longer accessible.

## Commit

```powershell
git status
git add .
git commit -m "Add customer account portal"
git push
```
