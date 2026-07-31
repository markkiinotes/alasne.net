# Supplier Integration Adapters

## Prerequisite

Install and migrate the Supplier and Dropshipping Fulfillment
Foundation through migration `000038` before installing this
package.

## Install

Extract the ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

Migration `000039` creates:

- `supplier_integrations`
- `supplier_sync_runs`
- `supplier_sync_errors`
- `supplier_order_submissions`
- `supplier_submission_events`

It also extends `purchase_orders` with submission state,
provider references, and an immutable ship-to snapshot. Existing
purchase orders are backfilled from their customer records without
overwriting nonblank snapshots.

## Available adapters

### Manual / Direct Supplier

- Prepares one idempotent submission record per purchase order
- Generates a supplier-ready CSV
- Supports manual status updates and external order IDs
- Does not transmit orders over the internet

### CSV Catalog / Inventory Feed

- Includes all manual/direct order-export behavior
- Imports catalog mappings
- Imports wholesale cost and stock updates
- Records partial success and row-level errors
- Uses source hashes and sync timestamps
- Does not transmit purchase orders over the internet

## Mission Control routes

```text
/admin/suppliers/SUPPLIER_ID/integration
/admin/supplier-submissions
/admin/purchase-orders/PURCHASE_ORDER_ID
```

## Catalog CSV columns

The catalog template includes:

```text
supplier_sku
product_sku
product_id
provider_product_id
wholesale_cost
currency
available_quantity
stock_status
lead_time_min
lead_time_max
minimum_order_quantity
pack_size
is_preferred
priority
source_updated_at
```

`supplier_sku` is required. New mappings also require a valid
`product_sku` or `product_id` and a valid wholesale cost.

## Inventory CSV columns

The inventory template includes:

```text
supplier_sku
wholesale_cost
currency
available_quantity
stock_status
source_updated_at
```

Inventory rows must match an existing supplier SKU mapping.

Valid stock statuses:

```text
unknown
in_stock
out_of_stock
backorder
discontinued
```

## Security

- API credentials are not stored in the database.
- Only environment-variable names are stored.
- The UI reports whether each named environment variable exists
  but never displays its value.
- CSV uploads are limited to 10 MB and 50,000 data rows.
- Supplier submissions are protected by an idempotency key and a
  unique purchase-order constraint.
- Preparing a supplier submission cannot reverse customer payment.
- Adapter preparation failures become fulfillment exceptions.

## Test sequence

1. Open a supplier.
2. Select **Manage Integration**.
3. Leave the Manual / Direct adapter selected.
4. Enable automatic preparation and save.
5. Place a paid storefront order using a mapped product.
6. Open the generated purchase order.
7. Confirm a supplier submission was automatically prepared.
8. Download the supplier CSV.
9. Confirm the CSV contains the supplier SKU, cost, quantity,
   customer order number, and ship-to snapshot.
10. Mark the submission Submitted and enter a test external order ID.
11. Confirm both the submission and purchase order reflect the status.
12. Change the supplier to the CSV Feed adapter.
13. Download the catalog template.
14. Import a valid catalog row.
15. Confirm the supplier mapping was created or updated.
16. Import a file with one valid row and one invalid row.
17. Confirm the run is Partial, the valid row commits, and the error
   appears in the sync-run details.
18. Download the inventory template.
19. Update quantity, stock status, and wholesale cost.
20. Confirm routing uses the synchronized values on the next order.

## Deferred external APIs

This package intentionally does not call TopDawg, Doba,
GreenDropShip, or another external service. A future authenticated
adapter will implement the same provider interface and can reuse the
submission queue, environment references, audit events, and
purchase-order snapshots.
