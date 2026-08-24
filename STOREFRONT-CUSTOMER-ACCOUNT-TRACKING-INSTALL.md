# Public Storefront Phase 3 — Customer Account + Order Tracking

## Install

Extract over:

```text
C:\xampp\htdocs\alasne.net
```

Then:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

## Files changed

```text
app/Controllers/CustomerAccountController.php
app/Repositories/CustomerPortalRepository.php
app/Services/Customers/CustomerPortalService.php

app/Views/storefront/customer-account-login.php
app/Views/storefront/customer-account-link-sent.php
app/Views/storefront/customer-account-dashboard.php
app/Views/storefront/customer-account-order.php
app/Views/storefront/customer-account-store-credit.php
app/Views/storefront/track-order.php

public/assets/css/storefront.css
```

## What is deliberately NOT changed

```text
config/routes.php
database schema
CheckoutController
CheckoutService
PaymentService
CartController
inventory services
supplier routing
return/RMA business rules
notification Event Bridge
```

The existing account routes and migration 000044 remain authoritative.

## Magic-link email

The secure account login URL remains a direct `email_outbox` message.

Do not move the token-bearing URL into generic Event Bridge payloads.

For the first acceptance test, `EMAIL_QUEUE_TRANSPORT=log` is recommended.

## Acceptance

Follow:

```text
docs/storefront/TESTING-CHECKLIST.md
```

## Git

Follow:

```text
docs/storefront/GIT-CHECKLIST.md
```

Recommended commit:

```powershell
git commit -m "Polish customer account and order tracking experience"
git push
```
