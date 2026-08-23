# Public Storefront Phase 2 — Cart + Checkout Experience

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

No migration is required.

## Files

```text
app/Views/layouts/storefront.php
app/Views/storefront/checkout.php
app/Views/storefront/checkout-success.php
public/assets/css/storefront.css

docs/storefront/README.md
docs/storefront/ROADMAP.md
docs/storefront/PHASE-2-CART-CHECKOUT.md
docs/storefront/TESTING-CHECKLIST.md
docs/storefront/GIT-CHECKLIST.md
docs/storefront/CHANGELOG.md
```

## Safety boundary

Not replaced:

```text
CartController
CheckoutController
CheckoutService
PaymentService
payment providers
inventory services
notification publishers
supplier routing
return/RMA logic
config/routes.php
database schema
```

## Acceptance test

Follow:

```text
docs/storefront/TESTING-CHECKLIST.md
```

Test all three existing development-provider outcomes:

```text
Approved
Declined
Provider Error
```

## Git

Use the targeted staging commands in:

```text
docs/storefront/GIT-CHECKLIST.md
```

Recommended commit:

```powershell
git commit -m "Polish public cart and checkout experience"
git push
```
