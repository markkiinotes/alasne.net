<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$old = $old ?? [];
$oldQuantities = is_array($old['quantities'] ?? null)
    ? $old['quantities']
    : [];
?>

<style>
.return-create-page { display:grid; gap:22px; }
.return-create-grid { display:grid; grid-template-columns:1fr 360px; gap:22px; align-items:start; }
.return-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:22px; box-shadow:0 10px 26px rgba(15,23,42,.06); }
.return-items { width:100%; border-collapse:collapse; }
.return-items th,.return-items td { padding:13px 10px; border-bottom:1px solid #e2e8f0; text-align:left; }
.return-items input[type=number] { width:90px; }
.return-note { padding:14px; border-radius:12px; background:#eff6ff; color:#1e3a8a; line-height:1.5; }
.return-alert { padding:14px 16px; border-radius:12px; background:#fee2e2; color:#991b1b; font-weight:700; }
@media(max-width:900px){ .return-create-grid{grid-template-columns:1fr;} }
</style>

<div class="return-create-page">
    <section class="page-header">
        <h1>Create Return</h1>
        <p>Order <?= $escape($order['order_number']) ?> · <?= $escape($order['customer_name'] ?? 'Customer') ?></p>
        <div class="table-actions">
            <a href="/admin/orders/<?= $escape($order['id']) ?>" class="button-muted">Back to Order</a>
            <a href="/admin/returns" class="button-muted">All Returns</a>
        </div>
    </section>

    <?php if (! empty($error)): ?>
        <div class="return-alert"><?= $escape($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/orders/<?= $escape($order['id']) ?>/returns" class="return-create-grid">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

        <section class="return-card">
            <h2>Merchandise</h2>
            <p class="return-note">Requested refund values cover merchandise only. Shipping and tax are not automatically added.</p>
            <div style="overflow-x:auto;">
                <table class="return-items">
                    <thead>
                        <tr><th>Item</th><th>Purchased</th><th>Already Requested</th><th>Available</th><th>Unit Price</th><th>Return Qty</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $available = (int)$item['quantity_available_to_return']; ?>
                            <tr>
                                <td><strong><?= $escape($item['product_name']) ?></strong><br><small><?= $escape($item['product_sku'] ?? '') ?></small></td>
                                <td><?= $escape($item['quantity']) ?></td>
                                <td><?= $escape($item['quantity_already_requested']) ?></td>
                                <td><?= $escape($available) ?></td>
                                <td>$<?= number_format((float)$item['unit_price'], 2) ?></td>
                                <td>
                                    <input type="number" name="quantities[<?= $escape($item['id']) ?>]" value="<?= $escape($oldQuantities[$item['id']] ?? 0) ?>" min="0" max="<?= $escape($available) ?>" step="1" <?= $available <= 0 ? 'disabled' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="return-card">
            <h2>Return Details</h2>
            <div class="form-group">
                <label for="reason_code">Reason *</label>
                <select id="reason_code" name="reason_code" required>
                    <?php foreach ([
                        'damaged' => 'Damaged',
                        'defective' => 'Defective',
                        'wrong_item' => 'Wrong Item',
                        'not_as_described' => 'Not as Described',
                        'changed_mind' => 'Changed Mind',
                        'other' => 'Other',
                    ] as $value => $text): ?>
                        <option value="<?= $escape($value) ?>" <?= ($old['reason_code'] ?? 'other') === $value ? 'selected' : '' ?>><?= $escape($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="reason_details">Reason Details</label>
                <textarea id="reason_details" name="reason_details" rows="4"><?= $escape($old['reason_details'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="customer_notes">Customer Notes</label>
                <textarea id="customer_notes" name="customer_notes" rows="4"><?= $escape($old['customer_notes'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="internal_notes">Internal Notes</label>
                <textarea id="internal_notes" name="internal_notes" rows="4"><?= $escape($old['internal_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="button-primary" style="width:100%;">Create Return Request</button>
        </aside>
    </form>
</div>
