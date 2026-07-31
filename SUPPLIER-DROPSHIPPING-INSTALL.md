# Supplier and Dropshipping Fulfillment Foundation

## Install

Extract this package into the Alasne project root, then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

## Mission Control routes

- Suppliers: `/admin/suppliers`
- Purchase orders: `/admin/purchase-orders`
- Product supplier mappings: `/admin/products/PRODUCT_ID/suppliers`
- Order dropshipping overview: `/admin/orders/ORDER_ID/dropship`

## Workflow

1. Create one or more suppliers.
2. Map products to suppliers with supplier SKU, wholesale cost, availability, lead time, and routing priority.
3. Complete a paid storefront checkout.
4. Alasne routes each line after checkout commits.
5. One purchase order is generated per selected supplier.
6. Unmapped or unavailable lines create fulfillment exceptions instead of failing checkout.
7. Update supplier status and tracking from the Purchase Order page.

## Routing precedence

1. Preferred product mapping
2. Product mapping priority
3. Supplier priority
4. Stock status
5. Wholesale cost
6. Maximum lead time

## Important scope

This milestone creates provider-neutral purchase orders. It does not yet transmit orders to AliExpress, CJdropshipping, Printful, Spocket, or another external API. Purchase orders begin in Pending status and can be managed manually until provider adapters are added.
