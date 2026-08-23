# Git Checklist — Public Storefront Phase 2

This milestone must be committed with its storefront documentation.

## Existing unrelated file

The repository previously contained this unrelated untracked file:

```text
TRACKING-UPDATED-TEMPLATE-CONTRACT-REPAIR-INSTALL.md
```

Do not accidentally stage it with this storefront milestone.

Use targeted `git add` commands. Do not use `git add .`.

## Before installation

```powershell
cd C:\xampp\htdocs\alasne.net
git status
git branch --show-current
```

The working branch previously observed was:

```text
feature/returns-and-restocking
```

## Install

Extract the Phase 2 ZIP over the project root.

Then:

```powershell
composer dump-autoload -o
```

No migration is required.

## Review changes

```powershell
git status
git diff -- app/Views/layouts/storefront.php
git diff -- app/Views/storefront/checkout.php
git diff -- app/Views/storefront/checkout-success.php
git diff -- public/assets/css/storefront.css
git diff -- docs/storefront
git diff -- STOREFRONT-CART-CHECKOUT-EXPERIENCE-INSTALL.md
```

The layout is included cumulatively for safe installation; if it already
matches Phase 1, Git may show no diff for that file.

## Stage only Phase 2

```powershell
git add app/Views/layouts/storefront.php
git add app/Views/storefront/checkout.php
git add app/Views/storefront/checkout-success.php
git add public/assets/css/storefront.css
git add docs/storefront
git add STOREFRONT-CART-CHECKOUT-EXPERIENCE-INSTALL.md
```

Then:

```powershell
git status
```

Review the staged file list before committing.

## Recommended commit

After the Approved / Declined / Provider Error browser tests pass:

```powershell
git commit -m "Polish public cart and checkout experience"
git push
```

## Verify push

```powershell
git status
git log -1 --oneline
```

The latest commit should be the storefront Phase 2 milestone.

If the unrelated tracking installer is still intentionally untracked, Git
may continue to list that one file separately.
