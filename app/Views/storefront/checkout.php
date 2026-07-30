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
$cartItems = $cartItems ?? [];
$shippingMethods = $shippingMethods ?? [];
$paymentMethods = $paymentMethods ?? [];

$selectedShippingMethodId = (int) (
    $old['shipping_method_id']
    ?? ($shippingMethods[0]['id'] ?? 0)
);

$selectedPaymentMethodId = (int) (
    $old['payment_method_id']
    ?? ($paymentMethods[0]['id'] ?? 0)
);

$selectedScenario = strtolower(
    (string) (
        $old['test_scenario']
        ?? 'approved'
    )
);
?>

<style>
.payment-method-list {
    display: grid;
    gap: 12px;
}

.payment-method-option {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    width: 100%;
    padding: 16px;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    background: #ffffff;
    cursor: pointer;
    font-weight: 400;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.payment-method-option:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}

.payment-method-option:has(
    input[type="radio"]:checked
) {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.12);
}

.payment-method-option input[type="radio"] {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    min-height: 0;
    margin: 2px 0 0;
    padding: 0;
    accent-color: #2563eb;
}

.payment-method-content {
    display: grid;
    gap: 5px;
}

.payment-method-content strong {
    color: #0f172a;
    font-size: 16px;
}

.payment-method-content small {
    color: #64748b;
    line-height: 1.45;
}

.test-payment-panel {
    margin-top: 14px;
    padding: 16px;
    border: 1px solid #fcd34d;
    border-radius: 14px;
    background: #fffbeb;
}

.test-payment-panel h3 {
    margin: 0 0 8px;
    color: #92400e;
}

.test-payment-panel p {
    margin: 0 0 14px;
    color: #78350f;
    line-height: 1.5;
}

.checkout-summary-status {
    color: #64748b;
    font-size: 13px;
    line-height: 1.45;
}

.checkout-security-note {
    margin-top: 16px;
    padding: 14px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #475569;
    font-size: 13px;
    line-height: 1.55;
}

.checkout-form-panel .storefront-cart-button {
    width: 100%;
    margin-top: 8px;
}

@media (max-width: 640px) {
    .payment-method-option {
        padding: 14px;
    }
}
</style>

<section class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a
            href="/store/<?= $escape($store['slug']) ?>/cart"
            class="back-link"
        >
            ← Back to Cart
        </a>

        <a
            href="/store/<?= $escape($store['slug']) ?>"
            class="cart-link"
        >
            <?= $escape($store['name']) ?>
        </a>
    </div>
</section>

<main class="storefront-container storefront-section">
    <div class="section-header">
        <p class="eyebrow dark-eyebrow">
            Secure checkout
        </p>

        <h1>Complete Your Order</h1>

        <p>
            Shipping, tax, and payment are verified on the
            server before the order is completed.
        </p>
    </div>

    <?php if (! empty($error)): ?>
        <div
            class="storefront-alert danger"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">
        <section class="checkout-form-panel">
            <form
                method="POST"
                action="/store/<?= $escape($store['slug']) ?>/checkout"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <h2>Contact Information</h2>

                <div class="checkout-grid">
                    <div class="form-group">
                        <label for="first_name">
                            First Name *
                        </label>

                        <input
                            id="first_name"
                            type="text"
                            name="first_name"
                            value="<?= $escape(
                                $old['first_name'] ?? ''
                            ) ?>"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="last_name">
                            Last Name *
                        </label>

                        <input
                            id="last_name"
                            type="text"
                            name="last_name"
                            value="<?= $escape(
                                $old['last_name'] ?? ''
                            ) ?>"
                            autocomplete="family-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">
                            Email Address *
                        </label>

                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="<?= $escape(
                                $old['email'] ?? ''
                            ) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            Phone
                        </label>

                        <input
                            id="phone"
                            type="tel"
                            name="phone"
                            value="<?= $escape(
                                $old['phone'] ?? ''
                            ) ?>"
                            autocomplete="tel"
                        >
                    </div>
                </div>

                <h2>Shipping Address</h2>

                <div class="form-group">
                    <label for="address_line_1">
                        Street Address *
                    </label>

                    <input
                        id="address_line_1"
                        type="text"
                        name="address_line_1"
                        value="<?= $escape(
                            $old['address_line_1'] ?? ''
                        ) ?>"
                        autocomplete="address-line1"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="address_line_2">
                        Apartment, Suite, or Unit
                    </label>

                    <input
                        id="address_line_2"
                        type="text"
                        name="address_line_2"
                        value="<?= $escape(
                            $old['address_line_2'] ?? ''
                        ) ?>"
                        autocomplete="address-line2"
                    >
                </div>

                <div class="checkout-grid">
                    <div class="form-group">
                        <label for="city">City *</label>

                        <input
                            id="city"
                            type="text"
                            name="city"
                            value="<?= $escape(
                                $old['city'] ?? ''
                            ) ?>"
                            autocomplete="address-level2"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="state">
                            State or Region *
                        </label>

                        <input
                            id="state"
                            type="text"
                            name="state"
                            value="<?= $escape(
                                $old['state'] ?? ''
                            ) ?>"
                            autocomplete="address-level1"
                            placeholder="SC or South Carolina"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="postal_code">
                            Postal Code *
                        </label>

                        <input
                            id="postal_code"
                            type="text"
                            name="postal_code"
                            value="<?= $escape(
                                $old['postal_code'] ?? ''
                            ) ?>"
                            autocomplete="postal-code"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="country">
                            Country *
                        </label>

                        <input
                            id="country"
                            type="text"
                            name="country"
                            value="<?= $escape(
                                $old['country']
                                ?? 'United States'
                            ) ?>"
                            autocomplete="country-name"
                            required
                        >
                    </div>
                </div>

                <h2>Shipping Method</h2>

                <?php if (empty($shippingMethods)): ?>
                    <div
                        class="storefront-alert danger"
                        role="alert"
                    >
                        No active shipping method is available.
                    </div>
                <?php else: ?>
                    <div class="shipping-method-list">
                        <?php foreach (
                            $shippingMethods as $method
                        ): ?>
                            <?php
                            $minimumDays =
                                $method[
                                    'estimated_days_min'
                                ] ?? null;

                            $maximumDays =
                                $method[
                                    'estimated_days_max'
                                ] ?? null;

                            $estimate = '';

                            if (
                                $minimumDays !== null
                                && $maximumDays !== null
                            ) {
                                $estimate =
                                    $minimumDays
                                    . '–'
                                    . $maximumDays
                                    . ' business days';
                            } elseif (
                                $minimumDays !== null
                            ) {
                                $estimate =
                                    $minimumDays
                                    . ' business days';
                            } elseif (
                                $maximumDays !== null
                            ) {
                                $estimate =
                                    'Up to '
                                    . $maximumDays
                                    . ' business days';
                            }
                            ?>

                            <label
                                class="shipping-method-option"
                            >
                                <input
                                    type="radio"
                                    name="shipping_method_id"
                                    value="<?= $escape(
                                        $method['id']
                                    ) ?>"
                                    data-price="<?= $escape(
                                        number_format(
                                            (float) $method[
                                                'price'
                                            ],
                                            2,
                                            '.',
                                            ''
                                        )
                                    ) ?>"
                                    <?= (
                                        (int) $method['id']
                                        ===
                                        $selectedShippingMethodId
                                    )
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <span
                                    class="shipping-method-content"
                                >
                                    <span
                                        class="shipping-method-details"
                                    >
                                        <strong>
                                            <?= $escape(
                                                $method['name']
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            ! empty(
                                                $method[
                                                    'description'
                                                ]
                                            )
                                        ): ?>
                                            <small>
                                                <?= $escape(
                                                    $method[
                                                        'description'
                                                    ]
                                                ) ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if (
                                            $estimate !== ''
                                        ): ?>
                                            <small>
                                                <?= $escape(
                                                    $estimate
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>

                                    <strong
                                        class="shipping-method-price"
                                    >
                                        $<?= number_format(
                                            (float) $method[
                                                'price'
                                            ],
                                            2
                                        ) ?>
                                    </strong>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2>Payment Method</h2>

                <?php if (empty($paymentMethods)): ?>
                    <div
                        class="storefront-alert danger"
                        role="alert"
                    >
                        No active payment method is available.
                    </div>
                <?php else: ?>
                    <div class="payment-method-list">
                        <?php foreach (
                            $paymentMethods as $method
                        ): ?>
                            <label
                                class="payment-method-option"
                            >
                                <input
                                    type="radio"
                                    name="payment_method_id"
                                    value="<?= $escape(
                                        $method['id']
                                    ) ?>"
                                    data-provider="<?= $escape(
                                        $method['provider']
                                    ) ?>"
                                    <?= (
                                        (int) $method['id']
                                        ===
                                        $selectedPaymentMethodId
                                    )
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <span
                                    class="payment-method-content"
                                >
                                    <strong>
                                        <?= $escape(
                                            $method['name']
                                        ) ?>

                                        <?php if (
                                            (int) (
                                                $method[
                                                    'is_test_mode'
                                                ] ?? 0
                                            ) === 1
                                        ): ?>
                                            — Test Mode
                                        <?php endif; ?>
                                    </strong>

                                    <?php if (
                                        ! empty(
                                            $method[
                                                'description'
                                            ]
                                        )
                                    ): ?>
                                        <small>
                                            <?= $escape(
                                                $method[
                                                    'description'
                                                ]
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div
                        id="test-payment-panel"
                        class="test-payment-panel"
                    >
                        <h3>Development Test Scenario</h3>

                        <p>
                            This is a simulated provider. It
                            does not collect or transmit real
                            card information.
                        </p>

                        <div class="form-group">
                            <label for="test_scenario">
                                Test Result
                            </label>

                            <select
                                id="test_scenario"
                                name="test_scenario"
                            >
                                <option
                                    value="approved"
                                    <?= $selectedScenario
                                        === 'approved'
                                            ? 'selected'
                                            : '' ?>
                                >
                                    Approved
                                </option>

                                <option
                                    value="declined"
                                    <?= $selectedScenario
                                        === 'declined'
                                            ? 'selected'
                                            : '' ?>
                                >
                                    Declined
                                </option>

                                <option
                                    value="error"
                                    <?= $selectedScenario
                                        === 'error'
                                            ? 'selected'
                                            : '' ?>
                                >
                                    Provider Error
                                </option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="checkout-security-note">
                    The final tax and total are recalculated
                    on the server. Only an approved payment
                    reduces inventory and completes the order.
                </div>

                <button
                    type="submit"
                    class="storefront-cart-button"
                    <?= (
                        empty($shippingMethods)
                        || empty($paymentMethods)
                    )
                        ? 'disabled'
                        : '' ?>
                >
                    Pay &amp; Place Order
                </button>
            </form>
        </section>

        <aside class="cart-summary">
            <h2>Order Summary</h2>

            <div class="checkout-items">
                <?php foreach (
                    $cartItems as $item
                ): ?>
                    <div class="checkout-item">
                        <span>
                            <?= $escape(
                                $item['name']
                            ) ?>
                            ×
                            <?= $escape(
                                $item['quantity']
                            ) ?>
                        </span>

                        <strong>
                            $<?= number_format(
                                (float) $item[
                                    'line_total'
                                ],
                                2
                            ) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <table>
                <tr>
                    <th>Subtotal</th>

                    <td>
                        $<?= number_format(
                            (float) $subtotal,
                            2
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Shipping</th>

                    <td id="checkout-shipping-total">
                        $0.00
                    </td>
                </tr>

                <tr>
                    <th>Tax</th>

                    <td class="checkout-summary-status">
                        Calculated securely
                    </td>
                </tr>

                <tr class="summary-total">
                    <th>Estimated Before Tax</th>

                    <td id="checkout-estimated-total">
                        $<?= number_format(
                            (float) $subtotal,
                            2
                        ) ?>
                    </td>
                </tr>
            </table>

            <p class="checkout-note">
                The server applies the matching destination
                tax rule and charges the resulting final total.
            </p>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const subtotal = Number(
        <?= json_encode(
            number_format(
                (float) $subtotal,
                2,
                '.',
                ''
            )
        ) ?>
    );

    const shippingOutput = document.getElementById(
        'checkout-shipping-total'
    );

    const estimatedOutput = document.getElementById(
        'checkout-estimated-total'
    );

    const shippingInputs = document.querySelectorAll(
        'input[name="shipping_method_id"]'
    );

    const paymentInputs = document.querySelectorAll(
        'input[name="payment_method_id"]'
    );

    const testPanel = document.getElementById(
        'test-payment-panel'
    );

    const money = (amount) => (
        new Intl.NumberFormat(
            'en-US',
            {
                style: 'currency',
                currency: 'USD'
            }
        ).format(amount)
    );

    const updateShipping = () => {
        const selected = document.querySelector(
            'input[name="shipping_method_id"]:checked'
        );

        const shipping = selected
            ? Number(selected.dataset.price || 0)
            : 0;

        if (shippingOutput) {
            shippingOutput.textContent =
                money(shipping);
        }

        if (estimatedOutput) {
            estimatedOutput.textContent =
                money(subtotal + shipping);
        }
    };

    const updatePaymentPanel = () => {
        if (! testPanel) {
            return;
        }

        const selected = document.querySelector(
            'input[name="payment_method_id"]:checked'
        );

        testPanel.hidden = ! selected
            || selected.dataset.provider !== 'test';
    };

    shippingInputs.forEach((input) => {
        input.addEventListener(
            'change',
            updateShipping
        );
    });

    paymentInputs.forEach((input) => {
        input.addEventListener(
            'change',
            updatePaymentPanel
        );
    });

    updateShipping();
    updatePaymentPanel();
});
</script>
