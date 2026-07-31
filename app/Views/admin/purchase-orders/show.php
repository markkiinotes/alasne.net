<?php

declare(strict_types=1);
$escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$label = static fn (string $value): string => ucwords(str_replace('_',' ',$value));
?>
<style>
.po-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
.po-summary article { padding:15px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
.po-summary small { display:block; color:#64748b; font-weight:800; text-transform:uppercase; }
.po-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.po-event { border-left:3px solid #cbd5e1; padding:4px 0 12px 16px; margin-bottom:12px; }
@media(max-width:900px){ .po-summary,.po-form-grid{grid-template-columns:1fr;} }
</style>
<section class="page-header">
    <div><h1><?= $escape($purchaseOrder['purchase_order_number']) ?></h1><p><?= $escape($purchaseOrder['supplier_name']) ?> · Customer order <?= $escape($purchaseOrder['order_number']) ?></p></div>
    <div class="table-actions">
        <a href="/admin/orders/<?= $escape($purchaseOrder['order_id']) ?>/dropship" class="button-muted">Order Fulfillment</a>
        <a href="/admin/purchase-orders" class="button-muted">Back</a>
    </div>
</section>
<?php if($success): ?><div class="alert-success"><?= $escape($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert-danger"><?= $escape($error) ?></div><?php endif; ?>
<section class="po-summary">
    <article><small>Status</small><strong><?= $escape($label($purchaseOrder['status'])) ?></strong></article>
    <article><small>Total Cost</small><strong>$<?= number_format((float)$purchaseOrder['total_cost'],2) ?></strong></article>
    <article><small>Customer Revenue</small><strong>$<?= number_format((float)$purchaseOrder['customer_revenue'],2) ?></strong></article>
    <article><small>Estimated Profit</small><strong>$<?= number_format((float)$purchaseOrder['estimated_profit'],2) ?></strong></article>
    <article><small>Margin</small><strong><?= number_format((float)$purchaseOrder['estimated_margin_percent'],1) ?>%</strong></article>
</section>
<br>
<section class="panel">
    <div class="table-header">
        <div>
            <h2>Supplier Submission</h2>
            <p>
                Prepare, export, and audit the supplier-side
                order transmission independently from customer
                payment.
            </p>
        </div>

        <a
            href="/admin/supplier-submissions"
            class="button-muted"
        >
            Submission Queue
        </a>
    </div>

    <?php if (! empty($submission)): ?>
        <table class="detail-table">
            <tr>
                <th>Status</th>
                <td>
                    <?= $escape(
                        $label(
                            $submission['status']
                        )
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>Adapter</th>
                <td>
                    <?= $escape(
                        $label(
                            $submission[
                                'provider_code'
                            ]
                        )
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>Channel</th>
                <td>
                    <?= $escape(
                        $label(
                            $submission['channel']
                        )
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>Prepared</th>
                <td>
                    <?= $escape(
                        $submission['prepared_at']
                    ) ?>
                </td>
            </tr>
        </table>

        <div class="form-actions">
            <a
                href="/admin/supplier-submissions/<?= $escape(
                    $submission['id']
                ) ?>"
                class="button-primary"
            >
                Open Submission
            </a>

            <a
                href="/admin/supplier-submissions/<?= $escape(
                    $submission['id']
                ) ?>/export"
                class="button-muted"
            >
                Download Supplier CSV
            </a>
        </div>
    <?php else: ?>
        <p>
            This purchase order does not have a prepared
            supplier submission.
        </p>

        <form
            method="POST"
            action="/admin/purchase-orders/<?= $escape(
                $purchaseOrder['id']
            ) ?>/submission/prepare"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape(
                    $csrf_token
                ) ?>"
            >

            <button
                type="submit"
                class="button-primary"
            >
                Prepare Supplier Submission
            </button>
        </form>
    <?php endif; ?>
</section>
<br>
<section class="panel">
    <h2>Supplier Items</h2>
    <table class="data-table">
        <thead><tr><th>Product</th><th>Store SKU</th><th>Supplier SKU</th><th>Qty</th><th>Unit Cost</th><th>Line Cost</th><th>Revenue</th><th>Profit</th></tr></thead>
        <tbody>
            <?php foreach($items as $item): ?>
                <tr>
                    <td><?= $escape($item['product_name']) ?></td><td><?= $escape($item['product_sku'] ?? '—') ?></td><td><?= $escape($item['supplier_sku']) ?></td><td><?= $escape($item['quantity']) ?></td>
                    <td>$<?= number_format((float)$item['unit_cost'],2) ?></td><td>$<?= number_format((float)$item['line_cost'],2) ?></td><td>$<?= number_format((float)$item['customer_line_revenue'],2) ?></td><td>$<?= number_format((float)$item['estimated_profit'],2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<br>
<section class="panel form-panel">
    <h2>Update Supplier Fulfillment</h2>
    <form method="POST" action="/admin/purchase-orders/<?= $escape($purchaseOrder['id']) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
        <div class="po-form-grid">
            <div class="form-group"><label for="status">Status</label><select id="status" name="status" required><?php foreach(['pending','submitted','accepted','partially_shipped','shipped','delivered','cancelled','failed'] as $status): ?><option value="<?= $escape($status) ?>" <?= $purchaseOrder['status']===$status?'selected':'' ?>><?= $escape($label($status)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="external_order_id">External Order ID</label><input id="external_order_id" type="text" name="external_order_id" value="<?= $escape($purchaseOrder['external_order_id'] ?? '') ?>"></div>
            <div class="form-group"><label for="supplier_reference">Supplier Reference</label><input id="supplier_reference" type="text" name="supplier_reference" value="<?= $escape($purchaseOrder['supplier_reference'] ?? '') ?>"></div>
            <div class="form-group"><label for="shipping_carrier">Carrier</label><input id="shipping_carrier" type="text" name="shipping_carrier" value="<?= $escape($purchaseOrder['shipping_carrier'] ?? '') ?>"></div>
            <div class="form-group"><label for="tracking_number">Tracking Number</label><input id="tracking_number" type="text" name="tracking_number" value="<?= $escape($purchaseOrder['tracking_number'] ?? '') ?>"></div>
            <div class="form-group"><label for="tracking_url">Tracking URL</label><input id="tracking_url" type="url" name="tracking_url" value="<?= $escape($purchaseOrder['tracking_url'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label for="event_note">Update Note</label><textarea id="event_note" name="event_note" rows="3"></textarea></div>
        <div class="form-group"><label for="notes">Internal Notes</label><textarea id="notes" name="notes" rows="4"><?= $escape($purchaseOrder['notes'] ?? '') ?></textarea></div>
        <button type="submit" class="button-primary">Save Purchase Order</button>
    </form>
</section>
<br>
<section class="panel"><h2>Purchase Order Timeline</h2><?php foreach($events as $event): ?><article class="po-event"><strong><?= $escape($event['title']) ?></strong><?php if(!empty($event['description'])): ?><p><?= $escape($event['description']) ?></p><?php endif; ?><small><?= $escape($event['created_at']) ?><?php if(!empty($event['old_value'])||!empty($event['new_value'])): ?> · <?= $escape($event['old_value'] ?? '—') ?> → <?= $escape($event['new_value'] ?? '—') ?><?php endif; ?></small></article><?php endforeach; ?><?php if(empty($events)): ?><p>No purchase-order activity recorded.</p><?php endif; ?></section>
