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
$storeCredit = $storeCredit ?? [
    'verified' => false,
    'available_balance' => 0.0,
    'currency' => 'USD',
];

$availableStoreCredit = round(
    (float) (
        $storeCredit['available_balance'] ?? 0
    ),
    2
);

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

.store-credit-panel {
    margin: 24px 0;
    padding: 18px;
    border: 1px solid #bbf7d0;
    border-radius: 14px;
    background: #f0fdf4;
}

.store-credit-panel h2 {
    margin-top: 0;
}

.store-credit-controls {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.store-credit-balance {
    margin: 12px 0;
    color: #166534;
    font-weight: 800;
}

.store-credit-help {
    color: #475569;
    font-size: 13px;
    line-height: 1.5;
}

@media (max-width: 640px) {
    .store-credit-controls {
        grid-template-columns: 1fr;
    }
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


                <section class="store-credit-panel">
                    <h2>Store Credit</h2>

                    <p class="store-credit-help">
                        Enter the email address and postal
                        code used on your prior order, then
                        verify your available balance.
                    </p>

                    <div class="store-credit-controls">
                        <div class="form-group">
                            <label for="store_credit_amount">
                                Amount to Apply
                            </label>

                            <input
                                id="store_credit_amount"
                                type="number"
                                name="store_credit_amount"
                                min="0"
                                step="0.01"
                                value="<?= $escape(
                                    number_format(
                                        (float) (
                                            $old[
                                                'store_credit_amount'
                                            ] ?? 0
                                        ),
                                        2,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                                <?= $availableStoreCredit > 0
                                    ? ''
                                    : 'disabled' ?>
                            >
                        </div>

                        <button
                            type="button"
                            id="check-store-credit"
                            class="storefront-cart-button"
                        >
                            Check Balance
                        </button>
                    </div>

                    <label
                        class="payment-method-option"
                        style="margin-top:12px;"
                    >
                        <input
                            id="apply_store_credit"
                            type="checkbox"
                            name="apply_store_credit"
                            value="1"
                            <?= ! empty(
                                $old['apply_store_credit']
                            )
                                ? 'checked'
                                : '' ?>
                            <?= $availableStoreCredit > 0
                                ? ''
                                : 'disabled' ?>
                        >

                        <span class="payment-method-content">
                            <strong>Apply Store Credit</strong>

                            <small>
                                The server will cap the amount
                                at the available balance and
                                final order total.
                            </small>
                        </span>
                    </label>

                    <div
                        id="store-credit-balance"
                        class="store-credit-balance"
                        data-available="<?= $escape(
                            number_format(
                                $availableStoreCredit,
                                2,
                                '.',
                                ''
                            )
                        ) ?>"
                    >
                        <?php if (
                            ! empty($storeCredit['verified'])
                        ): ?>
                            Available:
                            $<?= number_format(
                                $availableStoreCredit,
                                2
                            ) ?>
                            USD
                        <?php else: ?>
                            Balance not verified.
                        <?php endif; ?>
                    </div>
                </section>

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
                    The final tax, store credit, and payment
                    amount are recalculated on the server.
                    Credit is reserved during checkout and is
                    released automatically if payment fails.
                </div>

                <button
                    type="submit"
                    class="storefront-cart-button"
                    <?= (
                        empty($shippingMethods)
                    )
                        ? 'disabled'
                        : '' ?>
                >
                    Place Paid Order
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

                <tr>
                    <th>Store Credit</th>

                    <td id="checkout-store-credit">
                        $0.00
                    </td>
                </tr>

                <tr>
                    <th>Payment Due</th>

                    <td id="checkout-payment-due">
                        $<?= number_format(
                            (float) $subtotal,
                            2
                        ) ?>
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

    const storeCreditOutput = document.getElementById(
        'checkout-store-credit'
    );

    const paymentDueOutput = document.getElementById(
        'checkout-payment-due'
    );

    const storeCreditBalance = document.getElementById(
        'store-credit-balance'
    );

    const storeCreditAmount = document.getElementById(
        'store_credit_amount'
    );

    const applyStoreCredit = document.getElementById(
        'apply_store_credit'
    );

    const checkStoreCredit = document.getElementById(
        'check-store-credit'
    );

    const emailInput = document.getElementById('email');
    const postalInput = document.getElementById(
        'postal_code'
    );

    let availableStoreCredit = Number(
        storeCreditBalance?.dataset.available || 0
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

    const updateSettlement = () => {
        const selected = document.querySelector(
            'input[name="shipping_method_id"]:checked'
        );

        const shipping = selected
            ? Number(selected.dataset.price || 0)
            : 0;

        const estimatedTotal =
            subtotal + shipping;

        const requestedCredit =
            applyStoreCredit?.checked
                ? Math.max(
                    0,
                    Number(
                        storeCreditAmount?.value || 0
                    )
                )
                : 0;

        const appliedCredit = Math.min(
            requestedCredit,
            availableStoreCredit,
            estimatedTotal
        );

        if (shippingOutput) {
            shippingOutput.textContent =
                money(shipping);
        }

        if (estimatedOutput) {
            estimatedOutput.textContent =
                money(estimatedTotal);
        }

        if (storeCreditOutput) {
            storeCreditOutput.textContent =
                money(appliedCredit);
        }

        if (paymentDueOutput) {
            paymentDueOutput.textContent =
                money(
                    Math.max(
                        0,
                        estimatedTotal - appliedCredit
                    )
                );
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
            updateSettlement
        );
    });

    paymentInputs.forEach((input) => {
        input.addEventListener(
            'change',
            updatePaymentPanel
        );
    });


    storeCreditAmount?.addEventListener(
        'input',
        updateSettlement
    );

    applyStoreCredit?.addEventListener(
        'change',
        () => {
            if (
                applyStoreCredit.checked
                && storeCreditAmount
                && Number(
                    storeCreditAmount.value || 0
                ) <= 0
            ) {
                const selectedShipping =
                    document.querySelector(
                        'input[name="shipping_method_id"]:checked'
                    );

                const shipping = selectedShipping
                    ? Number(
                        selectedShipping.dataset.price
                        || 0
                    )
                    : 0;

                storeCreditAmount.value =
                    Math.min(
                        availableStoreCredit,
                        subtotal + shipping
                    ).toFixed(2);
            }

            updateSettlement();
        }
    );

    checkStoreCredit?.addEventListener(
        'click',
        async () => {
            const email = emailInput?.value.trim() || '';
            const postalCode =
                postalInput?.value.trim() || '';

            if (! email || ! postalCode) {
                storeCreditBalance.textContent =
                    'Enter your email and postal code first.';
                return;
            }

            checkStoreCredit.disabled = true;
            storeCreditBalance.textContent =
                'Checking store credit…';

            try {
                const body = new URLSearchParams({
                    _csrf_token:
                        <?= json_encode($csrf_token) ?>,
                    email,
                    postal_code: postalCode
                });

                const response = await fetch(
                    '/store/'
                    + <?= json_encode(
                        (string) $store['slug']
                    ) ?>
                    + '/checkout/store-credit',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    }
                );

                const result = await response.json();

                if (! response.ok || ! result.ok) {
                    throw new Error(
                        result.message
                        || 'Unable to check store credit.'
                    );
                }

                availableStoreCredit = Number(
                    result.available_balance || 0
                );

                storeCreditBalance.dataset.available =
                    String(availableStoreCredit);

                storeCreditBalance.textContent =
                    result.verified
                        ? 'Available: '
                            + money(
                                availableStoreCredit
                            )
                            + ' USD'
                        : result.message;

                if (storeCreditAmount) {
                    storeCreditAmount.disabled =
                        availableStoreCredit <= 0;
                    storeCreditAmount.max =
                        String(availableStoreCredit);

                    const currentAmount = Number(
                        storeCreditAmount.value || 0
                    );

                    if (
                        currentAmount <= 0
                        && availableStoreCredit > 0
                    ) {
                        const selectedShipping =
                            document.querySelector(
                                'input[name="shipping_method_id"]:checked'
                            );

                        const shipping = selectedShipping
                            ? Number(
                                selectedShipping.dataset.price
                                || 0
                            )
                            : 0;

                        storeCreditAmount.value =
                            Math.min(
                                availableStoreCredit,
                                subtotal + shipping
                            ).toFixed(2);
                    } else if (
                        currentAmount
                        > availableStoreCredit
                    ) {
                        storeCreditAmount.value =
                            availableStoreCredit.toFixed(2);
                    }
                }

                if (applyStoreCredit) {
                    applyStoreCredit.disabled =
                        availableStoreCredit <= 0;

                    if (availableStoreCredit <= 0) {
                        applyStoreCredit.checked = false;
                    }
                }

                updateSettlement();
            } catch (error) {
                storeCreditBalance.textContent =
                    error.message
                    || 'Unable to check store credit.';
            } finally {
                checkStoreCredit.disabled = false;
            }
        }
    );

    updateSettlement();
    updatePaymentPanel();
});
</script>
