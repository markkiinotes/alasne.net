<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));
?>

<style>
.submission-filters {
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}
@media(max-width:950px) {
    .submission-filters {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Supplier Submission Queue</h1>
        <p>
            Prepared purchase orders awaiting manual
            transmission or future provider processing.
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
            href="/admin/suppliers"
            class="button-muted"
        >
            Suppliers
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
    <form method="GET" class="submission-filters">
        <div class="form-group">
            <label for="q">Search</label>
            <input
                id="q"
                type="search"
                name="q"
                value="<?= $escape(
                    $filters['q'] ?? ''
                ) ?>"
                placeholder="PO, order, supplier, external ID"
            >
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select
                id="supplier_id"
                name="supplier_id"
            >
                <option value="">All suppliers</option>
                <?php foreach (
                    $suppliers
                    as $supplier
                ): ?>
                    <option
                        value="<?= $escape(
                            $supplier['id']
                        ) ?>"
                        <?= (int) (
                            $filters[
                                'supplier_id'
                            ] ?? 0
                        ) === (int) $supplier['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape(
                            $supplier['name']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (
                    $statuses
                    as $status
                ): ?>
                    <option
                        value="<?= $escape($status) ?>"
                        <?= (
                            $filters['status']
                            ?? ''
                        ) === $status
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="provider_code">
                Adapter
            </label>
            <select
                id="provider_code"
                name="provider_code"
            >
                <option value="">All adapters</option>
                <option
                    value="manual_direct"
                    <?= (
                        $filters[
                            'provider_code'
                        ] ?? ''
                    ) === 'manual_direct'
                        ? 'selected'
                        : '' ?>
                >
                    Manual / Direct
                </option>
                <option
                    value="csv_feed"
                    <?= (
                        $filters[
                            'provider_code'
                        ] ?? ''
                    ) === 'csv_feed'
                        ? 'selected'
                        : '' ?>
                >
                    CSV Feed
                </option>
            </select>
        </div>

        <button
            type="submit"
            class="button-primary"
        >
            Filter
        </button>
    </form>
</section>

<br>

<section class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Submission</th>
                <th>Purchase Order</th>
                <th>Customer Order</th>
                <th>Supplier</th>
                <th>Adapter</th>
                <th>Channel</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Prepared</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach (
                $submissions
                as $submission
            ): ?>
                <tr>
                    <td>
                        #<?= $escape(
                            $submission['id']
                        ) ?>
                    </td>
                    <td>
                        <a
                            href="/admin/purchase-orders/<?= $escape(
                                $submission[
                                    'purchase_order_id'
                                ]
                            ) ?>"
                            class="table-link"
                        >
                            <?= $escape(
                                $submission[
                                    'purchase_order_number'
                                ]
                            ) ?>
                        </a>
                    </td>
                    <td>
                        <a
                            href="/admin/orders/<?= $escape(
                                $submission[
                                    'order_id'
                                ]
                            ) ?>"
                            class="table-link"
                        >
                            <?= $escape(
                                $submission[
                                    'order_number'
                                ]
                            ) ?>
                        </a>
                    </td>
                    <td>
                        <?= $escape(
                            $submission[
                                'supplier_name'
                            ]
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $label(
                                $submission[
                                    'provider_code'
                                ]
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $label(
                                $submission['channel']
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $label(
                                $submission['status']
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $submission[
                                'attempt_count'
                            ]
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $submission[
                                'prepared_at'
                            ]
                        ) ?>
                    </td>
                    <td>
                        <a
                            href="/admin/supplier-submissions/<?= $escape(
                                $submission['id']
                            ) ?>"
                            class="table-link"
                        >
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($submissions)): ?>
                <tr>
                    <td colspan="10">
                        No supplier submissions match the
                        current filters.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
