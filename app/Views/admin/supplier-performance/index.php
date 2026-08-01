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

$badge = static function (string $value): string {
    return match ($value) {
        'keep' => 'performance-badge performance-keep',
        'replace' => 'performance-badge performance-replace',
        'watch' => 'performance-badge performance-watch',
        default => 'performance-badge performance-neutral',
    };
};

$summary = $dashboard['summary'];
$scorecards = $dashboard['scorecards'];
$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== '' && $value !== null && $value !== 0
));
?>

<style>
.performance-filter-grid {
    display:grid;
    grid-template-columns:1.4fr 1.4fr 1fr 1fr 1fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}
.performance-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.performance-summary article,
.performance-mini-card {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.performance-summary small,
.performance-mini-card small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.performance-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.performance-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-weight:800;
    font-size:12px;
}
.performance-keep {
    background:#dcfce7;
    color:#166534;
}
.performance-watch {
    background:#fef3c7;
    color:#92400e;
}
.performance-replace {
    background:#fee2e2;
    color:#991b1b;
}
.performance-neutral {
    background:#e2e8f0;
    color:#334155;
}
.performance-risk-list {
    margin:.35rem 0 0;
    padding-left:1.1rem;
    color:#64748b;
    font-size:12px;
}
.performance-review-form {
    display:grid;
    min-width:220px;
    gap:7px;
}
.performance-table-wrap {
    overflow-x:auto;
}
@media(max-width:1250px) {
    .performance-filter-grid,
    .performance-summary,
    .performance-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Supplier Performance Reporting</h1>
        <p>
            Measure profitability, fulfillment reliability,
            tracking quality, returns, exceptions, and supplier
            risk after orders begin flowing.
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/product-sourcing" class="button-muted">
            Product Sourcing
        </a>

        <a href="/admin/dropshipping" class="button-muted">
            Operations Command Center
        </a>

        <a
            href="/admin/supplier-performance/export<?= $query !== '' ? '?' . $escape($query) : '' ?>"
            class="button-primary"
        >
            Export Performance CSV
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
    <form method="GET" class="performance-filter-grid">
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

        <div class="form-group">
            <label for="recommendation">Recommendation</label>
            <select id="recommendation" name="recommendation">
                <option value="">All</option>
                <?php foreach (['keep', 'watch', 'replace'] as $recommendation): ?>
                    <option
                        value="<?= $escape($recommendation) ?>"
                        <?= ($filters['recommendation'] ?? '') === $recommendation
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($label($recommendation)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="lookback_days">Lookback Days</label>
            <input
                id="lookback_days"
                type="number"
                name="lookback_days"
                min="1"
                value="<?= $escape($filters['lookback_days'] ?? 90) ?>"
            >
        </div>

        <div class="form-group">
            <label for="date_from">Date From</label>
            <input
                id="date_from"
                type="date"
                name="date_from"
                value="<?= $escape($filters['date_from'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="date_to">Date To</label>
            <input
                id="date_to"
                type="date"
                name="date_to"
                value="<?= $escape($filters['date_to'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="target_margin">Target Margin %</label>
            <input
                id="target_margin"
                type="number"
                name="target_margin"
                min="0"
                step="0.1"
                value="<?= $escape($filters['target_margin'] ?? 25) ?>"
            >
        </div>

        <button type="submit" class="button-primary">
            Report
        </button>
    </form>
</section>

<br>

<section class="performance-summary">
    <article>
        <small>Suppliers</small>
        <strong><?= $escape($summary['suppliers']) ?></strong>
    </article>
    <article>
        <small>Keep</small>
        <strong><?= $escape($summary['keep']) ?></strong>
    </article>
    <article>
        <small>Watch</small>
        <strong><?= $escape($summary['watch']) ?></strong>
    </article>
    <article>
        <small>Replace</small>
        <strong><?= $escape($summary['replace']) ?></strong>
    </article>
    <article>
        <small>Gross Profit</small>
        <strong><?= $money($summary['gross_profit']) ?></strong>
    </article>
    <article>
        <small>Avg Score</small>
        <strong><?= number_format((float) $summary['average_score'], 1) ?></strong>
    </article>
</section>

<br>

<section class="performance-two-column">
    <section class="panel">
        <h2>Problems to Watch</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Late Purchase Orders</td>
                    <td><?= $escape($summary['late_purchase_orders']) ?></td>
                </tr>
                <tr>
                    <td>Failed Supplier Submissions</td>
                    <td><?= $escape($summary['failed_submissions']) ?></td>
                </tr>
                <tr>
                    <td>Missing Tracking</td>
                    <td><?= $escape($summary['missing_tracking']) ?></td>
                </tr>
                <tr>
                    <td>Open Exceptions</td>
                    <td><?= $escape($summary['open_exceptions']) ?></td>
                </tr>
                <tr>
                    <td>Returns</td>
                    <td><?= $escape($summary['returns']) ?></td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Financial Overview</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Customer Revenue</td>
                    <td><?= $money($summary['revenue']) ?></td>
                </tr>
                <tr>
                    <td>Supplier Cost</td>
                    <td><?= $money($summary['supplier_cost']) ?></td>
                </tr>
                <tr>
                    <td>Gross Profit</td>
                    <td><?= $money($summary['gross_profit']) ?></td>
                </tr>
                <tr>
                    <td>Average Margin</td>
                    <td><?= $percent($summary['average_margin']) ?></td>
                </tr>
                <tr>
                    <td>Purchase Orders</td>
                    <td><?= $escape($summary['purchase_orders']) ?></td>
                </tr>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="performance-two-column">
    <section class="panel">
        <h2>Late Purchase Orders</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>PO</th>
                    <th>Supplier</th>
                    <th>Hours Late</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dashboard['latePurchaseOrders'] as $po): ?>
                    <tr>
                        <td><?= $escape($po['purchase_order_number']) ?></td>
                        <td><?= $escape($po['supplier_name']) ?></td>
                        <td><?= $escape($po['hours_late'] ?? '—') ?></td>
                        <td>
                            <a href="/admin/purchase-orders/<?= $escape($po['id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['latePurchaseOrders'])): ?>
                    <tr>
                        <td colspan="4">No late purchase orders.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Failed Supplier Submissions</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Submission</th>
                    <th>Supplier</th>
                    <th>Error</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dashboard['failedSubmissions'] as $submission): ?>
                    <tr>
                        <td>#<?= $escape($submission['id']) ?></td>
                        <td><?= $escape($submission['supplier_name']) ?></td>
                        <td><?= $escape($submission['error_message'] ?? '—') ?></td>
                        <td>
                            <a href="/admin/supplier-submissions/<?= $escape($submission['id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['failedSubmissions'])): ?>
                    <tr>
                        <td colspan="4">No failed submissions.</td>
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
            <h2>Supplier Scorecards</h2>
            <p>
                Score combines margin, late purchase orders,
                failed submissions, tracking gaps, open exceptions,
                returns, and supplier status.
            </p>
        </div>
    </div>

    <div class="performance-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Score</th>
                    <th>Recommendation</th>
                    <th>Supplier</th>
                    <th>Orders</th>
                    <th>Revenue</th>
                    <th>Cost</th>
                    <th>Profit</th>
                    <th>Margin</th>
                    <th>Late</th>
                    <th>Failed</th>
                    <th>Tracking</th>
                    <th>Returns</th>
                    <th>Risks</th>
                    <th>Review</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($scorecards as $row): ?>
                    <tr>
                        <td>
                            <strong><?= number_format((float) $row['score'], 1) ?></strong>
                        </td>
                        <td>
                            <span class="<?= $escape($badge($row['recommendation'])) ?>">
                                <?= $escape($label($row['recommendation'])) ?>
                            </span>
                        </td>
                        <td>
                            <a
                                href="/admin/supplier-performance/<?= $escape($row['supplier_id']) ?><?= $query !== '' ? '?' . $escape($query) : '' ?>"
                                class="table-link"
                            >
                                <?= $escape($row['supplier_name']) ?>
                            </a>
                            <br>
                            <small><?= $escape($row['supplier_code']) ?> · <?= $escape($row['store_name']) ?></small>
                        </td>
                        <td><?= $escape($row['purchase_order_count']) ?></td>
                        <td><?= $money($row['revenue']) ?></td>
                        <td><?= $money($row['supplier_cost']) ?></td>
                        <td><?= $money($row['gross_profit']) ?></td>
                        <td><?= $percent($row['margin_percent']) ?></td>
                        <td><?= $escape($row['late_purchase_order_count']) ?></td>
                        <td><?= $escape($row['failed_submission_count']) ?></td>
                        <td><?= $escape($row['missing_tracking_count']) ?></td>
                        <td><?= $escape($row['return_count']) ?></td>
                        <td>
                            <?php if (! empty($row['risk_notes'])): ?>
                                <ul class="performance-risk-list">
                                    <?php foreach (array_slice($row['risk_notes'], 0, 3) as $note): ?>
                                        <li><?= $escape($note) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="performance-badge performance-keep">Clean</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form
                                method="POST"
                                action="/admin/supplier-performance/<?= $escape($row['supplier_id']) ?>/review"
                                class="performance-review-form"
                            >
                                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                                <?php foreach ([
                                    'store_id',
                                    'supplier_id',
                                    'recommendation',
                                    'lookback_days',
                                    'date_from',
                                    'date_to',
                                    'target_margin',
                                ] as $filterField): ?>
                                    <input
                                        type="hidden"
                                        name="filter_<?= $escape($filterField) ?>"
                                        value="<?= $escape($filters[$filterField] ?? '') ?>"
                                    >
                                <?php endforeach; ?>

                                <select name="status">
                                    <?php foreach ([
                                        'keep' => 'Keep',
                                        'watch' => 'Watch',
                                        'replace' => 'Replace',
                                        'unreviewed' => 'Reset',
                                    ] as $value => $text): ?>
                                        <option
                                            value="<?= $escape($value) ?>"
                                            <?= ($row['performance_status'] ?? '') === $value ? 'selected' : '' ?>
                                        >
                                            <?= $escape($text) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <input
                                    type="text"
                                    name="review_note"
                                    value="<?= $escape($row['performance_review_note'] ?? '') ?>"
                                    placeholder="Review note"
                                >

                                <button type="submit" class="button-muted">
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($scorecards)): ?>
                    <tr>
                        <td colspan="14">
                            No supplier performance data found for this period.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
