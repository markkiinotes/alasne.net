<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);
$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string) $value));
$customerName = trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? ''));
?>

<style>
.account-page{max-width:1180px;margin:0 auto;padding:30px 18px;display:grid;gap:22px}.account-header,.account-panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06);padding:24px}.account-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.account-actions{display:flex;gap:10px;flex-wrap:wrap}.account-button,.account-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:11px;font-weight:900;text-decoration:none}.account-button{background:#111827;color:#fff;border:0}.account-secondary{background:#fff;color:#111827;border:1px solid #cbd5e1}.account-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.account-summary article{background:#f8fafc;border:1px solid #e2e8f0;border-radius:15px;padding:18px}.account-summary span{display:block;color:#64748b;font-size:12px;font-weight:900;text-transform:uppercase}.account-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:22px}.account-table{width:100%;border-collapse:collapse}.account-table th,.account-table td{padding:12px 10px;border-bottom:1px solid #e2e8f0;text-align:left}.account-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}.account-form{display:grid;grid-template-columns:1fr 1fr;gap:14px}.account-form label{display:block;margin-bottom:6px;font-weight:800}.account-form input{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:11px;font:inherit}.account-alert{padding:14px 16px;border-radius:12px;font-weight:800}.account-alert.success{background:#dcfce7;color:#166534}.account-alert.error{background:#fee2e2;color:#991b1b}.account-badge{display:inline-flex;padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px;font-weight:900}.account-mobile-scroll{overflow-x:auto}@media(max-width:900px){.account-header,.account-grid,.account-summary,.account-form{grid-template-columns:1fr;display:grid}.account-header{display:grid}}
</style>

<main class="account-page">
    <section class="account-header">
        <div>
            <p class="eyebrow dark-eyebrow">My account</p>
            <h1>Hello<?= $customerName !== '' ? ', ' . $escape($customerName) : '' ?></h1>
            <p>View your orders, tracking, returns, store credit, and profile for <?= $escape($store['name']) ?>.</p>
        </div>

        <div class="account-actions">
            <a href="/store/<?= $escape($store['slug']) ?>" class="account-secondary">Shop</a>
            <a href="/store/<?= $escape($store['slug']) ?>/returns/request" class="account-secondary">Request return</a>
            <a href="/store/<?= $escape($store['slug']) ?>/account/store-credit" class="account-secondary">Store credit</a>
            <form method="POST" action="/store/<?= $escape($store['slug']) ?>/account/logout">
                <button type="submit" class="account-button">Sign out</button>
            </form>
        </div>
    </section>

    <?php if (! empty($success)): ?><div class="account-alert success"><?= $escape($success) ?></div><?php endif; ?>
    <?php if (! empty($error)): ?><div class="account-alert error"><?= $escape($error) ?></div><?php endif; ?>

    <section class="account-summary">
        <article><span>Orders</span><strong><?= $escape($summary['order_count']) ?></strong></article>
        <article><span>Lifetime spend</span><strong><?= $money($summary['lifetime_spend']) ?></strong></article>
        <article><span>Returns</span><strong><?= $escape($summary['return_count']) ?></strong></article>
        <article><span>Store credit</span><strong><?= $money($summary['store_credit_balance']) ?></strong></article>
    </section>

    <section class="account-grid">
        <div class="account-panel">
            <h2>Recent orders</h2>
            <div class="account-mobile-scroll">
                <table class="account-table">
                    <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Tracking</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $escape($order['order_number']) ?></td>
                                <td><span class="account-badge"><?= $escape($label($order['status'] ?? '')) ?></span></td>
                                <td><?= $escape($label($order['payment_status'] ?? '')) ?></td>
                                <td><?= $money($order['grand_total'] ?? 0) ?></td>
                                <td><?= $escape($order['tracking_number'] ?? '—') ?></td>
                                <td><a href="/store/<?= $escape($store['slug']) ?>/account/orders/<?= $escape($order['id']) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?><tr><td colspan="6">No orders found yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="account-panel">
            <h2>Returns</h2>
            <div class="account-mobile-scroll">
                <table class="account-table">
                    <thead><tr><th>Return</th><th>Order</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($returns as $return): ?>
                            <tr>
                                <td><?= $escape($return['return_number'] ?? ('#' . $return['id'])) ?></td>
                                <td><?= $escape($return['order_number'] ?? '—') ?></td>
                                <td><?= $escape($label($return['status'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($returns)): ?><tr><td colspan="3">No returns found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="account-panel">
        <h2>Profile and shipping address</h2>
        <form method="POST" action="/store/<?= $escape($store['slug']) ?>/account/profile" class="account-form">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
            <?php foreach ([
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'phone' => 'Phone',
                'address_line_1' => 'Address line 1',
                'address_line_2' => 'Address line 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal code',
                'country' => 'Country',
            ] as $field => $text): ?>
                <div>
                    <label for="<?= $escape($field) ?>"><?= $escape($text) ?></label>
                    <input id="<?= $escape($field) ?>" name="<?= $escape($field) ?>" value="<?= $escape($customer[$field] ?? '') ?>" <?= in_array($field, ['first_name','last_name','address_line_1','city','state','postal_code','country'], true) ? 'required' : '' ?>>
                </div>
            <?php endforeach; ?>
            <div><button type="submit" class="account-button">Save profile</button></div>
        </form>
    </section>
</main>
