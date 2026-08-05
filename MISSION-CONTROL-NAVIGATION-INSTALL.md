# Mission Control Navigation and Workflow Polish

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

## New Mission Control routes

```text
/admin/mission-control
/admin/workflows
/admin/navigation
```

All three routes point to the same workflow hub.

## What it adds

This package adds a professional Mission Control navigation and workflow layer:

- Grouped admin navigation
- Mission Control workflow hub
- Operational summary cards
- Next-action cards
- Workflow map
- Reusable navigation partial
- Reusable breadcrumb partial
- Operational badges for blockers and queues
- Links into existing modules

## Files added

```text
app/Controllers/Admin/MissionControlNavigationController.php
app/Services/Admin/MissionControlNavigationService.php
app/Views/admin/mission-control/index.php
app/Views/admin/partials/mission-control-nav.php
app/Views/admin/partials/mission-control-breadcrumbs.php
config/routes.php
```

## Navigation groups

The hub organizes Mission Control into:

- Daily Operations
- Product Intelligence
- Suppliers & Fulfillment
- Customer Care
- Payments & Compliance
- Stores & Scaling

## Next-action cards

The page surfaces workflow items such as:

- Open fulfillment exceptions
- Tracking gaps
- Failed supplier submissions
- Open returns
- Unreviewed sourcing products
- Blocked store launches

## Workflow map

The workflow map presents Alasne's operating sequence:

```text
1. Find profitable products
2. Prepare stores for launch
3. Route paid orders
4. Reconcile supplier tracking
5. Measure supplier quality
6. Verify launch readiness
```

## Safe scope

This package is UI/read-only.

It does not change:

- Checkout
- Payments
- Supplier routing
- Purchase order creation
- Customer records
- Product records
- Store records
- Database schema

## Optional later step

After confirming `/admin/mission-control` looks good, we can later make it the default `/admin` landing page. This package intentionally does not replace the existing `/admin` dashboard automatically.

## Test sequence

1. Extract the package.
2. Run `composer dump-autoload -o`.
3. Open `/admin/mission-control`.
4. Confirm the workflow hub loads.
5. Check each navigation section.
6. Confirm badges appear when blockers exist.
7. Open `/admin/workflows`.
8. Confirm it shows the same page.
9. Open `/admin/navigation`.
10. Confirm it shows the same page.
11. Commit and push.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control navigation workflow hub"
git push
```
