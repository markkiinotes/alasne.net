# Product Sourcing and Profitability Scanner

## Prerequisites

Install the supplier and dropshipping foundation first:

- `000038_create_supplier_dropshipping_system.php`
- `000039_create_supplier_integration_adapters.php`

This package adds migration `000040`.

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

## Open the scanner

```text
/admin/product-sourcing
```

## What it evaluates

The scanner reads the existing `supplier_products` mappings and calculates:

- Retail price
- Supplier cost
- Shipping allowance
- Payment fee estimate
- Return allowance
- Discount allowance
- Ad-spend target
- Gross profit
- Gross margin
- Net profit
- Net margin
- Break-even ad spend
- Suggested minimum retail price
- Suggested target retail price
- Product score
- Recommendation: Good, Watch, or Avoid

## Store-level rules

Each store can define its own sourcing assumptions:

- Minimum gross margin %
- Minimum net margin %
- Target net margin %
- Minimum profit dollars
- Payment fee %
- Payment fixed fee
- Return allowance %
- Discount allowance %
- Ad-spend target %
- Shipping allowance
- Target markup %
- High-risk shipping threshold

## Review workflow

Each supplier product can be marked:

- Approved
- Watch
- Rejected
- Unreviewed

Approvals and rejections are snapshotted in
`product_sourcing_reviews`, and the current review state is also
mirrored onto `supplier_products` for quick filtering.

## Export

Use:

```text
/admin/product-sourcing/export
```

The export includes scores, profit calculations, recommendations,
review statuses, stock status, suggested prices, and risk notes.

## Test sequence

1. Open `/admin/product-sourcing`.
2. Select a store.
3. Confirm supplier mappings appear.
4. Adjust sourcing rules and save.
5. Confirm scores and suggested prices change.
6. Filter by Good, Watch, and Avoid.
7. Approve one Good product.
8. Reject one Avoid product.
9. Export the scanner CSV.
10. Confirm the CSV includes profitability and review fields.
11. Change a supplier mapping cost.
12. Refresh the scanner and confirm the score recalculates.
13. Import an inventory CSV from the supplier integration page.
14. Confirm updated stock/cost values affect the scanner.
