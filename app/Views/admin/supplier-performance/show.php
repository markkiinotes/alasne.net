<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$scorecard = $detail['scorecard'];
$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== '' && $value !== null && $value !== 0
));
?>

<style>
.performance-detail-grid {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.performance-detail-grid article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.performance-detail-grid small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.performance-section-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.performance-risk-list {
    margin:.35rem 0 0;
    padding-left:1.1rem;
    color:#64748b;
}
@media(max-width:1000px) {
    .performance-detail-grid,
    .performance-section-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1><?= $escape($scorecard['supplier_name']) ?></h1>
        <p>
            Supplier performance detail ·
            <?= $escape($scorecard['supplier_code']) ?>
            ·
            <?= $escape($scorecard['store_name']) ?>
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/suppliers/<?= $escape($scorecard['supplier_id']) ?>" class="button-muted">
            Supplier Profile
        </a>
        <a href="/admin/supplier-performance<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-muted">
            Back to Report
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="performance-detail-grid">
    <article>
        <small>Score</small>
        <strong><?= number_format((float) $scorecard['score'], 1) ?></strong>
    </article>
    <article>
        <small>Recommendation</small>
        <strong><?= $escape($label($scorecard['recommendation'])) ?></strong>
    </article>
    <article>
        <small>Revenue</small>
        <strong><?= $money($scorecard['revenue']) ?></strong>
    </article>
    <article>
        <small>Gross Profit</small>
        <strong><?= $money($scorecard['gross_profit']) ?></strong>
    </article>
    <article>
        <small>Margin</small>
        <strong><?= $percent($scorecard['margin_percent']) ?></strong>
    </article>
</section>

<br>

<section class="performance-section-grid">
    <section class="panel">
        <h2>Risk Notes</h2>
        <?php if (! empty($scorecard['risk_notes'])): ?>
            <ul class="performance-risk-list">
                <?php foreach ($scorecard['risk_notes'] as $note): ?>
                    <li><?= $escape($note) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No major supplier risks detected for this period.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Fulfillment Metrics</h2>
        <table class="detail-table">
            <tr><th>Purchase Orders</th><td><?= $escape($scorecard['purchase_order_count']) ?></td></tr>
            <tr><th>Delivered</th><td><?= $escape($scorecard['delivered_count']) ?></td></tr>
            <tr><th>Late</th><td><?= $escape($scorecard['late_purchase_order_count']) ?></td></tr>
            <tr><th>Failed Submissions</th><td><?= $escape($scorecard['failed_submission_count']) ?></td></tr>
            <tr><th>Missing Tracking</th><td><?= $escape($scorecard['missing_tracking_count']) ?></td></tr>
            <tr><th>Open Exceptions</th><td><?= $escape($scorecard['open_exception_count']) ?></td></tr>
            <tr><th>Returns</th><td><?= $escape($scorecard['return_count']) ?></td></tr>
            <tr><th>Avg Hours to Ship</th><td><?= $escape($scorecard['avg_hours_to_ship'] !== null ? number_format((float) $scorecard['avg_hours_to_ship'], 1) : '—') ?></td></tr>
            <tr><th>Avg Hours to Deliver</th><td><?= $escape($scorecard['avg_hours_to_deliver'] !== null ? number_format((float) $scorecard['avg_hours_to_deliver'], 1) : '—') ?></td></tr>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <h2>Recent Purchase Orders</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Purchase Order</th>
                <th>Status</th>
                <th>Submission</th>
                <th>Cost</th>
                <th>Revenue</th>
                <th>Profit</th>
                <th>Tracking</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detail['purchaseOrders'] as $po): ?>
                <tr>
                    <td><?= $escape($po['purchase_order_number']) ?></td>
                    <td><?= $escape($label($po['status'])) ?></td>
                    <td><?= $escape($label($po['submission_status'] ?? 'not_prepared')) ?></td>
                    <td><?= $money($po['total_cost']) ?></td>
                    <td><?= $money($po['customer_revenue']) ?></td>
                    <td><?= $money($po['estimated_profit']) ?></td>
                    <td><?= $escape($po['tracking_number'] ?? '—') ?></td>
                    <td>
                        <a href="/admin/purchase-orders/<?= $escape($po['id']) ?>" class="table-link">
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($detail['purchaseOrders'])): ?>
                <tr><td colspan="8">No purchase orders for this period.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<br>

<section class="performance-section-grid">
    <section class="panel">
        <h2>Failed Submissions</h2>
        <table class="data-table">
            <thead><tr><th>Submission</th><th>Error</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($detail['failedSubmissions'] as $submission): ?>
                    <tr>
                        <td>#<?= $escape($submission['id']) ?></td>
                        <td><?= $escape($submission['error_message'] ?? '—') ?></td>
                        <td><a href="/admin/supplier-submissions/<?= $escape($submission['id']) ?>" class="table-link">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($detail['failedSubmissions'])): ?>
                    <tr><td colspan="3">No failed submissions.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Performance Reviews</h2>
        <table class="data-table">
            <thead><tr><th>Period</th><th>Status</th><th>Score</th><th>Note</th></tr></thead>
            <tbody>
                <?php foreach ($detail['reviews'] as $review): ?>
                    <tr>
                        <td><?= $escape($review['period_start']) ?> → <?= $escape($review['period_end']) ?></td>
                        <td><?= $escape($label($review['status'])) ?></td>
                        <td><?= number_format((float) $review['score'], 1) ?></td>
                        <td><?= $escape($review['review_note'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($detail['reviews'])): ?>
                    <tr><td colspan="4">No performance reviews saved yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>
