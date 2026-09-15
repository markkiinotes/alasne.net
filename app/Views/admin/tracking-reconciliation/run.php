<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));
?>

<style>
.run-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.run-summary article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.run-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.run-table-wrap {
    overflow-x:auto;
}
@media(max-width:1000px) {
    .run-summary {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Tracking Reconciliation Run #<?= $escape($run['id']) ?></h1>
        <p>
            <?= $escape($run['source_file_name'] ?? 'CSV upload') ?>
            ·
            <?= $escape($label($run['status'])) ?>
        </p>
    </div>

    <a
        href="/admin/tracking-reconciliation"
        class="button-muted"
    >
        Back to Reconciliation
    </a>
</section>

<section class="run-summary">
    <article>
        <small>Received</small>
        <strong><?= $escape($run['rows_received']) ?></strong>
    </article>
    <article>
        <small>Matched</small>
        <strong><?= $escape($run['rows_matched']) ?></strong>
    </article>
    <article>
        <small>Updated</small>
        <strong><?= $escape($run['rows_updated']) ?></strong>
    </article>
    <article>
        <small>Skipped</small>
        <strong><?= $escape($run['rows_skipped']) ?></strong>
    </article>
    <article>
        <small>Failed</small>
        <strong><?= $escape($run['rows_failed']) ?></strong>
    </article>
    <article>
        <small>Finished</small>
        <strong><?= $escape($run['finished_at'] ?? 'Running') ?></strong>
    </article>
</section>

<?php if (! empty($run['error_message'])): ?>
    <br>
    <div class="alert-danger">
        <?= $escape($run['error_message']) ?>
    </div>
<?php endif; ?>

<br>

<section class="panel">
    <h2>Rows</h2>

    <div class="run-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Status</th>
                    <th>Match</th>
                    <th>Purchase Order</th>
                    <th>Supplier Order</th>
                    <th>Carrier</th>
                    <th>Tracking</th>
                    <th>Shipment Status</th>
                    <th>Normalized</th>
                    <th>Message</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $escape($row['row_number']) ?></td>
                        <td><?= $escape($label($row['status'])) ?></td>
                        <td><?= $escape($row['match_strategy'] ?? '—') ?></td>
                        <td>
                            <?php if (! empty($row['purchase_order_id'])): ?>
                                <a
                                    href="/admin/purchase-orders/<?= $escape($row['purchase_order_id']) ?>"
                                    class="table-link"
                                >
                                    <?= $escape($row['purchase_order_number'] ?? ('PO #' . $row['purchase_order_id'])) ?>
                                </a>
                            <?php else: ?>
                                <?= $escape($row['purchase_order_number'] ?? '—') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($row['supplier_order_id'] ?? '—') ?></td>
                        <td><?= $escape($row['carrier'] ?? '—') ?></td>
                        <td><?= $escape($row['tracking_number'] ?? '—') ?></td>
                        <td><?= $escape($row['shipment_status'] ?? '—') ?></td>
                        <td><?= $escape($row['normalized_status'] ?? '—') ?></td>
                        <td><?= $escape($row['message'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="10">
                            No row records were stored for this run.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
