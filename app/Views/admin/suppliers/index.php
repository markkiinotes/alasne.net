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
.supplier-filters {
    display:grid;
    grid-template-columns:2fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}
.supplier-metrics {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:8px;
}
.supplier-metrics small {
    display:block;
    color:#64748b;
}
@media(max-width:900px) {
    .supplier-filters,
    .supplier-metrics {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Suppliers</h1>
        <p>
            Manage supplier relationships, product
            mappings, costs, availability, and lead times.
        </p>
    </div>

    <div class="table-actions">
        <a
            href="/admin/purchase-orders"
            class="button-muted"
        >
            Purchase Orders
        </a>

        <a
            href="/admin/suppliers/create"
            class="button-primary"
        >
            Add Supplier
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

<section class="panel">
    <form method="GET" class="supplier-filters">
        <div class="form-group">
            <label for="q">Search</label>
            <input
                id="q"
                type="search"
                name="q"
                value="<?= $escape(
                    $filters['q'] ?? ''
                ) ?>"
                placeholder="Name, code, or email"
            >
        </div>

        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= $escape($store['id']) ?>"
                        <?= (int) (
                            $filters['store_id'] ?? 0
                        ) === (int) $store['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option
                    value="active"
                    <?= ($filters['status'] ?? '') ===
                        'active'
                            ? 'selected'
                            : '' ?>
                >
                    Active
                </option>
                <option
                    value="inactive"
                    <?= ($filters['status'] ?? '') ===
                        'inactive'
                            ? 'selected'
                            : '' ?>
                >
                    Inactive
                </option>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Filter
        </button>
    </form>
</section>

<br>

<section class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Store</th>
                <th>Type</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Products</th>
                <th>Purchase Orders</th>
                <th>Ordered Cost</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td>
                        <strong>
                            <?= $escape(
                                $supplier['name']
                            ) ?>
                        </strong>
                        <br>
                        <small>
                            <?= $escape(
                                $supplier['code']
                            ) ?>
                        </small>
                    </td>
                    <td>
                        <?= $escape(
                            $supplier['store_name']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $supplier[
                                        'supplier_type'
                                    ]
                                )
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                $supplier['status']
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $supplier['priority']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $supplier[
                                'mapped_products'
                            ]
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $supplier[
                                'purchase_orders'
                            ]
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) $supplier[
                                'ordered_cost'
                            ],
                            2
                        ) ?>
                    </td>
                    <td>
                        <a
                            href="/admin/suppliers/<?= $escape(
                                $supplier['id']
                            ) ?>"
                            class="table-link"
                        >
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="9">
                        No suppliers match these filters.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
