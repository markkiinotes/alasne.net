<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$taxRules = $taxRules ?? [];
$activeCount = 0;
$shippingTaxCount = 0;

foreach ($taxRules as $rule) {
    if ((int) ($rule['is_active'] ?? 0) === 1) {
        $activeCount++;
    }

    if ((int) ($rule['tax_shipping'] ?? 0) === 1) {
        $shippingTaxCount++;
    }
}
?>

<style>
.tax-admin-page {
    display: grid;
    gap: 24px;
}

.tax-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.tax-admin-header h1 {
    margin: 0 0 8px;
}

.tax-admin-header p {
    margin: 0;
    color: #64748b;
    line-height: 1.55;
}

.tax-admin-actions,
.tax-admin-row-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tax-admin-button {
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

.tax-admin-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.tax-admin-button.warning {
    background: #fef3c7;
    color: #92400e;
}

.tax-admin-button.danger {
    background: #fee2e2;
    color: #991b1b;
}

.tax-admin-alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-weight: 700;
}

.tax-admin-alert.success {
    background: #dcfce7;
    color: #166534;
}

.tax-admin-alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.tax-admin-notice {
    padding: 16px 18px;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    background: #eff6ff;
    color: #1e3a8a;
    line-height: 1.55;
}

.tax-admin-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.tax-admin-stat,
.tax-admin-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
}

.tax-admin-stat {
    padding: 20px;
}

.tax-admin-stat span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.tax-admin-stat strong {
    font-size: 28px;
}

.tax-admin-panel {
    overflow: hidden;
}

.tax-admin-table-wrap {
    overflow-x: auto;
}

.tax-admin-table {
    width: 100%;
    border-collapse: collapse;
}

.tax-admin-table th,
.tax-admin-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

.tax-admin-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.tax-admin-table tr:last-child td {
    border-bottom: 0;
}

.tax-admin-rule-name {
    display: grid;
    gap: 5px;
}

.tax-admin-rule-name small,
.tax-admin-muted {
    color: #64748b;
}

.tax-admin-code,
.tax-admin-destination {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
}

.tax-admin-rate {
    font-size: 16px;
    font-weight: 800;
}

.tax-admin-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.tax-admin-status.active {
    background: #dcfce7;
    color: #166534;
}

.tax-admin-status.inactive {
    background: #e2e8f0;
    color: #475569;
}

.tax-admin-row-actions form {
    margin: 0;
}

.tax-admin-empty {
    padding: 42px 24px;
    text-align: center;
}

.tax-admin-empty h2 {
    margin: 0 0 8px;
}

.tax-admin-empty p {
    margin: 0 0 20px;
    color: #64748b;
}

@media (max-width: 900px) {
    .tax-admin-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="tax-admin-page">
    <header class="tax-admin-header">
        <div>
            <h1>Tax Rules</h1>

            <p>
                Manage destination-based tax rates for
                <strong><?= $escape($store['name']) ?></strong>.
            </p>
        </div>

        <div class="tax-admin-actions">
            <a
                href="/admin/stores/<?= $escape($store['id']) ?>"
                class="tax-admin-button secondary"
            >
                Back to Store
            </a>

            <a
                href="/admin/stores/<?= $escape($store['id']) ?>/tax-rules/create"
                class="tax-admin-button"
            >
                Add Tax Rule
            </a>
        </div>
    </header>

    <?php if (! empty($success)): ?>
        <div
            class="tax-admin-alert success"
            role="alert"
        >
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div
            class="tax-admin-alert error"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <div class="tax-admin-notice">
        The most specific matching rule wins. A postal-code
        prefix outranks a state rule, and a state rule
        outranks a country-wide rule. Priority resolves ties.
        When no active rule matches, checkout charges $0.00 tax.
    </div>

    <section class="tax-admin-summary">
        <article class="tax-admin-stat">
            <span>Total Rules</span>
            <strong><?= count($taxRules) ?></strong>
        </article>

        <article class="tax-admin-stat">
            <span>Active Rules</span>
            <strong><?= $activeCount ?></strong>
        </article>

        <article class="tax-admin-stat">
            <span>Rules Taxing Shipping</span>
            <strong><?= $shippingTaxCount ?></strong>
        </article>
    </section>

    <section class="tax-admin-panel">
        <?php if (empty($taxRules)): ?>
            <div class="tax-admin-empty">
                <h2>No tax rules yet</h2>

                <p>
                    Checkout will continue charging $0.00 tax
                    until an active destination rule is created.
                </p>

                <a
                    href="/admin/stores/<?= $escape($store['id']) ?>/tax-rules/create"
                    class="tax-admin-button"
                >
                    Create Tax Rule
                </a>
            </div>
        <?php else: ?>
            <div class="tax-admin-table-wrap">
                <table class="tax-admin-table">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Destination</th>
                            <th>Rate</th>
                            <th>Shipping</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($taxRules as $rule): ?>
                            <?php
                            $isActive =
                                (int) (
                                    $rule['is_active'] ?? 0
                                ) === 1;

                            $taxShipping =
                                (int) (
                                    $rule['tax_shipping'] ?? 0
                                ) === 1;

                            $destinationParts = [
                                $rule['country_code'] ?? '',
                            ];

                            if (! empty($rule['state_region'])) {
                                $destinationParts[] =
                                    $rule['state_region'];
                            }

                            if (
                                ! empty(
                                    $rule['postal_code_prefix']
                                )
                            ) {
                                $destinationParts[] =
                                    'ZIP '
                                    . $rule[
                                        'postal_code_prefix'
                                    ]
                                    . '*';
                            }

                            $destination = implode(
                                ' / ',
                                array_filter(
                                    $destinationParts
                                )
                            );
                            ?>

                            <tr>
                                <td>
                                    <div class="tax-admin-rule-name">
                                        <strong>
                                            <?= $escape($rule['name']) ?>
                                        </strong>

                                        <span class="tax-admin-code">
                                            <?= $escape($rule['code']) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="tax-admin-destination">
                                        <?= $escape($destination) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="tax-admin-rate">
                                        <?= number_format(
                                            (float) $rule['rate'],
                                            3
                                        ) ?>%
                                    </span>
                                </td>

                                <td>
                                    <?= $taxShipping
                                        ? 'Taxed'
                                        : 'Not taxed' ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $rule['priority'] ?? 0
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="tax-admin-status <?= $isActive
                                            ? 'active'
                                            : 'inactive' ?>"
                                    >
                                        <?= $isActive
                                            ? 'Active'
                                            : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="tax-admin-row-actions">
                                        <a
                                            href="/admin/stores/<?= $escape($store['id']) ?>/tax-rules/<?= $escape($rule['id']) ?>/edit"
                                            class="tax-admin-button secondary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="/admin/stores/<?= $escape($store['id']) ?>/tax-rules/<?= $escape($rule['id']) ?>/toggle"
                                        >
                                            <input
                                                type="hidden"
                                                name="_csrf_token"
                                                value="<?= $escape($csrf_token) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="tax-admin-button <?= $isActive
                                                    ? 'warning'
                                                    : '' ?>"
                                            >
                                                <?= $isActive
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="/admin/stores/<?= $escape($store['id']) ?>/tax-rules/<?= $escape($rule['id']) ?>/delete"
                                            onsubmit="return confirm('Delete this tax rule? Existing order snapshots will remain unchanged.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="_csrf_token"
                                                value="<?= $escape($csrf_token) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="tax-admin-button danger"
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
