# Dropshipping Operations Command Center

## Install

Extract the package into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No new database migration is required for this milestone. It reads from the supplier, purchase-order, submission, exception, and sync tables created in migrations `000038` and `000039`.

## New Mission Control route

```text
/admin/dropshipping
```

## What it shows

The command center consolidates the operational queues that matter every day:

- Paid orders that still need supplier routing
- Open fulfillment exceptions
- Purchase orders awaiting supplier submission
- Failed supplier submissions
- Late purchase orders
- Shipped supplier purchase orders missing tracking
- Failed or partial supplier CSV syncs
- Low-margin purchase orders
- Supplier performance and margin summary
- Recent dropshipping activity

## Filters

The dashboard supports:

- Store
- Supplier
- Financial lookback window
- Low-margin threshold

## Export

Use **Export Action Queue** to download a CSV containing the urgent operational queues, including action links back into Mission Control.

## Test sequence

1. Open `/admin/dropshipping`.
2. Confirm the page loads with all metric cards.
3. Filter by store.
4. Filter by supplier.
5. Open a paid order that has not been routed and confirm it appears in the routing queue.
6. Open an order with a fulfillment exception and confirm it appears in exceptions.
7. Open a purchase order awaiting submission and confirm it appears in the submission queue.
8. Create or view a failed supplier submission and confirm it appears in failed submissions.
9. Update a purchase order with an expected ship date in the past and confirm it appears as late.
10. Mark a purchase order shipped without tracking and confirm it appears in missing tracking.
11. Import a CSV with a bad row and confirm the sync problem appears.
12. Lower the margin threshold and confirm low-margin rows respond to the filter.
13. Use **Export Action Queue** and confirm the CSV opens.

## Commit

```powershell
git status
git add .
git commit -m "Add dropshipping operations command center"
git push
```
