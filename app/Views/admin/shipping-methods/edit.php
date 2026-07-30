<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$old = $old ?? [];

$value = static function (string $key, mixed $default = '') use ($old, $shippingMethod): mixed {
    return array_key_exists($key, $old)
        ? $old[$key]
        : ($shippingMethod[$key] ?? $default);
};

$isActive = (int) $value('is_active', 0) === 1;
?>

<style>
.shipping-form-page {
    display: grid;
    gap: 24px;
    max-width: 980px;
}

.shipping-form-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.shipping-form-header h1 {
    margin: 0 0 8px;
}

.shipping-form-header p {
    margin: 0;
    color: #64748b;
}

.shipping-form-panel {
    padding: 26px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
}

.shipping-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.shipping-form-group {
    display: grid;
    gap: 7px;
}

.shipping-form-group.full {
    grid-column: 1 / -1;
}

.shipping-form-group label {
    color: #334155;
    font-weight: 700;
}

.shipping-form-group small {
    color: #64748b;
    line-height: 1.45;
}

.shipping-form-group input,
.shipping-form-group textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 11px 12px;
    font: inherit;
    color: #0f172a;
    background: #ffffff;
}

.shipping-form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.shipping-form-group input:focus,
.shipping-form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.shipping-form-check {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.shipping-form-check input {
    margin-top: 3px;
}

.shipping-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.shipping-form-button {
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

.shipping-form-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.shipping-form-alert {
    padding: 14px 16px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
}

@media (max-width: 760px) {
    .shipping-form-grid {
        grid-template-columns: 1fr;
    }

    .shipping-form-group.full {
        grid-column: auto;
    }
}
</style>

<div class="shipping-form-page">
    <header class="shipping-form-header">
        <div>
            <h1>Edit Shipping Method</h1>
            <p>
                Update <strong><?= $escape($shippingMethod['name']) ?></strong>
                for <strong><?= $escape($store['name']) ?></strong>.
            </p>
        </div>

        <a
            href="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods"
            class="shipping-form-button secondary"
        >
            Back to Shipping Methods
        </a>
    </header>

    <?php if (! empty($error)): ?>
        <div class="shipping-form-alert" role="alert">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="shipping-form-panel">
        <form
            method="POST"
            action="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods/<?= $escape($shippingMethod['id']) ?>"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="shipping-form-grid">
                <div class="shipping-form-group">
                    <label for="name">Method Name *</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="<?= $escape($value('name')) ?>"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="shipping-form-group">
                    <label for="code">Code *</label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        value="<?= $escape($value('code')) ?>"
                        maxlength="100"
                        required
                    >
                    <small>
                        The code is normalized to lowercase letters, numbers, and hyphens.
                    </small>
                </div>

                <div class="shipping-form-group full">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        maxlength="255"
                    ><?= $escape($value('description')) ?></textarea>
                </div>

                <div class="shipping-form-group">
                    <label for="price">Shipping Price *</label>
                    <input
                        id="price"
                        type="number"
                        name="price"
                        value="<?= $escape(number_format((float) $value('price', 0), 2, '.', '')) ?>"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        required
                    >
                </div>

                <div class="shipping-form-group">
                    <label for="sort_order">Display Order</label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="<?= $escape($value('sort_order', 0)) ?>"
                        step="1"
                        inputmode="numeric"
                    >
                    <small>Lower numbers appear first during checkout.</small>
                </div>

                <div class="shipping-form-group">
                    <label for="estimated_days_min">Minimum Delivery Days</label>
                    <input
                        id="estimated_days_min"
                        type="number"
                        name="estimated_days_min"
                        value="<?= $escape($value('estimated_days_min')) ?>"
                        min="0"
                        step="1"
                        inputmode="numeric"
                    >
                </div>

                <div class="shipping-form-group">
                    <label for="estimated_days_max">Maximum Delivery Days</label>
                    <input
                        id="estimated_days_max"
                        type="number"
                        name="estimated_days_max"
                        value="<?= $escape($value('estimated_days_max')) ?>"
                        min="0"
                        step="1"
                        inputmode="numeric"
                    >
                </div>

                <div class="shipping-form-group full">
                    <label class="shipping-form-check">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= $isActive ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>Active at checkout</strong><br>
                            <small>
                                Customers can select this method while placing an order.
                            </small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="shipping-form-actions">
                <button type="submit" class="shipping-form-button">
                    Save Shipping Method
                </button>

                <a
                    href="/admin/stores/<?= $escape($store['id']) ?>/shipping-methods"
                    class="shipping-form-button secondary"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>
</div>
