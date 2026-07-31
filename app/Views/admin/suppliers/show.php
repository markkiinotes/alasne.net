<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
?>

<style>
.supplier-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.supplier-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.supplier-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
@media(max-width:900px) {
    .supplier-summary { grid-template-columns:1fr; }
}
</style>

<section class="page-header">
    <div>
        <h1><?= $escape($supplier['name']) ?></h1>
        <p>
            <?= $escape($supplier['code']) ?>
            ·
            <?= $escape($supplier['store_name']) ?>
        </p>
    </div>

    <div class="table-actions">
        <a
            href="/admin/suppliers/<?= $escape(
                $supplier['id']
            ) ?>/edit"
            class="button-primary"
        >
            Edit Supplier
        </a>

        <a
            href="/admin/suppliers/<?= $escape(
                $supplier['id']
            ) ?>/integration"
            class="button-primary"
        >
            Manage Integration
        </a>
        <a
            href="/admin/suppliers"
            class="button-muted"
        >
            Back
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success">
        <?= $escape($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger">
        <?= $escape($error) ?>
    </div>
<?php endif; ?>

<section class="supplier-summary">
    <article>
        <small>Status</small>
        <strong>
            <?= $escape(ucwords($supplier['status'])) ?>
        </strong>
    </article>
    <article>
        <small>Type</small>
        <strong>
            <?= $escape(
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $supplier['supplier_type']
                    )
                )
            ) ?>
        </strong>
    </article>
    <article>
        <small>Priority</small>
        <strong>
            <?= $escape($supplier['priority']) ?>
        </strong>
    </article>
    <article>
        <small>Lead Time</small>
        <strong>
            <?= $escape(
                ($supplier['default_lead_time_min']
                    ?? '—')
                . '–'
                . ($supplier['default_lead_time_max']
                    ?? '—')
                . ' days'
            ) ?>
        </strong>
    </article>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Product Mappings</h2>
            <p>
                Open a product to configure supplier SKU,
                cost, availability, and routing priority.
            </p>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Store SKU</th>
                <th>Supplier SKU</th>
                <th>Wholesale</th>
                <th>Retail</th>
                <th>Profit / Unit</th>
                <th>Stock</th>
                <th>Preferred</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mappings as $mapping): ?>
                <tr>
                    <td>
                        <?= $escape(
                            $mapping['product_name']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $mapping['product_sku']
                            ?? '—'
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $mapping['supplier_sku']
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $mapping[
                                'wholesale_cost'
                            ],
                            2
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $mapping[
                                'retail_price'
                            ],
                            2
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $mapping[
                                'retail_price'
                            ]
                            - (float) $mapping[
                                'wholesale_cost'
                            ],
                            2
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $mapping[
                                        'stock_status'
                                    ]
                                )
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= (int) $mapping[
                            'is_preferred'
                        ] === 1
                            ? 'Yes'
                            : 'No' ?>
                    </td>
                    <td>
                        <a
                            href="/admin/products/<?= $escape(
                                $mapping[
                                    'product_id'
                                ]
                            ) ?>/suppliers"
                            class="table-link"
                        >
                            Manage
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($mappings)): ?>
                <tr>
                    <td colspan="9">
                        No products are mapped to this
                        supplier yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (! empty($products)): ?>
        <div style="margin-top:18px;">
            <strong>Map another product:</strong>
            <?php foreach (array_slice($products, 0, 12) as $product): ?>
                <a
                    href="/admin/products/<?= $escape(
                        $product['id']
                    ) ?>/suppliers"
                    class="button-muted"
                    style="margin:5px;"
                >
                    <?= $escape($product['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <h2>Recent Purchase Orders</h2>
        <a
            href="/admin/purchase-orders?supplier_id=<?= $escape(
                $supplier['id']
            ) ?>"
            class="button-muted"
        >
            View All
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Purchase Order</th>
                <th>Customer Order</th>
                <th>Status</th>
                <th>Cost</th>
                <th>Profit</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($purchaseOrders as $po): ?>
                <tr>
                    <td>
                        <?= $escape(
                            $po[
                                'purchase_order_number'
                            ]
                        ) ?>
                    </td>
                    <td>
                        <?= $escape($po['order_number']) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $po['status']
                                )
                            )
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $po['total_cost'],
                            2
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $po[
                                'estimated_profit'
                            ],
                            2
                        ) ?>
                    </td>
                    <td>
                        <?= $escape($po['created_at']) ?>
                    </td>
                    <td>
                        <a
                            href="/admin/purchase-orders/<?= $escape(
                                $po['id']
                            ) ?>"
                            class="table-link"
                        >
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($purchaseOrders)): ?>
                <tr>
                    <td colspan="7">
                        No purchase orders have been created.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
