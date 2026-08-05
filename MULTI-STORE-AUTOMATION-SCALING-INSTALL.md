# Multi-Store Automation Scaling

## Prerequisites

Install prior milestones through:

- `000044_create_customer_account_portal.php`

This package adds migration `000045`.

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

## New Mission Control pages

```text
/admin/multi-store-automation
/admin/multi-store-automation/STORE_ID
/admin/multi-store-automation/runs/RUN_ID
```

## What it adds

This package introduces a multi-store operating layer for scaling Alasne
from one store into many stores.

It adds:

- Multi-store automation dashboard
- Store launch scorecards
- Per-store launch profiles
- Store launch status tracking
- Store automation status tracking
- Store health score
- Saved launch audit runs
- Saved launch audit items
- Cross-store catalog candidate review queue
- CSV exports for scorecards and audit runs

## New tables

```text
multi_store_launch_profiles
multi_store_launch_audit_runs
multi_store_launch_audit_items
multi_store_catalog_candidates
```

## Store table additions

```text
automation_launch_status
automation_health_score
last_automation_audit_at
```

## Store launch profile

Each store can define:

- Launch status: Planning, Building, Ready, Launched, Paused
- Automation status: Paused, Manual Review, Active
- Target launch date
- Niche summary
- Primary supplier
- Margin target
- Minimum approved products
- Required readiness checks
- Store-specific notes

## Launch scorecard

The scorecard checks:

- Active products exist
- Active suppliers exist
- Supplier product mappings exist
- Approved product target is met
- Return policy is configured
- Tracking gaps are resolved
- Optional store credit readiness

Readiness levels:

```text
Ready
Warning
Blocked
```

## Catalog candidates

The package can refresh catalog candidates from supplier product mappings
that were approved in the Product Sourcing Scanner or recommended as Good.

Candidate statuses:

```text
Candidate
Approved
Deferred
Rejected
```

This is intentionally a review workflow. It does not automatically publish
products into new stores yet.

## Why this milestone matters

Alasne already has:

- Supplier routing
- Purchase orders
- Supplier submissions
- Product sourcing intelligence
- Supplier performance reporting
- Tracking reconciliation
- Customer account portal
- Production readiness checks

This milestone adds the scaling layer:

```text
Which stores are ready?
Which stores are blocked?
Which products are candidates for each store?
Which store launch tasks still need attention?
```

## Test sequence

1. Open `/admin/multi-store-automation`.
2. Confirm all stores appear.
3. Open a store profile.
4. Set launch status to Building.
5. Set automation status to Manual Review.
6. Set a target margin and minimum approved products.
7. Save the profile.
8. Return to the main dashboard.
9. Save a Launch Audit.
10. Open the saved audit run.
11. Export the audit run CSV.
12. Refresh catalog candidates.
13. Approve, defer, or reject a candidate.
14. Export the store scorecards CSV.
15. Fix one blocked item and save another audit to confirm the score changes.

## Commit

```powershell
git status
git add .
git commit -m "Add multi-store automation scaling"
git push
```
