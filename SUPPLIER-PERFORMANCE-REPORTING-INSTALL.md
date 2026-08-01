# Supplier Performance Reporting

## Prerequisites

Install these milestones first:

- Supplier Dropshipping Foundation: `000038`
- Supplier Integration Adapters: `000039`
- Product Sourcing Scanner: `000040`

This package adds migration `000041`.

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

## New pages

```text
/admin/supplier-performance
/admin/supplier-performance/SUPPLIER_ID
```

## What it measures

The report builds supplier scorecards from the existing operational tables:

- Purchase orders
- Supplier submissions
- Supplier submission failures
- Late expected ship dates
- Missing tracking numbers
- Delivered and shipped purchase orders
- Open dropshipping exceptions
- Returns, when the returns table is installed
- Revenue, supplier cost, gross profit, and margin

## Supplier score

Each supplier receives a 0–100 score. The score starts from 100 and applies penalties for:

- Late purchase orders
- Failed supplier submissions
- Missing tracking
- Open fulfillment exceptions
- Returns
- Margin below the target rule
- Inactive supplier status
- No purchase-order history in the period

## Recommendations

Supplier recommendations are:

```text
Keep
Watch
Replace
```

A supplier is usually marked **Keep** when it has order history,
solid margin, no late POs, and no failed submissions.

A supplier is marked **Replace** when it has repeated failures,
low score, negative margin, or too many open exceptions.

Most suppliers begin as **Watch** until enough clean order history
has accumulated.

## Review workflow

You can manually review each supplier as:

```text
Keep
Watch
Replace
Unreviewed
```

A review snapshot is saved in `supplier_performance_reviews`, and the
current performance status is mirrored on the `suppliers` table.

## Export

Use:

```text
/admin/supplier-performance/export
```

The export includes supplier revenue, cost, gross profit, margin, score,
recommendation, late POs, failed submissions, tracking gaps, exceptions,
returns, delivery rate, and risk notes.

## Test sequence

1. Open `/admin/supplier-performance`.
2. Filter by store.
3. Confirm supplier scorecards load.
4. Set target margin to a higher number and re-run.
5. Confirm lower-margin suppliers lose score.
6. Open a supplier detail page.
7. Review recent purchase orders and failures.
8. Save a supplier review as Watch or Keep.
9. Confirm the review appears in the detail page.
10. Export the report CSV.
11. Confirm the CSV contains score, recommendation, margin, and risk notes.
