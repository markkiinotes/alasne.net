<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$summary = $dashboard['summary'];
$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));
?>

<style>
.tracking-filter-grid {
    display:grid;
    grid-template-columns:1.5fr 1.5fr auto;
    gap:12px;
    align-items:end;
}
.tracking-upload-grid {
    display:grid;
    grid-template-columns:1fr 1fr auto;
    gap:12px;
    align-items:end;
}
.tracking-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.tracking-summary article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.tracking-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.tracking-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.tracking-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.tracking-ok {
    background:#dcfce7;
    color:#166534;
}
.tracking-watch {
    background:#fef3c7;
    color:#92400e;
}
.tracking-bad {
    background:#fee2e2;
    color:#991b1b;
}
.tracking-neutral {
    background:#e2e8f0;
    color:#334155;
}
.tracking-table-wrap {
    overflow-x:auto;
}
@media(max-width:1050px) {
    .tracking-filter-grid,
    .tracking-upload-grid,
    .tracking-summary,
    .tracking-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Automated Tracking Reconciliation</h1>
        <p>
            Import supplier tracking, match it to purchase orders,
            update customer timelines, and surface tracking gaps.
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/dropshipping" class="button-muted">
            Operations Command Center
        </a>

        <a href="/admin/supplier-performance" class="button-muted">
            Supplier Performance
        </a>

        <a
            href="/admin/tracking-reconciliation/queue/export<?= $query !== '' ? '?' . $escape($query) : '' ?>"
            class="button-primary"
        >
            Export Queue
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
    <form method="GET" class="tracking-filter-grid">
        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= $escape($store['id']) ?>"
                        <?= (int) ($filters['store_id'] ?? 0) === (int) $store['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option
                        value="<?= $escape($supplier['id']) ?>"
                        <?= (int) ($filters['supplier_id'] ?? 0) === (int) $supplier['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Filter
        </button>
    </form>
</section>

<br>

<section class="tracking-summary">
    <article>
        <small>Purchase Orders</small>
        <strong><?= $escape($summary['purchase_orders']) ?></strong>
    </article>

    <article>
        <small>Missing Tracking</small>
        <strong><?= $escape($summary['missing_tracking']) ?></strong>
    </article>

    <article>
        <small>Never Reconciled</small>
        <strong><?= $escape($summary['never_reconciled']) ?></strong>
    </article>

    <article>
        <small>Tracking Exceptions</small>
        <strong><?= $escape($summary['tracking_exceptions']) ?></strong>
    </article>

    <article>
        <small>Delivered</small>
        <strong><?= $escape($summary['delivered']) ?></strong>
    </article>

    <article>
        <small>Import Runs</small>
        <strong><?= $escape($summary['runs']) ?></strong>
    </article>
</section>

<br>

<section class="panel form-panel">
    <div class="table-header">
        <div>
            <h2>Upload Supplier Tracking CSV</h2>
            <p>
                Valid rows update purchase orders immediately.
                Unmatched, duplicate, or invalid rows are logged
                without discarding the whole file.
            </p>
        </div>

        <a
            href="/admin/tracking-reconciliation/template"
            class="button-muted"
        >
            Download Template
        </a>
    </div>

    <form
        method="POST"
        action="/admin/tracking-reconciliation/upload<?= $query !== '' ? '?' . $escape($query) : '' ?>"
        enctype="multipart/form-data"
        class="tracking-upload-grid"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="form-group">
            <label for="tracking_csv">Tracking CSV</label>
            <input
                id="tracking_csv"
                type="file"
                name="tracking_csv"
                accept=".csv,text/csv"
                required
            >
            <small class="form-help">
                Maximum file size: 10 MB.
            </small>
        </div>

        <div class="form-group">
            <label>Required columns</label>
            <p class="form-help">
                tracking_number plus one match field:
                purchase_order_number, supplier_order_id,
                provider_order_id, external_order_id, or
                supplier_reference.
            </p>
        </div>

        <button type="submit" class="button-primary">
            Reconcile Tracking
        </button>
    </form>
</section>

<br>

<section class="tracking-two-column">
    <section class="panel">
        <h2>Recent Import Runs</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Status</th>
                    <th>Matched</th>
                    <th>Updated</th>
                    <th>Failed</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['runs'] as $run): ?>
                    <tr>
                        <td>#<?= $escape($run['id']) ?></td>
                        <td>
                            <span class="tracking-badge <?= in_array($run['status'], ['succeeded'], true)
                                ? 'tracking-ok'
                                : (in_array($run['status'], ['partial'], true)
                                    ? 'tracking-watch'
                                    : 'tracking-bad') ?>">
                                <?= $escape($label($run['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($run['rows_matched']) ?></td>
                        <td><?= $escape($run['rows_updated']) ?></td>
                        <td><?= $escape($run['rows_failed']) ?></td>
                        <td>
                            <a
                                href="/admin/tracking-reconciliation/runs/<?= $escape($run['id']) ?>"
                                class="table-link"
                            >
                                Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['runs'])): ?>
                    <tr>
                        <td colspan="6">
                            No tracking reconciliation runs yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Unmatched / Problem Rows</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Status</th>
                    <th>PO</th>
                    <th>Tracking</th>
                    <th>Message</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['unmatchedRows'] as $row): ?>
                    <tr>
                        <td>
                            <a
                                href="/admin/tracking-reconciliation/runs/<?= $escape($row['run_id']) ?>"
                                class="table-link"
                            >
                                #<?= $escape($row['run_id']) ?>
                            </a>
                        </td>
                        <td><?= $escape($label($row['status'])) ?></td>
                        <td><?= $escape($row['purchase_order_number'] ?? '—') ?></td>
                        <td><?= $escape($row['tracking_number'] ?? '—') ?></td>
                        <td><?= $escape($row['message'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['unmatchedRows'])): ?>
                    <tr>
                        <td colspan="5">
                            No problem rows from recent imports.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Tracking Reconciliation Queue</h2>
            <p>
                Purchase orders that are missing tracking,
                have unknown tracking state, or have never been
                reconciled.
            </p>
        </div>
    </div>

    <div class="tracking-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Purchase Order</th>
                    <th>Customer Order</th>
                    <th>Supplier</th>
                    <th>PO Status</th>
                    <th>Tracking Status</th>
                    <th>Carrier</th>
                    <th>Tracking</th>
                    <th>Last Reconciled</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['queue'] as $row): ?>
                    <tr>
                        <td><?= $escape($row['purchase_order_number']) ?></td>
                        <td>
                            <a
                                href="/admin/orders/<?= $escape($row['order_id']) ?>"
                                class="table-link"
                            >
                                <?= $escape($row['order_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?= $escape($row['supplier_name']) ?>
                            <br>
                            <small><?= $escape($row['supplier_code']) ?></small>
                        </td>
                        <td><?= $escape($label($row['status'])) ?></td>
                        <td>
                            <?= $escape($label($row['tracking_status'] ?? 'unknown')) ?>
                        </td>
                        <td><?= $escape($row['shipping_carrier'] ?? '—') ?></td>
                        <td><?= $escape($row['tracking_number'] ?? '—') ?></td>
                        <td><?= $escape($row['last_tracking_reconciled_at'] ?? 'Never') ?></td>
                        <td>
                            <a
                                href="/admin/purchase-orders/<?= $escape($row['id']) ?>"
                                class="table-link"
                            >
                                Open PO
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['queue'])): ?>
                    <tr>
                        <td colspan="9">
                            No tracking reconciliation work is currently queued.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="tracking-two-column">
    <section class="panel">
        <h2>Duplicate Tracking Numbers</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Carrier</th>
                    <th>Tracking Number</th>
                    <th>PO Count</th>
                    <th>Last Seen</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['duplicateTracking'] as $row): ?>
                    <tr>
                        <td><?= $escape($row['carrier'] ?? '—') ?></td>
                        <td><?= $escape($row['tracking_number']) ?></td>
                        <td><?= $escape($row['po_count']) ?></td>
                        <td><?= $escape($row['last_seen_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['duplicateTracking'])): ?>
                    <tr>
                        <td colspan="4">
                            No duplicate tracking numbers detected.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Recent Tracking Records</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>PO</th>
                    <th>Carrier</th>
                    <th>Tracking</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['recentTracking'] as $row): ?>
                    <tr>
                        <td><?= $escape($row['purchase_order_number']) ?></td>
                        <td><?= $escape($row['carrier'] ?? '—') ?></td>
                        <td><?= $escape($row['tracking_number']) ?></td>
                        <td><?= $escape($label($row['normalized_status'])) ?></td>
                        <td><?= $escape($row['last_seen_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['recentTracking'])): ?>
                    <tr>
                        <td colspan="5">
                            No tracking records imported yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>
