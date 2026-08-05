# Mission Control Default Admin Experience

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

## What changes

This package makes the new professional Mission Control workflow hub the default admin landing page.

```text
/admin
```

now opens:

```text
MissionControlNavigationController@index
```

The original dashboard is preserved at:

```text
/admin/dashboard
/admin/legacy-dashboard
```

## Why this is safe

This package only changes routing and navigation.

It does not change:

- Database schema
- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Customer-facing pages

## Files included

```text
config/routes.php
app/Services/Admin/MissionControlNavigationService.php
app/Views/admin/mission-control/index.php
```

## Test sequence

1. Extract the package.
2. Run `composer dump-autoload -o`.
3. Open `/admin`.
4. Confirm the new Mission Control workflow hub loads.
5. Open `/admin/dashboard`.
6. Confirm the old dashboard still loads.
7. Open `/admin/legacy-dashboard`.
8. Confirm the old dashboard still loads.
9. Open `/admin/mission-control`.
10. Confirm it still loads the new workflow hub.
11. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Make Mission Control workflow hub default admin page"
git push
```
