<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$label = static fn (mixed $value): string =>
    ucwords(
        str_replace('_', ' ', (string) $value)
    );
?>

<style>
.returns-page { display:grid; gap:22px; }
.returns-header { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; }
.returns-header h1 { margin:0 0 6px; }
.returns-header p { margin:0; color:#64748b; }
.returns-panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:22px; box-shadow:0 10px 26px rgba(15,23,42,.06); }
.returns-filters { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:12px; align-items:end; }
.returns-table-wrap { overflow-x:auto; }
.returns-table { width:100%; border-collapse:collapse; }
.returns-table th,.returns-table td { padding:14px 12px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
.returns-table th { background:#f8fafc; color:#475569; font-size:12px; text-transform:uppercase; }
.return-badge { display:inline-flex; padding:5px 9px; border-radius:999px; background:#e2e8f0; font-size:12px; font-weight:800; }
.return-badge.completed { background:#dcfce7; color:#166534; }
.return-badge.cancelled { background:#fee2e2; color:#991b1b; }
.return-badge.requested,.return-badge.approved,.return-badge.received { background:#fef3c7; color:#92400e; }
.returns-alert { padding:14px 16px; border-radius:12px; font-weight:700; }
.returns-alert.success { background:#dcfce7; color:#166534; }
.returns-alert.error { background:#fee2e2; color:#991b1b; }
@media(max-width:900px){ .returns-filters{grid-template-columns:1fr;} }
</style>

<div class="returns-page">
    <header class="returns-header">
        <div>
            <h1>Returns</h1>
            <p>Track requests, receiving, restocking, disposal, and refunds.</p>
        </div>
    </header>

    <?php if (! empty($success)): ?>
        <div class="returns-alert success"><?= $escape($success) ?></div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div class="returns-alert error"><?= $escape($error) ?></div>
    <?php endif; ?>

    <section class="returns-panel">
        <form method="GET" action="/admin/returns" class="returns-filters">
            <div class="form-group">
                <label for="q">Search</label>
                <input id="q" type="text" name="q" value="<?= $escape($filters['q'] ?? '') ?>" placeholder="Return, order, customer, or email">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $escape($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= $escape($label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="store_id">Store</label>
                <select id="store_id" name="store_id">
                    <option value="0">All stores</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= $escape($store['id']) ?>" <?= (int)($filters['store_id'] ?? 0) === (int)$store['id'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="request_source">Source</label>
                <select id="request_source" name="request_source">
                    <option value="">All sources</option>
                    <?php foreach ($sources as $source): ?>
                        <option
                            value="<?= $escape($source) ?>"
                            <?= ($filters['request_source'] ?? '') === $source
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $escape(
                                $source === 'customer'
                                    ? 'Customer Self-Service'
                                    : 'Mission Control'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button-primary">Filter</button>
        </form>
    </section>

    <section class="returns-panel" style="padding:0;overflow:hidden;">
        <div class="returns-table-wrap">
            <table class="returns-table">
                <thead>
                    <tr>
                        <th>Return</th>
                        <th>RMA</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Store</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Requested</th>
                        <th>Approved</th>
                        <th>Refund</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><strong><?= $escape($return['return_number']) ?></strong></td>
                            <td><?= $escape($return['rma_number'] ?? '—') ?></td>
                            <td><a class="table-link" href="/admin/orders/<?= $escape($return['order_id']) ?>"><?= $escape($return['order_number']) ?></a></td>
                            <td><?= $escape($return['customer_name'] ?? '—') ?><br><small><?= $escape($return['customer_email'] ?? '') ?></small></td>
                            <td><?= $escape($return['store_name']) ?></td>
                            <td><span class="return-badge <?= $escape($return['status']) ?>"><?= $escape($label($return['status'])) ?></span></td>
                            <td><?= $escape(
                                ($return['request_source'] ?? 'admin') === 'customer'
                                    ? 'Customer Self-Service'
                                    : 'Mission Control'
                            ) ?></td>
                            <td>$<?= number_format((float)$return['requested_refund_amount'], 2) ?></td>
                            <td>$<?= number_format((float)$return['approved_refund_amount'], 2) ?></td>
                            <td><?= $escape($label($return['refund_status'])) ?></td>
                            <td><?= $escape($return['created_at']) ?></td>
                            <td><a class="button-muted" href="/admin/returns/<?= $escape($return['id']) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="12">No returns matched the selected filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
