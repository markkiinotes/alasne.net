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

$value = static function (
    string $key,
    mixed $default = ''
) use ($old, $paymentMethod): mixed {
    return array_key_exists($key, $old)
        ? $old[$key]
        : (
            $paymentMethod[$key]
            ?? $default
        );
};

$config = json_decode(
    (string) (
        $paymentMethod['config_json'] ?? ''
    ),
    true
);

$storedDefaultScenario =
    is_array($config)
        ? (
            $config['default_scenario']
            ?? 'approved'
        )
        : 'approved';

$defaultScenario = strtolower(
    (string) $value(
        'default_scenario',
        $storedDefaultScenario
    )
);

$isActive =
    (int) $value('is_active', 0) === 1;
?>

<style>
.payment-form-page {
    display: grid;
    gap: 24px;
    max-width: 980px;
}

.payment-form-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.payment-form-header h1 {
    margin: 0 0 8px;
}

.payment-form-header p {
    margin: 0;
    color: #64748b;
    line-height: 1.55;
}

.payment-form-panel,
.payment-form-help {
    padding: 26px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 10px 26px rgba(15, 23, 42, 0.06);
}

.payment-form-help {
    background: #fffbeb;
    border-color: #fcd34d;
    box-shadow: none;
}

.payment-form-help h2 {
    margin: 0 0 10px;
    color: #92400e;
    font-size: 17px;
}

.payment-form-help p {
    margin: 0;
    color: #78350f;
    line-height: 1.6;
}

.payment-form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.payment-form-group {
    display: grid;
    gap: 7px;
}

.payment-form-group.full {
    grid-column: 1 / -1;
}

.payment-form-group label {
    color: #334155;
    font-weight: 700;
}

.payment-form-group small {
    color: #64748b;
    line-height: 1.45;
}

.payment-form-group input,
.payment-form-group textarea,
.payment-form-group select {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 11px 12px;
    font: inherit;
    color: #0f172a;
    background: #ffffff;
}

.payment-form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.payment-form-group input:focus,
.payment-form-group textarea:focus,
.payment-form-group select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.12);
}

.payment-form-check {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.payment-form-check input {
    width: auto;
    margin-top: 3px;
}

.payment-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.payment-form-button {
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

.payment-form-button.secondary {
    background: #e2e8f0;
    color: #0f172a;
}

.payment-form-alert {
    padding: 14px 16px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
}

@media (max-width: 760px) {
    .payment-form-grid {
        grid-template-columns: 1fr;
    }

    .payment-form-group.full {
        grid-column: auto;
    }
}
</style>

<div class="payment-form-page">
    <header class="payment-form-header">
        <div>
            <h1>Edit Payment Method</h1>

            <p>
                Update
                <strong>
                    <?= $escape(
                        $paymentMethod['name']
                    ) ?>
                </strong>
                for
                <strong>
                    <?= $escape($store['name']) ?>
                </strong>.
            </p>
        </div>

        <a
            href="/admin/stores/<?= $escape(
                $store['id']
            ) ?>/payment-methods"
            class="payment-form-button secondary"
        >
            Back to Payment Methods
        </a>
    </header>

    <?php if (! empty($error)): ?>
        <div
            class="payment-form-alert"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="payment-form-help">
        <h2>Historical payments remain unchanged</h2>

        <p>
            Changes affect future checkout selections only.
            Existing orders and payment transactions retain
            their saved method and provider snapshots.
        </p>
    </section>

    <section class="payment-form-panel">
        <form
            method="POST"
            action="/admin/stores/<?= $escape(
                $store['id']
            ) ?>/payment-methods/<?= $escape(
                $paymentMethod['id']
            ) ?>"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="payment-form-grid">
                <div class="payment-form-group">
                    <label for="name">
                        Display Name *
                    </label>

                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="<?= $escape(
                            $value('name')
                        ) ?>"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="code">Code *</label>

                    <input
                        id="code"
                        type="text"
                        name="code"
                        value="<?= $escape(
                            $value('code')
                        ) ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="provider">
                        Provider
                    </label>

                    <input
                        id="provider"
                        type="text"
                        value="<?= $escape(
                            $paymentMethod[
                                'provider'
                            ] ?? 'test'
                        ) ?>"
                        disabled
                    >
                </div>

                <div class="payment-form-group">
                    <label for="sort_order">
                        Display Order
                    </label>

                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="<?= $escape(
                            $value('sort_order', 0)
                        ) ?>"
                        step="1"
                        inputmode="numeric"
                    >
                </div>

                <div
                    class="payment-form-group full"
                >
                    <label for="description">
                        Checkout Description
                    </label>

                    <input
                        id="description"
                        type="text"
                        name="description"
                        value="<?= $escape(
                            $value('description')
                        ) ?>"
                        maxlength="255"
                    >
                </div>

                <div
                    class="payment-form-group full"
                >
                    <label for="instructions">
                        Instructions
                    </label>

                    <textarea
                        id="instructions"
                        name="instructions"
                    ><?= $escape(
                        $value('instructions')
                    ) ?></textarea>
                </div>

                <div class="payment-form-group">
                    <label for="default_scenario">
                        Default Test Result
                    </label>

                    <select
                        id="default_scenario"
                        name="default_scenario"
                        required
                    >
                        <option
                            value="approved"
                            <?= $defaultScenario
                                === 'approved'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Approved
                        </option>

                        <option
                            value="declined"
                            <?= $defaultScenario
                                === 'declined'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Declined
                        </option>

                        <option
                            value="error"
                            <?= $defaultScenario
                                === 'error'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Provider Error
                        </option>
                    </select>
                </div>

                <div class="payment-form-group">
                    <label class="payment-form-check">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= $isActive
                                ? 'checked'
                                : '' ?>
                        >

                        <span>
                            <strong>
                                Active at checkout
                            </strong>
                            <br>

                            <small>
                                Allow customers to select
                                this payment method.
                            </small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="payment-form-actions">
                <button
                    type="submit"
                    class="payment-form-button"
                >
                    Save Payment Method
                </button>

                <a
                    href="/admin/stores/<?= $escape(
                        $store['id']
                    ) ?>/payment-methods"
                    class="payment-form-button secondary"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>
</div>
