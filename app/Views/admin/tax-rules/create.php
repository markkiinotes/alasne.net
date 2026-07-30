<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$old = $old ?? [];

$isActive = array_key_exists('is_active', $old)
    ? (int) $old['is_active'] === 1
    : true;

$taxShipping = array_key_exists(
    'tax_shipping',
    $old
)
    ? (int) $old['tax_shipping'] === 1
    : false;
?>

<style>
.tax-form-page {
    display: grid;
    gap: 24px;
    max-width: 980px;
}

.tax-form-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.tax-form-header h1 {
    margin: 0 0 8px;
}

.tax-form-header p {
    margin: 0;
    color: #64748b;
    line-height: 1.55;
}

.tax-form-panel,
.tax-form-help {
    padding: 26px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
}

.tax-form-help {
    background: #f8fafc;
    box-shadow: none;
}

.tax-form-help h2 {
    margin: 0 0 10px;
    font-size: 17px;
}

.tax-form-help p {
    margin: 0;
    color: #475569;
    line-height: 1.6;
}

.tax-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.tax-form-group {
    display: grid;
    gap: 7px;
}

.tax-form-group.full {
    grid-column: 1 / -1;
}

.tax-form-group label {
    color: #334155;
    font-weight: 700;
}

.tax-form-group small {
    color: #64748b;
    line-height: 1.45;
}

.tax-form-group input {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 11px 12px;
    font: inherit;
    color: #0f172a;
    background: #ffffff;
}

.tax-form-group input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.tax-form-checks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    grid-column: 1 / -1;
}

.tax-form-check {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.tax-form-check input {
    width: auto;
    margin-top: 3px;
}

.tax-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.tax-form-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: #111827;
    color: #ffffff;
    text-decoration: none;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.tax-form-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.tax-form-alert {
    padding: 14px 16px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
}

@media (max-width: 760px) {
    .tax-form-grid,
    .tax-form-checks {
        grid-template-columns: 1fr;
    }

    .tax-form-group.full,
    .tax-form-checks {
        grid-column: auto;
    }
}
</style>

<div class="tax-form-page">
    <header class="tax-form-header">
        <div>
            <h1>Create Tax Rule</h1>

            <p>
                Add a destination-based tax rate for
                <strong><?= $escape($store['name']) ?></strong>.
            </p>
        </div>

        <a
            href="/admin/stores/<?= $escape($store['id']) ?>/tax-rules"
            class="tax-form-button secondary"
        >
            Back to Tax Rules
        </a>
    </header>

    <?php if (! empty($error)): ?>
        <div class="tax-form-alert" role="alert">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="tax-form-help">
        <h2>Destination matching</h2>

        <p>
            Country is required. Leave state and postal-code
            prefix blank for a country-wide rule. Add a state
            for a state rule, or add a postal prefix for a more
            specific local rule. Use the combined percentage
            rate you intend to charge.
        </p>
    </section>

    <section class="tax-form-panel">
        <form
            method="POST"
            action="/admin/stores/<?= $escape($store['id']) ?>/tax-rules"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="tax-form-grid">
                <div class="tax-form-group">
                    <label for="name">Rule Name *</label>

                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="<?= $escape($old['name'] ?? '') ?>"
                        placeholder="South Carolina Sales Tax"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="tax-form-group">
                    <label for="code">Code</label>

                    <input
                        id="code"
                        type="text"
                        name="code"
                        value="<?= $escape($old['code'] ?? '') ?>"
                        placeholder="sc-sales-tax"
                        maxlength="100"
                    >

                    <small>
                        Leave blank to generate it from the rule name.
                    </small>
                </div>

                <div class="tax-form-group">
                    <label for="country_code">Country *</label>

                    <input
                        id="country_code"
                        type="text"
                        name="country_code"
                        value="<?= $escape(
                            $old['country_code'] ?? 'US'
                        ) ?>"
                        placeholder="US"
                        maxlength="100"
                        required
                    >

                    <small>
                        Two-letter code or a common country name.
                    </small>
                </div>

                <div class="tax-form-group">
                    <label for="state_region">
                        State or Region
                    </label>

                    <input
                        id="state_region"
                        type="text"
                        name="state_region"
                        value="<?= $escape(
                            $old['state_region'] ?? ''
                        ) ?>"
                        placeholder="SC or South Carolina"
                        maxlength="100"
                    >

                    <small>
                        Leave blank for a country-wide rule.
                    </small>
                </div>

                <div class="tax-form-group">
                    <label for="postal_code_prefix">
                        Postal-Code Prefix
                    </label>

                    <input
                        id="postal_code_prefix"
                        type="text"
                        name="postal_code_prefix"
                        value="<?= $escape(
                            $old['postal_code_prefix'] ?? ''
                        ) ?>"
                        placeholder="290"
                        maxlength="20"
                    >

                    <small>
                        Example: 290 matches postal codes beginning with 290.
                    </small>
                </div>

                <div class="tax-form-group">
                    <label for="rate">Tax Rate (%) *</label>

                    <input
                        id="rate"
                        type="number"
                        name="rate"
                        value="<?= $escape($old['rate'] ?? '0.000') ?>"
                        min="0"
                        max="100"
                        step="0.001"
                        inputmode="decimal"
                        required
                    >

                    <small>
                        Enter the combined percentage, such as 8.000.
                    </small>
                </div>

                <div class="tax-form-group">
                    <label for="priority">Priority</label>

                    <input
                        id="priority"
                        type="number"
                        name="priority"
                        value="<?= $escape(
                            $old['priority'] ?? '0'
                        ) ?>"
                        step="1"
                        inputmode="numeric"
                    >

                    <small>
                        Higher numbers win when rules are equally specific.
                    </small>
                </div>

                <div class="tax-form-checks">
                    <label class="tax-form-check">
                        <input
                            type="checkbox"
                            name="tax_shipping"
                            value="1"
                            <?= $taxShipping ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>Tax shipping charge</strong><br>

                            <small>
                                Include the selected shipping fee in the
                                taxable amount.
                            </small>
                        </span>
                    </label>

                    <label class="tax-form-check">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= $isActive ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>Active at checkout</strong><br>

                            <small>
                                Allow this rule to match new orders.
                            </small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="tax-form-actions">
                <button
                    type="submit"
                    class="tax-form-button"
                >
                    Create Tax Rule
                </button>

                <a
                    href="/admin/stores/<?= $escape($store['id']) ?>/tax-rules"
                    class="tax-form-button secondary"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>
</div>
