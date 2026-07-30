<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$shippingMethods = $shippingMethods ?? [];
$activeCount = 0;

foreach ($shippingMethods as $method) {
    if ((int) ($method['is_active'] ?? 0) === 1) {
        $activeCount++;
    }
}
?>

<style>
.shipping-admin-page {
    display: grid;
    gap: 24px;
}

.shipping-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.shipping-admin-header h1 {
    margin: 0 0 8px;
}

.shipping-admin-header p {
    margin: 0;
    color: #64748b;
}

.shipping-admin-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.shipping-admin-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border: 0;
    border-radius: 10px;
    background: #111827;
    color: #ffffff;
    text-decoration: none;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.shipping-admin-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.shipping-admin-button.warning {
    background: #fef3c7;
    color: #92400e;
}

.shipping-admin-button.danger {
    background: #fee2e2;
    color: #991b1b;
}

.shipping-admin-alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-weight: 700;
}

.shipping-admin-alert.success {
    background: #dcfce7;
    color: #166534;
}

.shipping-admin-alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.shipping-admin-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.shipping-admin-stat,
.shipping-admin-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
}

.shipping-admin-stat {
    padding: 20px;
}

.shipping-admin-stat span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.shipping-admin-stat strong {
    font-size: 28px;
}

.shipping-admin-panel {
    overflow: hidden;
}

.shipping-admin-table-wrap {
    overflow-x: auto;
}

.shipping-admin-table {
    width: 100%;
    border-collapse: collapse;
}

.shipping-admin-table th,
.shipping-admin-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

.shipping-admin-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.shipping-admin-table tr:last-child td {
    border-bottom: 0;
}

.shipping-admin-method-name {
    display: grid;
    gap: 4px;
}

.shipping-admin-method-name small,
.shipping-admin-muted {
    color: #64748b;
}

.shipping-admin-code {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
}

.shipping-admin-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.shipping-admin-status.active {
    background: #dcfce7;
    color: #166534;
}

.shipping-admin-status.inactive {
    background: #e2e8f0;
    color: #475569;
}

.shipping-admin-row-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.shipping-admin-row-actions form {
    margin: 0;
}

.shipping-admin-empty {
    padding: 42px 24px;
    text-align: center;
}

.shipping-admin-empty h2 {
    margin: 0 0 8px;
}

.shipping-admin-empty p {
    margin: 0 0 20px;
    color: #64748b;
}

@media (max-width: 900px) {
    .shipping-admin-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="shipping-admin-page">
    <header class="shipping-admin-header">
        <div>
            <h1>Shipping Methods</h1>
            <p>
                Manage checkout delivery options for
                <strong><?= $escape($store['name']) ?></strong>.
            </p>
        </div>

        <div class="shipping-admin-actions">
            <a
                href="/admin/stores/<?= $escape($store['id']) ?>"
                class="shipping-admin-button secondary"
            >
                Back to Store
            </a>

            <a
                href="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/create"
                class="shipping-admin-button"
            >
                Add Shipping Method
            </a>
        </div>
    </header>

    <?php if (! empty($success)): ?>
        <div class="shipping-admin-alert success" role="alert">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div class="shipping-admin-alert error" role="alert">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="shipping-admin-summary">
        <article class="shipping-admin-stat">
            <span>Total Methods</span>
            <strong><?= count($shippingMethods) ?></strong>
        </article>

        <article class="shipping-admin-stat">
            <span>Active Methods</span>
            <strong><?= $activeCount ?></strong>
        </article>

        <article class="shipping-admin-stat">
            <span>Inactive Methods</span>
            <strong><?= count($shippingMethods) - $activeCount ?></strong>
        </article>
    </section>

    <section class="shipping-admin-panel">
        <?php if (empty($shippingMethods)): ?>
            <div class="shipping-admin-empty">
                <h2>No shipping methods yet</h2>
                <p>
                    Create a shipping method before customers use checkout.
                </p>

                <a
                    href="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/create"
                    class="shipping-admin-button"
                >
                    Create Shipping Method
                </a>
            </div>
        <?php else: ?>
            <div class="shipping-admin-table-wrap">
                <table class="shipping-admin-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Code</th>
                            <th>Price</th>
                            <th>Delivery Estimate</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($shippingMethods as $method): ?>
                            <?php
                            $isActive = (int) ($method['is_active'] ?? 0) === 1;
                            $minimumDays = $method['estimated_days_min'] ?? null;
                            $maximumDays = $method['estimated_days_max'] ?? null;
                            ?>

                            <tr>
                                <td>
                                    <div class="shipping-admin-method-name">
                                        <strong><?= $escape($method['name']) ?></strong>

                                        <?php if (! empty($method['description'])): ?>
                                            <small><?= $escape($method['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="shipping-admin-code">
                                        <?= $escape($method['code']) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        $<?= number_format((float) $method['price'], 2) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if ($minimumDays !== null && $maximumDays !== null): ?>
                                        <?= $escape($minimumDays) ?>–<?= $escape($maximumDays) ?> business days
                                    <?php elseif ($minimumDays !== null): ?>
                                        <?= $escape($minimumDays) ?> business days
                                    <?php elseif ($maximumDays !== null): ?>
                                        Up to <?= $escape($maximumDays) ?> business days
                                    <?php else: ?>
                                        <span class="shipping-admin-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= $escape($method['sort_order'] ?? 0) ?></td>

                                <td>
                                    <span class="shipping-admin-status <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="shipping-admin-row-actions">
                                        <a
                                            href="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/<?= $escape($method['id']) ?>/edit"
                                            class="shipping-admin-button secondary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/<?= $escape($method['id']) ?>/toggle"
                                        >
                                            <input
                                                type="hidden"
                                                name="_csrf_token"
                                                value="<?= $escape($csrf_token) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="shipping-admin-button <?= $isActive ? 'warning' : '' ?>"
                                            >
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/<?= $escape($method['id']) ?>/delete"
                                            onsubmit="return confirm('Delete this shipping method? Existing order snapshots will remain unchanged.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="_csrf_token"
                                                value="<?= $escape($csrf_token) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="shipping-admin-button danger"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
