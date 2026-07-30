<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string)$value));
$status = (string)$return['status'];
$currency = strtoupper((string)($return['currency'] ?? 'USD'));
?>

<style>
.return-show-page { display:grid; gap:20px; }
.return-summary-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.return-panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:22px; box-shadow:0 10px 26px rgba(15,23,42,.06); }
.return-status { display:inline-flex; padding:6px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:800; }
.return-status.completed { background:#dcfce7; color:#166534; }
.return-status.cancelled { background:#fee2e2; color:#991b1b; }
.return-table-wrap { overflow-x:auto; }
.return-table { width:100%; border-collapse:collapse; }
.return-table th,.return-table td { padding:13px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
.return-table th { background:#f8fafc; color:#475569; font-size:12px; text-transform:uppercase; }
.return-action-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
.return-alert { padding:14px 16px; border-radius:12px; font-weight:700; }
.return-alert.success { background:#dcfce7; color:#166534; }
.return-alert.error { background:#fee2e2; color:#991b1b; }
.return-timeline { display:grid; gap:16px; }
.return-event { border-left:3px solid #2563eb; padding-left:14px; }
.return-event p { margin:5px 0; color:#475569; }
@media(max-width:900px){ .return-summary-grid,.return-action-grid{grid-template-columns:1fr;} }
</style>

<div class="return-show-page">
    <section class="page-header">
        <h1><?= $escape($return['return_number']) ?></h1>
        <p>Order <a class="table-link" href="/admin/orders/<?= $escape($return['order_id']) ?>"><?= $escape($return['order_number']) ?></a> · <?= $escape($return['customer_name'] ?? 'Customer') ?></p>
        <div class="table-actions">
            <a href="/admin/returns" class="button-muted">All Returns</a>
            <a href="/admin/orders/<?= $escape($return['order_id']) ?>" class="button-muted">View Order</a>
        </div>
    </section>

    <?php if (! empty($success)): ?><div class="return-alert success"><?= $escape($success) ?></div><?php endif; ?>
    <?php if (! empty($error)): ?><div class="return-alert error"><?= $escape($error) ?></div><?php endif; ?>

    <section class="return-summary-grid">
        <article class="return-panel"><small>Status</small><h2><span class="return-status <?= $escape($status) ?>"><?= $escape($label($status)) ?></span></h2><p>Refund: <?= $escape($label($return['refund_status'])) ?><br>Source: <?= $escape(($return['request_source'] ?? 'admin') === 'customer' ? 'Customer Self-Service' : 'Mission Control') ?></p></article>
        <article class="return-panel"><small>Requested Merchandise</small><h2>$<?= number_format((float)$return['requested_refund_amount'],2) ?> <?= $escape($currency) ?></h2><p>Reason: <?= $escape($label($return['reason_code'])) ?></p></article>
        <article class="return-panel"><small>Approved Merchandise</small><h2>$<?= number_format((float)$return['approved_refund_amount'],2) ?> <?= $escape($currency) ?></h2><p>Refunded: $<?= number_format((float)($return['refunded_amount'] ?? 0), 2) ?> <?= $escape($currency) ?><br>Transaction: <?= !empty($return['refund_transaction_id']) ? '#'.$escape($return['refund_transaction_id']) : '—' ?></p></article>
    </section>

    <section class="return-panel">
        <h2>Returned Items</h2>
        <div class="return-table-wrap">
            <table class="return-table">
                <thead><tr><th>Item</th><th>Requested</th><th>Received</th><th>Restocked</th><th>Discarded</th><th>Condition</th><th>Approved Refund</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= $escape($item['product_name']) ?></strong><br><small><?= $escape($item['product_sku'] ?? '') ?></small></td>
                            <td><?= $escape($item['quantity_requested']) ?></td>
                            <td><?= $escape($item['quantity_received']) ?></td>
                            <td><?= $escape($item['quantity_restocked']) ?></td>
                            <td><?= $escape($item['quantity_discarded']) ?></td>
                            <td><?= $escape($label($item['condition_code'] ?? '—')) ?></td>
                            <td>$<?= number_format((float)$item['approved_refund_amount'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($status === 'requested'): ?>
        <section class="return-panel">
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/approve">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <h2>Approve Return</h2>
                <div class="form-group"><label for="approve_notes">Approval Notes</label><textarea id="approve_notes" name="notes" rows="4"></textarea></div>
                <button type="submit" class="button-primary">Approve Return</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($status === 'approved'): ?>
        <section class="return-panel">
            <h2>Receive and Inspect Merchandise</h2>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/receive">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <div class="return-table-wrap">
                    <table class="return-table">
                        <thead><tr><th>Item</th><th>Approved Qty</th><th>Received</th><th>Restock</th><th>Discard</th><th>Condition</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= $escape($item['product_name']) ?></strong></td>
                                    <td><?= $escape($item['quantity_requested']) ?></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][received]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="<?= $escape($item['quantity_requested']) ?>" required style="width:80px;"></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][restocked]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="0" required style="width:80px;"></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][discarded]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="0" required style="width:80px;"></td>
                                    <td><select name="items[<?= $escape($item['id']) ?>][condition_code]"><option value="new">New</option><option value="opened">Opened</option><option value="used">Used</option><option value="damaged">Damaged</option><option value="defective">Defective</option></select></td>
                                    <td><input type="text" name="items[<?= $escape($item['id']) ?>][notes]" maxlength="500"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-group"><label for="receive_notes">Receiving Notes</label><textarea id="receive_notes" name="notes" rows="4"></textarea></div>
                <button type="submit" class="button-primary">Record Receipt</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($status === 'received'): ?>
        <section class="return-panel">
            <h2>Complete Return</h2>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/complete">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:16px;"><input type="checkbox" name="process_refund" value="1" checked><span><strong>Process payment refund</strong><br><small>Uncheck to complete the physical return without changing payment totals.</small></span></label>
                <div class="return-action-grid">
                    <div class="form-group"><label for="refund_amount">Refund Amount</label><input id="refund_amount" type="number" name="refund_amount" value="<?= $escape(number_format((float)$return['approved_refund_amount'],2,'.','')) ?>" min="0.01" max="<?= $escape(number_format((float)$return['approved_refund_amount'],2,'.','')) ?>" step="0.01"></div>
                    <div class="form-group"><label for="refund_scenario">Test Refund Result</label><select id="refund_scenario" name="refund_scenario"><option value="approved">Approved</option><option value="declined">Declined</option><option value="error">Provider Error</option></select></div>
                </div>
                <p style="color:#64748b;">Completing a return never changes inventory again. Restocking was handled during receipt.</p>
                <button type="submit" class="button-primary" onclick="return confirm('Complete this return?');">Complete Return</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if (in_array($status, ['requested','approved'], true)): ?>
        <section class="return-panel">
            <h2>Cancellation</h2>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/cancel">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <div class="form-group"><label for="general_cancel_notes">Notes</label><textarea id="general_cancel_notes" name="notes" rows="3" required></textarea></div>
                <button type="submit" class="button-muted" onclick="return confirm('Cancel this return?');">Cancel Return</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="return-panel">
        <h2>Return Activity</h2>
        <div class="return-timeline">
            <?php foreach ($events as $event): ?>
                <article class="return-event">
                    <strong><?= $escape($event['title']) ?></strong>
                    <?php if (!empty($event['description'])): ?><p><?= $escape($event['description']) ?></p><?php endif; ?>
                    <small><?= $escape($event['created_at']) ?><?= !empty($event['old_value']) || !empty($event['new_value']) ? ' · '.$escape($event['old_value'] ?? '').' → '.$escape($event['new_value'] ?? '') : '' ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
