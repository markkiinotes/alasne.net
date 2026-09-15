<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));
?>

<style>
.po-filters { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:12px; align-items:end; }
.po-kpi { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.po-kpi article { padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
.po-kpi small { display:block; color:#64748b; font-weight:800; text-transform:uppercase; }
@media(max-width:950px){ .po-filters,.po-kpi{grid-template-columns:1fr;} }
</style>

<section class="page-header">
    <div>
        <h1>Purchase Orders</h1>
        <p>Supplier-side fulfillment, cost, margin, and tracking.</p>
    </div>
    <a href="/admin/suppliers" class="button-muted">Suppliers</a>
</section>

<?php if ($success): ?><div class="alert-success"><?= $escape($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert-danger"><?= $escape($error) ?></div><?php endif; ?>

<section class="panel">
    <form method="GET" class="po-filters">
        <div class="form-group">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="<?= $escape($filters['q'] ?? '') ?>" placeholder="PO, customer order, supplier, tracking">
        </div>
        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?= $escape($store['id']) ?>" <?= (int)($filters['store_id'] ?? 0)===(int)$store['id']?'selected':'' ?>><?= $escape($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= $escape($supplier['id']) ?>" <?= (int)($filters['supplier_id'] ?? 0)===(int)$supplier['id']?'selected':'' ?>><?= $escape($supplier['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $escape($status) ?>" <?= ($filters['status'] ?? '')===$status?'selected':'' ?>><?= $escape($label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="button-primary">Filter</button>
    </form>
</section>

<br>

<section class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Purchase Order</th><th>Supplier</th><th>Customer Order</th><th>Status</th><th>Items</th><th>Cost</th><th>Revenue</th><th>Profit</th><th>Margin</th><th>Tracking</th><th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($purchaseOrders as $po): ?>
                <tr>
                    <td><strong><?= $escape($po['purchase_order_number']) ?></strong><br><small><?= $escape($po['created_at']) ?></small></td>
                    <td><a href="/admin/suppliers/<?= $escape($po['supplier_id']) ?>" class="table-link"><?= $escape($po['supplier_name']) ?></a></td>
                    <td><a href="/admin/orders/<?= $escape($po['order_id']) ?>" class="table-link"><?= $escape($po['order_number']) ?></a></td>
                    <td><?= $escape($label($po['status'])) ?></td>
                    <td><?= $escape($po['item_count']) ?> lines / <?= $escape($po['unit_count']) ?> units</td>
                    <td>$<?= number_format((float)$po['total_cost'],2) ?></td>
                    <td>$<?= number_format((float)$po['customer_revenue'],2) ?></td>
                    <td>$<?= number_format((float)$po['estimated_profit'],2) ?></td>
                    <td><?= number_format((float)$po['estimated_margin_percent'],1) ?>%</td>
                    <td><?= $escape($po['tracking_number'] ?? '—') ?></td>
                    <td><a href="/admin/purchase-orders/<?= $escape($po['id']) ?>" class="table-link">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($purchaseOrders)): ?><tr><td colspan="11">No purchase orders match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
