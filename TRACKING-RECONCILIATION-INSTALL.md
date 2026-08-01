# Automated Tracking Reconciliation

## Prerequisites

Install these prior milestones first:

- `000038` Supplier Dropshipping Foundation
- `000039` Supplier Integration Adapters
- `000040` Product Sourcing Scanner
- `000041` Supplier Performance Reporting

This package adds migration `000042`.

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

## New page

```text
/admin/tracking-reconciliation
```

## What it adds

Automated Tracking Reconciliation adds a Mission Control workflow for
importing supplier tracking data and keeping supplier purchase orders,
customer order tracking fields, and public customer timelines aligned.

It creates:

- `tracking_reconciliation_runs`
- `tracking_reconciliation_rows`
- `supplier_tracking_records`

It also adds these columns to `purchase_orders`:

- `tracking_status`
- `tracking_source`
- `last_tracking_reconciled_at`

## CSV import

Download a template at:

```text
/admin/tracking-reconciliation/template
```

Required:

```text
tracking_number
```

Plus at least one match field:

```text
purchase_order_number
supplier_order_id
provider_order_id
external_order_id
supplier_reference
```

Supported optional columns:

```text
carrier
tracking_url
shipment_status
shipped_date
delivered_date
notes
```

## Matching order

Rows are matched against purchase orders in this order:

1. `purchase_order_number`
2. `provider_order_id`
3. `external_order_id`
4. `supplier_reference`

Store and supplier filters narrow the match when supplied on the upload
screen.

## Status normalization

Supplier shipment statuses are normalized to:

```text
label_created
in_transit
out_for_delivery
delivered
exception
unknown
```

Purchase-order status updates:

- `delivered` → purchase order becomes Delivered
- `in_transit` or `out_for_delivery` → purchase order becomes Shipped
- `label_created` → purchase order becomes Partially Shipped when not already shipped
- `exception` → tracking exception is recorded and a fulfillment exception is opened

## Customer order synchronization

For single-supplier customer orders, the legacy customer order tracking
fields are updated:

- `shipping_carrier`
- `tracking_number`
- `tracking_url`
- `shipped_at`

For multi-supplier customer orders, tracking remains lossless in
`purchase_orders`, `supplier_tracking_records`, and public order events.

Every valid tracking update also writes:

- a `purchase_order_events` entry
- a public `order_events` timeline entry

## Partial-import protection

Each row is processed independently:

- Valid matched rows update purchase orders
- Unmatched rows are logged
- Ambiguous rows are logged
- Duplicate tracking numbers are logged and flagged
- Invalid rows are logged
- The run becomes Partial when some rows succeed and some fail

A bad supplier tracking row does not discard the rest of the file.

## Dashboard

The dashboard shows:

- Purchase orders missing tracking
- Purchase orders never reconciled
- Tracking exceptions
- Recent import runs
- Unmatched/problem rows
- Duplicate tracking numbers
- Recent tracking records
- Exportable reconciliation queue

## Test sequence

1. Open `/admin/tracking-reconciliation`.
2. Download the CSV template.
3. Fill in a known `purchase_order_number`.
4. Add carrier, tracking number, and `in_transit`.
5. Upload the CSV.
6. Confirm the run shows Matched and Updated.
7. Open the purchase order.
8. Confirm carrier, tracking number, status, and event timeline updated.
9. Open the customer order timeline.
10. Confirm a public tracking event was recorded.
11. Upload a row with an unknown PO.
12. Confirm the run becomes Partial and the row appears as Unmatched.
13. Upload the same tracking number for another PO.
14. Confirm a duplicate-tracking problem is logged.
15. Export the reconciliation queue.
