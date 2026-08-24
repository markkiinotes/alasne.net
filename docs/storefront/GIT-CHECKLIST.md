# Git Checklist — Public Storefront Phase 3 Closeout

Phase 3 browser acceptance passed on 2026-08-23.

The original Phase 3 package was committed as:

```text
b08ad31 Polish customer account and order tracking experience
```

This closeout commit captures the acceptance repairs discovered after that
commit and records the final PASS state in the storefront documentation.

## 1. Confirm branch and working tree

```powershell
cd C:\xampp\htdocs\alasne.net
git branch --show-current
git status
```

Expected branch:

```text
feature/returns-and-restocking
```

## 2. Review the acceptance-repair code

```powershell
git diff -- app/Services/Checkout/CheckoutService.php
git diff -- app/Repositories/OrderRepository.php
git diff -- app/Repositories/StoreRepository.php
```

The expected code changes are limited to:

- immutable shipping-address snapshot persistence during storefront checkout
- complete payment/shipping-method snapshot reads for orders
- public tracking access to the immutable shipping-address snapshot
- order-item SKU compatibility required by the repaired order views

## 3. Replace the closeout documentation

Replace these complete files with the Phase 3 closeout versions:

```text
docs/storefront/ROADMAP.md
docs/storefront/TESTING-CHECKLIST.md
docs/storefront/PHASE-3-CUSTOMER-ACCOUNT-TRACKING.md
docs/storefront/CHANGELOG.md
docs/storefront/GIT-CHECKLIST.md
```

## 4. Validate PHP syntax

```powershell
php -l app/Services/Checkout/CheckoutService.php
php -l app/Repositories/OrderRepository.php
php -l app/Repositories/StoreRepository.php
```

Expected for all three:

```text
No syntax errors detected
```

## 5. Review final diff

```powershell
git diff --check
git diff --stat
git status
```

`git diff --check` should return no output.

## 6. Stage only the Phase 3 closeout

```powershell
git add app/Services/Checkout/CheckoutService.php
git add app/Repositories/OrderRepository.php
git add app/Repositories/StoreRepository.php
git add docs/storefront/ROADMAP.md
git add docs/storefront/TESTING-CHECKLIST.md
git add docs/storefront/PHASE-3-CUSTOMER-ACCOUNT-TRACKING.md
git add docs/storefront/CHANGELOG.md
git add docs/storefront/GIT-CHECKLIST.md
git status
```

Do not stage unrelated files.

## 7. Commit and push

Recommended closeout commit:

```powershell
git commit -m "Close storefront Phase 3 acceptance and shipping snapshots"
git push
```

## 8. Verify

```powershell
git status
git log -2 --oneline
```

Expected:

```text
working tree clean
latest commit = Phase 3 acceptance closeout
previous Phase 3 package commit = b08ad31
```

## 9. Begin Phase 4

After the closeout commit is pushed:

```text
Phase 4 — Returns + RMA Customer Experience
Phase 4A — Return Eligibility & Request Experience
```
