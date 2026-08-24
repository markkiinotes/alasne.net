# Git Checklist — Public Storefront Phase 3

The working tree was clean before this milestone.

Use targeted staging so the Phase 3 commit contains only customer-account and
tracking work.

## Before installation

```powershell
cd C:\xampp\htdocs\alasne.net
git status
git branch --show-current
```

Expected branch:

```text
feature/returns-and-restocking
```

## Install

Extract the Phase 3 ZIP over the project root.

Then:

```powershell
composer dump-autoload -o
```

No migration is required.

## Review changes

```powershell
git status
git diff -- app/Controllers/CustomerAccountController.php
git diff -- app/Repositories/CustomerPortalRepository.php
git diff -- app/Services/Customers/CustomerPortalService.php
git diff -- app/Views/storefront/customer-account-login.php
git diff -- app/Views/storefront/customer-account-link-sent.php
git diff -- app/Views/storefront/customer-account-dashboard.php
git diff -- app/Views/storefront/customer-account-order.php
git diff -- app/Views/storefront/customer-account-store-credit.php
git diff -- app/Views/storefront/track-order.php
git diff -- public/assets/css/storefront.css
git diff -- docs/storefront
git diff -- STOREFRONT-CUSTOMER-ACCOUNT-TRACKING-INSTALL.md
```

## Stage only Phase 3

```powershell
git add app/Controllers/CustomerAccountController.php
git add app/Repositories/CustomerPortalRepository.php
git add app/Services/Customers/CustomerPortalService.php
git add app/Views/storefront/customer-account-login.php
git add app/Views/storefront/customer-account-link-sent.php
git add app/Views/storefront/customer-account-dashboard.php
git add app/Views/storefront/customer-account-order.php
git add app/Views/storefront/customer-account-store-credit.php
git add app/Views/storefront/track-order.php
git add public/assets/css/storefront.css
git add docs/storefront
git add STOREFRONT-CUSTOMER-ACCOUNT-TRACKING-INSTALL.md

git status
```

## Recommended commit

After Phase 3 acceptance tests pass:

```powershell
git commit -m "Polish customer account and order tracking experience"
git push
```

## Verify

```powershell
git status
git log -1 --oneline
```

Expected:

```text
working tree clean
latest commit = Phase 3 customer account/tracking milestone
```
