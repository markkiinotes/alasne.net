<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$filters = $report['filters'];
$summary = $report['summary'];
$breadcrumbs = $report['breadcrumbs'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));

$badgeClass = static function (mixed $value, string $mode = 'neutral'): string {
    if ($mode === 'good_bad') {
        return (float) $value >= 0
            ? 'kpi-badge kpi-good'
            : 'kpi-badge kpi-bad';
    }

    if ($mode === 'risk') {
        return (int) $value > 0
            ? 'kpi-badge kpi-bad'
            : 'kpi-badge kpi-good';
    }

    return 'kpi-badge kpi-neutral';
};
?>

<style>
.kpi-breadcrumbs {
    display:flex;
    gap:8px;
    align-items:center;
    margin:0 0 12px;
    color:#64748b;
    font-size:13px;
}
.kpi-breadcrumbs a {
    color:#2563eb;
    text-decoration:none;
}
.kpi-filter-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.kpi-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.kpi-card {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#ffffff;
}
.kpi-card small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.kpi-card strong {
    display:block;
    margin-top:6px;
    font-size:26px;
}
.kpi-card span {
    display:block;
    margin-top:4px;
    color:#64748b;
    font-size:13px;
}
.kpi-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.kpi-table-wrap {
    overflow-x:auto;
}
.kpi-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.kpi-good {
    background:#dcfce7;
    color:#166534;
}
.kpi-bad {
    background:#fee2e2;
    color:#991b1b;
}
.kpi-warning {
    background:#fef3c7;
    color:#92400e;
}
.kpi-neutral {
    background:#e2e8f0;
    color:#334155;
}
.kpi-action-links {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.kpi-panel-header {
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    margin-bottom:12px;
}
.kpi-panel-header h2 {
    margin:0;
}
.kpi-panel-header p {
    margin:4px 0 0;
    color:#64748b;
}
@media(max-width:1150px) {
    .kpi-filter-grid,
    .kpi-summary,
    .kpi-grid {
        grid-template-columns:1fr;
    }
}
</style>

<nav class="kpi-breadcrumbs" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $index => $crumb): ?>
        <?php if ($index > 0): ?>
            <span>/</span>
        <?php endif; ?>

        <?php if (! empty($crumb['url'])): ?>
            <a href="<?= $escape($crumb['url']) ?>">
                <?= $escape($crumb['label']) ?>
            </a>
        <?php else: ?>
            <span><?= $escape($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<section class="page-header">
    <div>
        <h1>Reports & KPI Center</h1>
        <p>
            Executive-level reporting for sales, margin, suppliers,
            operations, returns, sourcing, and store readiness.
        </p>
    </div>

    <div class="kpi-action-links">
        <a href="/admin" class="button-muted">
            Mission Control
        </a>
        <a href="/admin/reports/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">
            Export CSV
        </a>
    </div>
</section>

<section class="panel">
    <form method="GET" class="kpi-filter-grid">
        <div class="form-group">
            <label for="date_from">From</label>
            <input
                id="date_from"
                type="date"
                name="date_from"
                value="<?= $escape($filters['date_from']) ?>"
            >
        </div>

        <div class="form-group">
            <label for="date_to">To</label>
            <input
                id="date_to"
                type="date"
                name="date_to"
                value="<?= $escape($filters['date_to']) ?>"
            >
        </div>

        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($report['stores'] as $store): ?>
                    <option
                        value="<?= $escape($store['id']) ?>"
                        <?= (int) $filters['store_id'] === (int) $store['id'] ? 'selected' : '' ?>
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
                <?php foreach ($report['suppliers'] as $supplier): ?>
                    <option
                        value="<?= $escape($supplier['id']) ?>"
                        <?= (int) $filters['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>
                    >
                        <?= $escape($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Apply
        </button>
    </form>
</section>

<br>

<section class="kpi-summary">
    <article class="kpi-card">
        <small>Sales Revenue</small>
        <strong><?= $money($summary['revenue']) ?></strong>
        <span><?= $number($summary['paid_orders']) ?> paid order(s)</span>
    </article>

    <article class="kpi-card">
        <small>Average Order Value</small>
        <strong><?= $money($summary['average_order_value']) ?></strong>
        <span>Across paid orders</span>
    </article>

    <article class="kpi-card">
        <small>Gross Profit</small>
        <strong><?= $money($summary['gross_profit']) ?></strong>
        <span><?= $percent($summary['margin_percent']) ?> estimated margin</span>
    </article>

    <article class="kpi-card">
        <small>Supplier Cost</small>
        <strong><?= $money($summary['supplier_cost']) ?></strong>
        <span>Estimated supplier cost</span>
    </article>

    <article class="kpi-card">
        <small>Refunds</small>
        <strong><?= $money($summary['refunds']) ?></strong>
        <span>Refund activity detected</span>
    </article>

    <article class="kpi-card">
        <small>Store Credit</small>
        <strong><?= $money($summary['store_credit_issued']) ?></strong>
        <span><?= $money($summary['store_credit_redeemed']) ?> redeemed</span>
    </article>

    <article class="kpi-card">
        <small>Operational Risk</small>
        <strong><?= $number($summary['tracking_gaps'] + $summary['open_exceptions'] + $summary['failed_submissions']) ?></strong>
        <span>Tracking gaps, exceptions, failed submissions</span>
    </article>

    <article class="kpi-card">
        <small>Store Readiness</small>
        <strong><?= $number($summary['blocked_stores']) ?></strong>
        <span>Blocked store(s)</span>
    </article>
</section>

<br>

<section class="kpi-grid">
    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Sales by Store</h2>
                <p>Revenue, cost, profit, and margin by store.</p>
            </div>
            <a href="/admin/multi-store-automation" class="table-link">
                Store Scaling
            </a>
        </div>

        <div class="kpi-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Supplier Cost</th>
                        <th>Gross Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($report['salesByStore'] as $row): ?>
                        <tr>
                            <td><?= $escape($row['store_name']) ?></td>
                            <td><?= $number($row['paid_orders']) ?></td>
                            <td><?= $money($row['revenue']) ?></td>
                            <td><?= $money($row['supplier_cost']) ?></td>
                            <td><?= $money($row['gross_profit']) ?></td>
                            <td>
                                <span class="<?= $escape($badgeClass($row['margin_percent'], 'good_bad')) ?>">
                                    <?= $percent($row['margin_percent']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($report['salesByStore'])): ?>
                        <tr>
                            <td colspan="6">
                                No sales data found for this filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Supplier Performance</h2>
                <p>Supplier revenue, cost, profit, score, and order quality.</p>
            </div>
            <a href="/admin/supplier-performance" class="table-link">
                Supplier Report
            </a>
        </div>

        <div class="kpi-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>POs</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit</th>
                        <th>Score</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($report['supplierPerformance'] as $row): ?>
                        <tr>
                            <td>
                                <?= $escape($row['supplier_name']) ?>
                                <br>
                                <small><?= $escape($row['supplier_code'] ?? '') ?></small>
                            </td>
                            <td><?= $number($row['purchase_orders']) ?></td>
                            <td><?= $money($row['revenue']) ?></td>
                            <td><?= $money($row['supplier_cost']) ?></td>
                            <td><?= $money($row['gross_profit']) ?></td>
                            <td>
                                <?= $row['performance_score'] !== null
                                    ? number_format((float) $row['performance_score'], 1)
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($report['supplierPerformance'])): ?>
                        <tr>
                            <td colspan="6">
                                No supplier performance data found for this filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<br>

<section class="kpi-grid">
    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Operations</h2>
                <p>Fulfillment risks and action queues.</p>
            </div>
            <a href="/admin/dropshipping" class="table-link">
                Operations Center
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Detail</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($report['operations'] as $row): ?>
                    <tr>
                        <td><?= $escape($row['metric']) ?></td>
                        <td>
                            <span class="<?= $escape($badgeClass($row['value'], 'risk')) ?>">
                                <?= $number($row['value']) ?>
                            </span>
                        </td>
                        <td><?= $escape($row['detail']) ?></td>
                        <td>
                            <a href="<?= $escape($row['url']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Returns</h2>
                <p>Return volume and approved value by status.</p>
            </div>
            <a href="/admin/returns" class="table-link">
                Returns
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Returns</th>
                    <th>Approved Value</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($report['returns'] as $row): ?>
                    <tr>
                        <td><?= $escape($label((string) $row['status'])) ?></td>
                        <td><?= $number($row['return_count']) ?></td>
                        <td><?= $money($row['approved_value']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($report['returns'])): ?>
                    <tr>
                        <td colspan="3">
                            No return data found for this filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="kpi-grid">
    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Store Readiness</h2>
                <p>Launch health and automation status by store.</p>
            </div>
            <a href="/admin/multi-store-automation" class="table-link">
                Multi-Store
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Launch</th>
                    <th>Store Status</th>
                    <th>Health</th>
                    <th>Last Audit</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($report['storeReadiness'] as $row): ?>
                    <tr>
                        <td><?= $escape($row['store_name']) ?></td>
                        <td><?= $escape($label((string) ($row['automation_launch_status'] ?? 'planning'))) ?></td>
                        <td><?= $escape($label((string) ($row['store_status'] ?? ''))) ?></td>
                        <td>
                            <?= $row['automation_health_score'] !== null
                                ? number_format((float) $row['automation_health_score'], 1)
                                : '—' ?>
                        </td>
                        <td><?= $escape($row['last_automation_audit_at'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($report['storeReadiness'])): ?>
                    <tr>
                        <td colspan="5">
                            No store readiness data found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <div class="kpi-panel-header">
            <div>
                <h2>Product Sourcing</h2>
                <p>Supplier product review status and average sourcing score.</p>
            </div>
            <a href="/admin/product-sourcing" class="table-link">
                Sourcing Scanner
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Products</th>
                    <th>Average Score</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($report['sourcing'] as $row): ?>
                    <tr>
                        <td><?= $escape($label((string) $row['status'])) ?></td>
                        <td><?= $number($row['product_count']) ?></td>
                        <td><?= number_format((float) $row['average_score'], 1) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($report['sourcing'])): ?>
                    <tr>
                        <td colspan="3">
                            No sourcing data found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <div class="kpi-panel-header">
        <div>
            <h2>Recent Orders</h2>
            <p>Latest customer orders in the selected range.</p>
        </div>
        <a href="/admin/orders" class="table-link">
            Orders
        </a>
    </div>

    <div class="kpi-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Store</th>
                    <th>Payment</th>
                    <th>Dropship</th>
                    <th>Total</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($report['recentOrders'] as $order): ?>
                    <tr>
                        <td><?= $escape($order['order_number'] ?? ('#' . $order['id'])) ?></td>
                        <td><?= $escape($order['store_name'] ?? '') ?></td>
                        <td><?= $escape($label((string) ($order['payment_status'] ?? ''))) ?></td>
                        <td><?= $escape($label((string) ($order['dropship_status'] ?? ''))) ?></td>
                        <td><?= $money($order['order_total']) ?></td>
                        <td><?= $escape($order['created_at'] ?? '') ?></td>
                        <td>
                            <a href="/admin/orders/<?= $escape($order['id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($report['recentOrders'])): ?>
                    <tr>
                        <td colspan="7">
                            No recent orders found for this filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
