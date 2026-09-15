# Public Storefront Phase 1 — Shell + Catalog Polish

## Purpose

Turn the already-functional public commerce surfaces into a cohesive customer
storefront without changing business transaction logic.

## Files installed

```text
app/Views/layouts/storefront.php
public/assets/css/storefront.css

docs/storefront/README.md
docs/storefront/ROADMAP.md
docs/storefront/TESTING-CHECKLIST.md
docs/storefront/GIT-CHECKLIST.md
docs/storefront/CHANGELOG.md
```

## Install

Extract this package over:

```text
C:\xampp\htdocs\alasne.net
```

Then:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

## Business-logic safety boundary

This package does NOT replace:

```text
CheckoutController
CheckoutService
CartController
payment services
return controllers/services
supplier services
notification publishers
config/routes.php
```

## First browser test

Open:

```text
/store/<your-store-slug>
```

Then follow:

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
git commit -m "Polish public storefront shell and catalog experience"
git push
```

Use targeted staging because an unrelated historical tracking installer was
already untracked before this milestone.
