<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);
$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string) $value));
?>

<style>
.account-page{max-width:1120px;margin:0 auto;padding:30px 18px;display:grid;gap:22px}.account-header,.account-panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06);padding:24px}.account-header{display:flex;justify-content:space-between;gap:16px}.account-button,.account-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:11px;font-weight:900;text-decoration:none}.account-button{background:#111827;color:#fff}.account-secondary{background:#fff;color:#111827;border:1px solid #cbd5e1}.account-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.account-summary article{background:#f8fafc;border:1px solid #e2e8f0;border-radius:15px;padding:18px}.account-summary span{display:block;color:#64748b;font-size:12px;font-weight:900;text-transform:uppercase}.account-table{width:100%;border-collapse:collapse}.account-table th,.account-table td{padding:12px 10px;border-bottom:1px solid #e2e8f0;text-align:left}.account-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}.timeline{display:grid;gap:16px}.timeline-event{border-left:3px solid #2563eb;padding-left:14px}.timeline-event p{margin:.25rem 0;color:#475569}.account-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}.account-scroll{overflow-x:auto}@media(max-width:900px){.account-header,.account-summary,.account-grid{display:grid;grid-template-columns:1fr}}
</style>

<main class="account-page">
    <section class="account-header">
        <div>
            <p class="eyebrow dark-eyebrow">Order detail</p>
            <h1><?= $escape($order['order_number']) ?></h1>
            <p><?= $escape($store['name']) ?> customer account</p>
        </div>
        <div>
            <a href="/store/<?= $escape($store['slug']) ?>/account/dashboard" class="account-secondary">Back to account</a>
            <a href="/store/<?= $escape($store['slug']) ?>/returns/request" class="account-button">Request return</a>
        </div>
    </section>

    <section class="account-summary">
        <article><span>Order status</span><strong><?= $escape($label($order['status'] ?? '')) ?></strong></article>
        <article><span>Payment</span><strong><?= $escape($label($order['payment_status'] ?? '')) ?></strong></article>
        <article><span>Total</span><strong><?= $money($order['grand_total'] ?? 0) ?></strong></article>
        <article><span>Tracking</span><strong><?= $escape($order['tracking_number'] ?? '—') ?></strong></article>
    </section>

    <section class="account-grid">
        <div class="account-panel">
            <h2>Items</h2>
            <div class="account-scroll">
                <table class="account-table">
                    <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= $escape($item['product_name'] ?? '') ?></td>
                                <td><?= $escape($item['product_sku'] ?? '—') ?></td>
                                <td><?= $escape($item['quantity'] ?? 0) ?></td>
                                <td><?= $money($item['unit_price'] ?? 0) ?></td>
                                <td><?= $money($item['line_total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?><tr><td colspan="5">No order items found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="account-panel">
            <h2>Shipment tracking</h2>
            <?php if (! empty($purchase_orders)): ?>
                <table class="account-table">
                    <thead><tr><th>Shipment</th><th>Status</th><th>Tracking</th></tr></thead>
                    <tbody>
                        <?php foreach ($purchase_orders as $po): ?>
                            <tr>
                                <td><?= $escape($po['supplier_name'] ?? 'Shipment') ?></td>
                                <td><?= $escape($label($po['tracking_status'] ?? $po['status'] ?? '')) ?></td>
                                <td>
                                    <?php if (! empty($po['tracking_url'])): ?>
                                        <a href="<?= $escape($po['tracking_url']) ?>" target="_blank" rel="noopener"><?= $escape($po['tracking_number'] ?? 'Track') ?></a>
                                    <?php else: ?>
                                        <?= $escape($po['tracking_number'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Shipment details will appear here once the order is routed for fulfillment.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="account-panel">
        <h2>Public order timeline</h2>
        <div class="timeline">
            <?php foreach ($events as $event): ?>
                <div class="timeline-event">
                    <strong><?= $escape($event['title'] ?? $label($event['type'] ?? 'Update')) ?></strong>
                    <p><?= $escape($event['description'] ?? '') ?></p>
                    <small><?= $escape($event['created_at'] ?? '') ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (empty($events)): ?>
                <p>No public order updates have been posted yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
