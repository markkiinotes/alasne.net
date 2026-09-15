<?php

declare(strict_types=1);
$escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$label = static fn (string $value): string => ucwords(str_replace('_',' ',$value));
?>
<style>
.ds-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
.ds-summary article { padding:15px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
.ds-summary small { display:block; color:#64748b; font-weight:800; text-transform:uppercase; }
.exception-card { padding:15px; margin-bottom:12px; border:1px solid #fecaca; border-left:5px solid #dc2626; border-radius:10px; background:#fff7f7; }
@media(max-width:900px){ .ds-summary{grid-template-columns:1fr;} }
</style>
<section class="page-header">
    <div><h1>Dropshipping Fulfillment</h1><p><?= $escape($order['order_number']) ?> · <?= $escape($order['store_name']) ?> · <?= $escape($order['customer_name'] ?? '') ?></p></div>
    <div class="table-actions"><a href="/admin/orders/<?= $escape($order['id']) ?>" class="button-muted">Back to Order</a></div>
</section>
<?php if($success): ?><div class="alert-success"><?= $escape($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert-danger"><?= $escape($error) ?></div><?php endif; ?>
<section class="ds-summary">
    <article><small>Routing Status</small><strong><?= $escape($label($order['dropship_status'] ?? 'unrouted')) ?></strong></article>
    <article><small>Supplier Cost</small><strong>$<?= number_format((float)($order['supplier_cost_total'] ?? 0),2) ?></strong></article>
    <article><small>Gross Profit</small><strong>$<?= number_format((float)($order['estimated_gross_profit'] ?? 0),2) ?></strong></article>
    <article><small>Margin</small><strong><?= number_format((float)($order['estimated_margin_percent'] ?? 0),1) ?>%</strong></article>
    <article><small>Purchase Orders</small><strong><?= count($purchaseOrders) ?></strong></article>
</section>
<br>
<section class="panel">
    <div class="table-header"><div><h2>Supplier Routing</h2><p>Retry routing after adding or correcting supplier mappings.</p></div>
        <form method="POST" action="/admin/orders/<?= $escape($order['id']) ?>/dropship/route"><input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>"><button type="submit" class="button-primary">Route / Retry Order</button></form>
    </div>
</section>
<?php if(!empty($exceptions)): ?><br><section class="panel"><h2>Fulfillment Exceptions</h2><?php foreach($exceptions as $exception): ?><article class="exception-card"><strong><?= $escape($label($exception['exception_code'])) ?></strong><p><?= $escape($exception['message']) ?></p><?php if($exception['status']==='open'): ?><form method="POST" action="/admin/orders/<?= $escape($order['id']) ?>/dropship/exceptions/<?= $escape($exception['id']) ?>/resolve"><input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>"><div class="form-group"><input type="text" name="resolution_note" placeholder="Resolution note"></div><button type="submit" class="button-muted">Mark Resolved</button></form><?php else: ?><small>Resolved <?= $escape($exception['resolved_at'] ?? '') ?> · <?= $escape($exception['resolution_note'] ?? '') ?></small><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>
<br>
<section class="panel"><h2>Purchase Orders</h2><table class="data-table"><thead><tr><th>PO</th><th>Supplier</th><th>Status</th><th>Cost</th><th>Revenue</th><th>Profit</th><th>Margin</th><th>Tracking</th><th></th></tr></thead><tbody><?php foreach($purchaseOrders as $po): ?><tr><td><?= $escape($po['purchase_order_number']) ?></td><td><?= $escape($po['supplier_name']) ?></td><td><?= $escape($label($po['status'])) ?></td><td>$<?= number_format((float)$po['total_cost'],2) ?></td><td>$<?= number_format((float)$po['customer_revenue'],2) ?></td><td>$<?= number_format((float)$po['estimated_profit'],2) ?></td><td><?= number_format((float)$po['estimated_margin_percent'],1) ?>%</td><td><?= $escape($po['tracking_number'] ?? '—') ?></td><td><a href="/admin/purchase-orders/<?= $escape($po['id']) ?>" class="table-link">Open</a></td></tr><?php endforeach; ?><?php if(empty($purchaseOrders)): ?><tr><td colspan="9">No supplier purchase orders have been generated.</td></tr><?php endif; ?></tbody></table></section>
