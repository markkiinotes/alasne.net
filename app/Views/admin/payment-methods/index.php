<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$paymentMethods = $paymentMethods ?? [];
$activeCount = 0;

foreach ($paymentMethods as $method) {
    if ((int) ($method['is_active'] ?? 0) === 1) {
        $activeCount++;
    }
}
?>

<style>
.payment-methods-page {
    display: grid;
    gap: 24px;
}

.payment-methods-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.payment-methods-header h1 {
    margin: 0 0 8px;
}

.payment-methods-header p {
    margin: 0;
    color: #64748b;
    line-height: 1.55;
}

.payment-methods-actions,
.payment-method-row-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.payment-method-button {
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

.payment-method-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.payment-method-button.warning {
    background: #fef3c7;
    color: #92400e;
}

.payment-method-alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-weight: 700;
}

.payment-method-alert.success {
    background: #dcfce7;
    color: #166534;
}

.payment-method-alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.payment-method-notice {
    padding: 16px 18px;
    border: 1px solid #fcd34d;
    border-radius: 14px;
    background: #fffbeb;
    color: #78350f;
    line-height: 1.55;
}

.payment-method-summary {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.payment-method-stat,
.payment-method-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 10px 26px rgba(15, 23, 42, 0.06);
}

.payment-method-stat {
    padding: 20px;
}

.payment-method-stat span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.payment-method-stat strong {
    font-size: 28px;
}

.payment-method-panel {
    overflow: hidden;
}

.payment-method-table-wrap {
    overflow-x: auto;
}

.payment-method-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-method-table th,
.payment-method-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

.payment-method-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.payment-method-table tr:last-child td {
    border-bottom: 0;
}

.payment-method-name {
    display: grid;
    gap: 5px;
}

.payment-method-name small {
    color: #64748b;
    line-height: 1.45;
}

.payment-method-code,
.payment-method-provider {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
}

.payment-method-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.payment-method-status.active {
    background: #dcfce7;
    color: #166534;
}

.payment-method-status.inactive {
    background: #e2e8f0;
    color: #475569;
}

.payment-method-row-actions form {
    margin: 0;
}

.payment-method-empty {
    padding: 42px 24px;
    text-align: center;
}

.payment-method-empty h2 {
    margin: 0 0 8px;
}

.payment-method-empty p {
    margin: 0 0 20px;
    color: #64748b;
}

@media (max-width: 900px) {
    .payment-method-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="payment-methods-page">
    <header class="payment-methods-header">
        <div>
            <h1>Payment Methods</h1>

            <p>
                Manage checkout payment options for
                <strong>
                    <?= $escape($store['name']) ?>
                </strong>.
            </p>
        </div>

        <div class="payment-methods-actions">
            <a
                href="/admin/stores/<?= $escape(
                    $store['id']
                ) ?>"
                class="payment-method-button secondary"
            >
                Back to Store
            </a>

            <a
                href="/admin/stores/<?= $escape(
                    $store['id']
                ) ?>/payment-methods/create"
                class="payment-method-button"
            >
                Add Payment Method
            </a>
        </div>
    </header>

    <?php if (! empty($success)): ?>
        <div
            class="payment-method-alert success"
            role="alert"
        >
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div
            class="payment-method-alert error"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <div class="payment-method-notice">
        Only the simulated test provider is installed.
        It never sends card information or makes an external
        payment request. Live providers will be added through
        separate provider adapters.
    </div>

    <section class="payment-method-summary">
        <article class="payment-method-stat">
            <span>Total Methods</span>

            <strong>
                <?= count($paymentMethods) ?>
            </strong>
        </article>

        <article class="payment-method-stat">
            <span>Active at Checkout</span>

            <strong>
                <?= $activeCount ?>
            </strong>
        </article>

        <article class="payment-method-stat">
            <span>Installed Providers</span>

            <strong>1</strong>
        </article>
    </section>

    <section class="payment-method-panel">
        <?php if (empty($paymentMethods)): ?>
            <div class="payment-method-empty">
                <h2>No payment methods yet</h2>

                <p>
                    Checkout cannot accept an order until
                    at least one method is active.
                </p>

                <a
                    href="/admin/stores/<?= $escape(
                        $store['id']
                    ) ?>/payment-methods/create"
                    class="payment-method-button"
                >
                    Create Payment Method
                </a>
            </div>
        <?php else: ?>
            <div class="payment-method-table-wrap">
                <table class="payment-method-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Provider</th>
                            <th>Default Result</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $paymentMethods as $method
                        ): ?>
                            <?php
                            $isActive =
                                (int) (
                                    $method['is_active']
                                    ?? 0
                                ) === 1;

                            $config = json_decode(
                                (string) (
                                    $method[
                                        'config_json'
                                    ] ?? ''
                                ),
                                true
                            );

                            $defaultScenario =
                                is_array($config)
                                    ? (
                                        $config[
                                            'default_scenario'
                                        ]
                                        ?? 'approved'
                                    )
                                    : 'approved';
                            ?>

                            <tr>
                                <td>
                                    <div
                                        class="payment-method-name"
                                    >
                                        <strong>
                                            <?= $escape(
                                                $method['name']
                                            ) ?>
                                        </strong>

                                        <span
                                            class="payment-method-code"
                                        >
                                            <?= $escape(
                                                $method['code']
                                            ) ?>
                                        </span>

                                        <?php if (! empty(
                                            $method[
                                                'description'
                                            ]
                                        )): ?>
                                            <small>
                                                <?= $escape(
                                                    $method[
                                                        'description'
                                                    ]
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="payment-method-provider"
                                    >
                                        <?= $escape(
                                            $method[
                                                'provider'
                                            ]
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $escape(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string)
                                                $defaultScenario
                                            )
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $method[
                                            'sort_order'
                                        ] ?? 0
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="payment-method-status <?= $isActive
                                            ? 'active'
                                            : 'inactive' ?>"
                                    >
                                        <?= $isActive
                                            ? 'Active'
                                            : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <div
                                        class="payment-method-row-actions"
                                    >
                                        <a
                                            href="/admin/stores/<?= $escape(
                                                $store['id']
                                            ) ?>/payment-methods/<?= $escape(
                                                $method['id']
                                            ) ?>/edit"
                                            class="payment-method-button secondary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="/admin/stores/<?= $escape(
                                                $store['id']
                                            ) ?>/payment-methods/<?= $escape(
                                                $method['id']
                                            ) ?>/toggle"
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
                                                class="payment-method-button <?= $isActive
                                                    ? 'warning'
                                                    : '' ?>"
                                            >
                                                <?= $isActive
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>
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
