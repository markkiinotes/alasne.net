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
.mapping-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.mapping-grid-wide {
    grid-column:span 2;
}
@media(max-width:1000px) {
    .mapping-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:650px) {
    .mapping-grid {
        grid-template-columns:1fr;
    }
    .mapping-grid-wide { grid-column:span 1; }
}
</style>

<section class="page-header">
    <div>
        <h1>Product Suppliers</h1>
        <p>
            <?= $escape($product['name']) ?>
            ·
            <?= $escape($product['store_name']) ?>
            · Retail $<?= number_format(
                (float) $product['price'],
                2
            ) ?>
        </p>
    </div>
    <a
        href="/admin/products/<?= $escape(
            $product['id']
        ) ?>"
        class="button-muted"
    >
        Back to Product
    </a>
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

<section class="panel form-panel">
    <h2>Add or Update Supplier Mapping</h2>

    <?php if (empty($suppliers)): ?>
        <p>
            Create an active supplier for this store before
            mapping the product.
        </p>
        <a
            href="/admin/suppliers/create"
            class="button-primary"
        >
            Create Supplier
        </a>
    <?php else: ?>
        <form
            method="POST"
            action="/admin/products/<?= $escape(
                $product['id']
            ) ?>/suppliers"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="mapping-grid">
                <div class="form-group mapping-grid-wide">
                    <label for="supplier_id">Supplier</label>
                    <select
                        id="supplier_id"
                        name="supplier_id"
                        required
                    >
                        <option value="">
                            Select supplier
                        </option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option
                                value="<?= $escape(
                                    $supplier['id']
                                ) ?>"
                            >
                                <?= $escape(
                                    $supplier['name']
                                ) ?>
                                (<?= $escape(
                                    $supplier['code']
                                ) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="supplier_sku">
                        Supplier SKU
                    </label>
                    <input
                        id="supplier_sku"
                        type="text"
                        name="supplier_sku"
                        maxlength="191"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="wholesale_cost">
                        Wholesale Cost
                    </label>
                    <input
                        id="wholesale_cost"
                        type="number"
                        name="wholesale_cost"
                        min="0"
                        step="0.01"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="currency">Currency</label>
                    <input
                        id="currency"
                        type="text"
                        name="currency"
                        maxlength="3"
                        value="USD"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="stock_status">
                        Stock Status
                    </label>
                    <select
                        id="stock_status"
                        name="stock_status"
                    >
                        <option value="in_stock">
                            In Stock
                        </option>
                        <option value="unknown">
                            Unknown
                        </option>
                        <option value="backorder">
                            Backorder
                        </option>
                        <option value="out_of_stock">
                            Out of Stock
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="available_quantity">
                        Available Quantity
                    </label>
                    <input
                        id="available_quantity"
                        type="number"
                        name="available_quantity"
                        min="0"
                        placeholder="Blank = unknown"
                    >
                </div>

                <div class="form-group">
                    <label for="lead_time_min">
                        Minimum Lead Days
                    </label>
                    <input
                        id="lead_time_min"
                        type="number"
                        name="lead_time_min"
                        min="0"
                    >
                </div>

                <div class="form-group">
                    <label for="lead_time_max">
                        Maximum Lead Days
                    </label>
                    <input
                        id="lead_time_max"
                        type="number"
                        name="lead_time_max"
                        min="0"
                    >
                </div>

                <div class="form-group">
                    <label for="minimum_order_quantity">
                        Minimum Order Quantity
                    </label>
                    <input
                        id="minimum_order_quantity"
                        type="number"
                        name="minimum_order_quantity"
                        min="1"
                        value="1"
                    >
                </div>

                <div class="form-group">
                    <label for="pack_size">
                        Pack Size
                    </label>
                    <input
                        id="pack_size"
                        type="number"
                        name="pack_size"
                        min="1"
                        value="1"
                    >
                </div>

                <div class="form-group">
                    <label for="priority">
                        Product Routing Priority
                    </label>
                    <input
                        id="priority"
                        type="number"
                        name="priority"
                        min="1"
                        value="100"
                    >
                </div>

                <div class="form-group">
                    <label>
                        <input
                            type="checkbox"
                            name="is_preferred"
                            value="1"
                        >
                        Preferred supplier
                    </label>
                </div>
            </div>

            <button
                type="submit"
                class="button-primary"
            >
                Save Mapping
            </button>
        </form>
    <?php endif; ?>
</section>

<br>

<section class="panel">
    <h2>Current Supplier Options</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Supplier SKU</th>
                <th>Cost</th>
                <th>Profit / Unit</th>
                <th>Margin</th>
                <th>Stock</th>
                <th>Lead</th>
                <th>Priority</th>
                <th>Preferred</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mappings as $mapping): ?>
                <?php
                $profit =
                    (float) $product['price']
                    - (float) $mapping[
                        'wholesale_cost'
                    ];
                $margin =
                    (float) $product['price'] > 0
                        ? $profit
                            / (float) $product['price']
                            * 100
                        : 0;
                ?>
                <tr>
                    <td>
                        <a
                            href="/admin/suppliers/<?= $escape(
                                $mapping['supplier_id']
                            ) ?>"
                            class="table-link"
                        >
                            <?= $escape(
                                $mapping['supplier_name']
                            ) ?>
                        </a>
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
                        $<?= number_format($profit, 2) ?>
                    </td>
                    <td>
                        <?= number_format($margin, 1) ?>%
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
                        <?php if (
                            $mapping[
                                'available_quantity'
                            ] !== null
                        ): ?>
                            (<?= $escape(
                                $mapping[
                                    'available_quantity'
                                ]
                            ) ?>)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $escape(
                            ($mapping['lead_time_min']
                                ?? '—')
                            . '–'
                            . ($mapping['lead_time_max']
                                ?? '—')
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $mapping['priority']
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
                        <form
                            method="POST"
                            action="/admin/products/<?= $escape(
                                $product['id']
                            ) ?>/suppliers/<?= $escape(
                                $mapping['id']
                            ) ?>/delete"
                            onsubmit="return confirm('Remove this supplier mapping?');"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >
                            <button
                                type="submit"
                                class="button-muted"
                            >
                                Remove
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($mappings)): ?>
                <tr>
                    <td colspan="10">
                        This product does not have a
                        supplier mapping yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
