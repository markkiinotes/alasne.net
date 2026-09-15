<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string) $value));

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$queryString = http_build_query([
    'store_id' => $filters['store_id'] ?? 0,
    'supplier_id' => $filters['supplier_id'] ?? 0,
    'lookback_days' => $filters['lookback_days'] ?? 30,
    'min_margin' => $filters['min_margin'] ?? 20,
]);

$summary = $dashboard['summary'];

$attentionLevel = (int) $summary['attention_total'] > 0
    ? 'Needs Attention'
    : 'Clear';
?>

<style>
.ops-header-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}
.ops-filters {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.ops-scoreboard {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.ops-card {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:14px;
    background:#f8fafc;
}
.ops-card.critical {
    border-color:#fecaca;
    background:#fff7f7;
}
.ops-card.warning {
    border-color:#fde68a;
    background:#fffbeb;
}
.ops-card.success {
    border-color:#bbf7d0;
    background:#f0fdf4;
}
.ops-card small {
    display:block;
    font-weight:800;
    color:#64748b;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.ops-card strong {
    display:block;
    margin-top:4px;
    font-size:26px;
}
.ops-card span {
    display:block;
    margin-top:4px;
    color:#475569;
}
.ops-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.ops-table-scroll {
    width:100%;
    overflow-x:auto;
}
.ops-pill {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    background:#e2e8f0;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.ops-pill-danger {
    background:#fee2e2;
    color:#991b1b;
}
.ops-pill-warn {
    background:#fef3c7;
    color:#92400e;
}
.ops-pill-good {
    background:#dcfce7;
    color:#166534;
}
.ops-empty {
    padding:16px;
    border:1px dashed #cbd5e1;
    border-radius:12px;
    color:#64748b;
    background:#f8fafc;
}
.ops-activity {
    border-left:3px solid #cbd5e1;
    padding:2px 0 14px 16px;
    margin-bottom:12px;
}
.ops-activity strong {
    display:block;
}
.ops-activity small {
    color:#64748b;
}
@media(max-width:1100px) {
    .ops-filters,
    .ops-scoreboard,
    .ops-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Dropshipping Operations</h1>
        <p>
            Supplier routing, purchase orders, submissions,
            exceptions, tracking, sync health, and supplier
            margin in one Mission Control screen.
        </p>
    </div>

    <div class="ops-header-actions">
        <a href="/admin/suppliers" class="button-muted">
            Suppliers
        </a>
        <a href="/admin/purchase-orders" class="button-muted">
            Purchase Orders
        </a>
        <a href="/admin/supplier-submissions" class="button-muted">
            Submissions
        </a>
        <a
            href="/admin/dropshipping/export?<?= $escape($queryString) ?>"
            class="button-primary"
        >
            Export Action Queue
        </a>
    </div>
</section>

<section class="panel">
    <form method="GET" class="ops-filters">
        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="0">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= $escape($store['id']) ?>"
                        <?= (int) ($filters['store_id'] ?? 0) === (int) $store['id'] ? 'selected' : '' ?>
                    >
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="0">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option
                        value="<?= $escape($supplier['id']) ?>"
                        <?= (int) ($filters['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>
                    >
                        <?= $escape($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="lookback_days">Financial Lookback</label>
            <input
                id="lookback_days"
                type="number"
                name="lookback_days"
                min="1"
                max="365"
                value="<?= $escape($filters['lookback_days'] ?? 30) ?>"
            >
        </div>

        <div class="form-group">
            <label for="min_margin">Low-Margin Threshold</label>
            <input
                id="min_margin"
                type="number"
                name="min_margin"
                min="0"
                max="100"
                step="0.1"
                value="<?= $escape($filters['min_margin'] ?? 20) ?>"
            >
        </div>

        <button type="submit" class="button-primary">
            Refresh
        </button>
    </form>
</section>

<br>

<section class="ops-scoreboard">
    <article class="ops-card <?= (int) $summary['attention_total'] > 0 ? 'critical' : 'success' ?>">
        <small>Operations Status</small>
        <strong><?= $escape($attentionLevel) ?></strong>
        <span><?= $escape($summary['attention_total']) ?> item(s) need action</span>
    </article>

    <article class="ops-card <?= (int) $summary['paid_unrouted_orders'] > 0 ? 'warning' : 'success' ?>">
        <small>Paid Orders Unrouted</small>
        <strong><?= $escape($summary['paid_unrouted_orders']) ?></strong>
        <span>Paid orders waiting for supplier routing</span>
    </article>

    <article class="ops-card <?= (int) $summary['open_exceptions'] > 0 ? 'critical' : 'success' ?>">
        <small>Open Exceptions</small>
        <strong><?= $escape($summary['open_exceptions']) ?></strong>
        <span>Fulfillment issues requiring review</span>
    </article>

    <article class="ops-card <?= (int) $summary['po_awaiting_submission'] > 0 ? 'warning' : 'success' ?>">
        <small>Awaiting Submission</small>
        <strong><?= $escape($summary['po_awaiting_submission']) ?></strong>
        <span>Supplier purchase orders need action</span>
    </article>

    <article class="ops-card <?= (int) $summary['late_purchase_orders'] > 0 ? 'critical' : 'success' ?>">
        <small>Late Purchase Orders</small>
        <strong><?= $escape($summary['late_purchase_orders']) ?></strong>
        <span>Expected ship date has passed</span>
    </article>

    <article class="ops-card <?= (int) $summary['missing_tracking'] > 0 ? 'warning' : 'success' ?>">
        <small>Missing Tracking</small>
        <strong><?= $escape($summary['missing_tracking']) ?></strong>
        <span>Marked shipped without tracking</span>
    </article>

    <article class="ops-card <?= (int) $summary['failed_submissions'] > 0 ? 'critical' : 'success' ?>">
        <small>Failed Submissions</small>
        <strong><?= $escape($summary['failed_submissions']) ?></strong>
        <span>Supplier submission queue failures</span>
    </article>

    <article class="ops-card <?= (int) $summary['failed_sync_runs'] > 0 ? 'warning' : 'success' ?>">
        <small>Feed Sync Problems</small>
        <strong><?= $escape($summary['failed_sync_runs']) ?></strong>
        <span>Failed or partial CSV sync runs</span>
    </article>
</section>

<br>

<section class="ops-scoreboard">
    <article class="ops-card">
        <small>Routed Revenue</small>
        <strong><?= $money($summary['routed_revenue_total']) ?></strong>
        <span><?= $escape($filters['lookback_days']) ?> day lookback</span>
    </article>

    <article class="ops-card">
        <small>Supplier Cost</small>
        <strong><?= $money($summary['supplier_cost_total']) ?></strong>
        <span>Total estimated wholesale cost</span>
    </article>

    <article class="ops-card">
        <small>Gross Profit</small>
        <strong><?= $money($summary['gross_profit_total']) ?></strong>
        <span>Revenue minus supplier cost</span>
    </article>

    <article class="ops-card <?= (float) $summary['margin_percent'] < (float) $filters['min_margin'] && (float) $summary['routed_revenue_total'] > 0 ? 'warning' : '' ?>">
        <small>Margin</small>
        <strong><?= $percent($summary['margin_percent']) ?></strong>
        <span><?= $escape($summary['purchase_order_count']) ?> routed purchase order(s)</span>
    </article>
</section>

<br>

<div class="ops-grid">
    <section class="panel">
        <div class="table-header">
            <h2>Paid Orders Needing Routing</h2>
            <a href="/admin/orders" class="button-muted">Orders</a>
        </div>

        <?php if (empty($dashboard['ordersNeedingRouting'])): ?>
            <div class="ops-empty">No paid orders are waiting for routing.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Store</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['ordersNeedingRouting'] as $order): ?>
                            <tr>
                                <td><?= $escape($order['order_number']) ?></td>
                                <td><?= $escape($order['store_name']) ?></td>
                                <td><?= $escape($order['customer_name'] ?: $order['customer_email']) ?></td>
                                <td><?= $money($order['grand_total']) ?></td>
                                <td><?= $escape($order['created_at']) ?></td>
                                <td><a class="table-link" href="/admin/orders/<?= $escape($order['id']) ?>/dropship">Route</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Open Fulfillment Exceptions</h2>
            <a href="/admin/purchase-orders" class="button-muted">Purchase Orders</a>
        </div>

        <?php if (empty($dashboard['openExceptions'])): ?>
            <div class="ops-empty">No open fulfillment exceptions.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Issue</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['openExceptions'] as $exception): ?>
                            <tr>
                                <td><?= $escape($exception['order_number']) ?></td>
                                <td><?= $escape($exception['product_name'] ?? '—') ?></td>
                                <td><?= $escape($exception['message']) ?></td>
                                <td><?= $escape($exception['created_at']) ?></td>
                                <td><a class="table-link" href="/admin/orders/<?= $escape($exception['order_id']) ?>/dropship">Resolve</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Purchase Orders Awaiting Submission</h2>
            <a href="/admin/supplier-submissions" class="button-muted">Queue</a>
        </div>

        <?php if (empty($dashboard['purchaseOrdersAwaitingSubmission'])): ?>
            <div class="ops-empty">No purchase orders are waiting for supplier submission.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['purchaseOrdersAwaitingSubmission'] as $po): ?>
                            <tr>
                                <td><?= $escape($po['purchase_order_number']) ?></td>
                                <td><?= $escape($po['supplier_name']) ?></td>
                                <td><span class="ops-pill ops-pill-warn"><?= $escape($label($po['submission_status'] ?? 'not_prepared')) ?></span></td>
                                <td><?= $money($po['total_cost']) ?></td>
                                <td><?= $escape($po['created_at']) ?></td>
                                <td><a class="table-link" href="/admin/purchase-orders/<?= $escape($po['id']) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Failed Supplier Submissions</h2>
            <a href="/admin/supplier-submissions?status=failed" class="button-muted">Failed Queue</a>
        </div>

        <?php if (empty($dashboard['failedSubmissions'])): ?>
            <div class="ops-empty">No supplier submissions are failed.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Submission</th>
                            <th>PO</th>
                            <th>Supplier</th>
                            <th>Error</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['failedSubmissions'] as $submission): ?>
                            <tr>
                                <td>#<?= $escape($submission['id']) ?></td>
                                <td><?= $escape($submission['purchase_order_number']) ?></td>
                                <td><?= $escape($submission['supplier_name']) ?></td>
                                <td><?= $escape($submission['error_message'] ?? '—') ?></td>
                                <td><a class="table-link" href="/admin/supplier-submissions/<?= $escape($submission['id']) ?>">Review</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Late Purchase Orders</h2>
            <a href="/admin/purchase-orders" class="button-muted">All POs</a>
        </div>

        <?php if (empty($dashboard['latePurchaseOrders'])): ?>
            <div class="ops-empty">No purchase orders are past expected ship date.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Expected Ship</th>
                            <th>Late</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['latePurchaseOrders'] as $po): ?>
                            <tr>
                                <td><?= $escape($po['purchase_order_number']) ?></td>
                                <td><?= $escape($po['supplier_name']) ?></td>
                                <td><span class="ops-pill ops-pill-danger"><?= $escape($label($po['status'])) ?></span></td>
                                <td><?= $escape($po['expected_ship_at']) ?></td>
                                <td><?= $escape($po['days_late']) ?> day(s)</td>
                                <td><a class="table-link" href="/admin/purchase-orders/<?= $escape($po['id']) ?>">Follow Up</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Missing Tracking</h2>
            <a href="/admin/purchase-orders" class="button-muted">All POs</a>
        </div>

        <?php if (empty($dashboard['missingTracking'])): ?>
            <div class="ops-empty">No shipped purchase orders are missing tracking.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['missingTracking'] as $po): ?>
                            <tr>
                                <td><?= $escape($po['purchase_order_number']) ?></td>
                                <td><?= $escape($po['supplier_name']) ?></td>
                                <td><span class="ops-pill ops-pill-warn"><?= $escape($label($po['status'])) ?></span></td>
                                <td><?= $escape($po['updated_at']) ?></td>
                                <td><a class="table-link" href="/admin/purchase-orders/<?= $escape($po['id']) ?>">Add Tracking</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<br>

<section class="panel">
    <div class="table-header">
        <h2>Supplier Performance</h2>
        <span class="ops-pill"><?= $escape($filters['lookback_days']) ?> day lookback</span>
    </div>

    <?php if (empty($dashboard['supplierPerformance'])): ?>
        <div class="ops-empty">No supplier purchase-order activity in this lookback window.</div>
    <?php else: ?>
        <div class="ops-table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Store</th>
                        <th>POs</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit</th>
                        <th>Margin</th>
                        <th>Delivered</th>
                        <th>Late</th>
                        <th>Failed Submissions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dashboard['supplierPerformance'] as $supplier): ?>
                        <?php $marginClass = (float) $supplier['margin_percent'] < (float) $filters['min_margin'] ? 'ops-pill-warn' : 'ops-pill-good'; ?>
                        <tr>
                            <td><?= $escape($supplier['name']) ?><br><small><?= $escape($supplier['code']) ?></small></td>
                            <td><?= $escape($supplier['store_name']) ?></td>
                            <td><?= $escape($supplier['purchase_order_count']) ?></td>
                            <td><?= $money($supplier['customer_revenue']) ?></td>
                            <td><?= $money($supplier['supplier_cost']) ?></td>
                            <td><?= $money($supplier['estimated_profit']) ?></td>
                            <td><span class="ops-pill <?= $escape($marginClass) ?>"><?= $percent($supplier['margin_percent']) ?></span></td>
                            <td><?= $escape($supplier['delivered_count'] ?? 0) ?></td>
                            <td><?= $escape($supplier['late_count'] ?? 0) ?></td>
                            <td><?= $escape($supplier['failed_submission_count'] ?? 0) ?></td>
                            <td><a class="table-link" href="/admin/suppliers/<?= $escape($supplier['id']) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<br>

<div class="ops-grid">
    <section class="panel">
        <div class="table-header">
            <h2>Low-Margin Purchase Orders</h2>
            <span class="ops-pill">Below <?= $percent($filters['min_margin']) ?></span>
        </div>

        <?php if (empty($dashboard['lowMarginPurchaseOrders'])): ?>
            <div class="ops-empty">No routed purchase orders are below the selected margin threshold.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO</th>
                            <th>Supplier</th>
                            <th>Revenue</th>
                            <th>Cost</th>
                            <th>Margin</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['lowMarginPurchaseOrders'] as $po): ?>
                            <tr>
                                <td><?= $escape($po['purchase_order_number']) ?></td>
                                <td><?= $escape($po['supplier_name']) ?></td>
                                <td><?= $money($po['customer_revenue']) ?></td>
                                <td><?= $money($po['total_cost']) ?></td>
                                <td><span class="ops-pill ops-pill-warn"><?= $percent($po['estimated_margin_percent']) ?></span></td>
                                <td><a class="table-link" href="/admin/purchase-orders/<?= $escape($po['id']) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>CSV Sync Problems</h2>
            <a href="/admin/suppliers" class="button-muted">Suppliers</a>
        </div>

        <?php if (empty($dashboard['failedSyncRuns'])): ?>
            <div class="ops-empty">No failed or partial supplier feed syncs.</div>
        <?php else: ?>
            <div class="ops-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Failed Rows</th>
                            <th>Started</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['failedSyncRuns'] as $run): ?>
                            <tr>
                                <td>#<?= $escape($run['id']) ?></td>
                                <td><?= $escape($run['supplier_name']) ?></td>
                                <td><span class="ops-pill ops-pill-warn"><?= $escape($label($run['status'])) ?></span></td>
                                <td><?= $escape($run['rows_failed']) ?></td>
                                <td><?= $escape($run['started_at']) ?></td>
                                <td><a class="table-link" href="/admin/suppliers/<?= $escape($run['supplier_id']) ?>/integration/sync-runs/<?= $escape($run['id']) ?>">Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<br>

<section class="panel">
    <h2>Recent Dropshipping Activity</h2>

    <?php if (empty($dashboard['recentActivity'])): ?>
        <div class="ops-empty">No recent dropshipping activity.</div>
    <?php else: ?>
        <?php foreach ($dashboard['recentActivity'] as $activity): ?>
            <article class="ops-activity">
                <strong><?= $escape($activity['message']) ?></strong>
                <small>
                    <?= $escape($activity['activity_at']) ?>
                    · <?= $escape($activity['store_name']) ?>
                    · <?= $escape($activity['supplier_name']) ?>
                </small>
                <br>
                <a href="<?= $escape($activity['action_url']) ?>" class="table-link">Open</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
