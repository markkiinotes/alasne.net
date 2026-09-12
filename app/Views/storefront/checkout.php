<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$old = $old ?? [];
$cartItems = $cartItems ?? [];
$shippingMethods = $shippingMethods ?? [];
$paymentMethods = $paymentMethods ?? [];
$quote = is_array($quote ?? null)
    ? $quote
    : null;

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

$selectedShippingPrice = 0.0;

foreach ($shippingMethods as $method) {
    if (
        (int) ($method['id'] ?? 0)
        === $selectedShippingMethodId
    ) {
        $selectedShippingPrice = round(
            (float) ($method['price'] ?? 0),
            2
        );

        break;
    }
}

$quoteReady = $quote !== null
    && ! empty($quote['fingerprint']);

$displayShipping = $quoteReady
    ? (float) ($quote['shipping_total'] ?? 0)
    : $selectedShippingPrice;

$displayTax = $quoteReady
    ? (float) ($quote['tax_total'] ?? 0)
    : null;

$displayGrandTotal = $quoteReady
    ? (float) ($quote['grand_total'] ?? 0)
    : round(
        (float) $subtotal + $displayShipping,
        2
    );

$taxRate = $quoteReady
    ? (float) ($quote['tax_rate'] ?? 0)
    : 0.0;
?>

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

<div class="checkout-progress-wrap">
    <div class="storefront-container">
        <ol
            class="checkout-progress"
            aria-label="Checkout progress"
        >
            <li class="is-complete">
                <span>1</span>
                Cart
            </li>

            <li
                class="is-current"
                aria-current="step"
            >
                <span>2</span>
                Checkout
            </li>

            <li>
                <span>3</span>
                Complete
            </li>
        </ol>
    </div>
</div>

<main class="storefront-container storefront-section">
    <div class="section-header checkout-page-header">
        <p class="eyebrow dark-eyebrow">
            Secure checkout
        </p>

        <h1>Complete Your Order</h1>

        <p>
            Review shipping, tax, and the final total before
            payment. The server verifies the amounts again when
            the order is placed.
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
                id="checkout-form"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <div class="checkout-section-title">
                    <span class="checkout-section-number">1</span>

                    <div>
                        <h2>Contact Information</h2>
                        <p>
                            We’ll use this information for your
                            order confirmation and updates.
                        </p>
                    </div>
                </div>

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

                <div class="checkout-section-title">
                    <span class="checkout-section-number">2</span>

                    <div>
                        <h2>Shipping Address</h2>
                        <p>
                            Enter the destination where this
                            order should be delivered.
                        </p>
                    </div>
                </div>

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

                <div class="checkout-section-title">
                    <span class="checkout-section-number">3</span>

                    <div>
                        <h2>Shipping Method</h2>
                        <p>
                            Choose the available delivery option
                            that works best for this order.
                        </p>
                    </div>
                </div>

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

                <div class="checkout-section-title">
                    <span class="checkout-section-number">4</span>

                    <div>
                        <h2>Payment Method</h2>
                        <p>
                            Select an available payment method.
                            Development providers stay clearly
                            labeled as test mode.
                        </p>
                    </div>
                </div>

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
                    <strong>Server-verified checkout</strong>

                    <span>
                        Tax is calculated from the shipping
                        destination before payment. The server
                        recalculates the final amount again when
                        the order is placed.
                    </span>
                </div>

                <p
                    id="checkout-submit-note"
                    class="checkout-submit-note"
                >
                    Review the tax and final total before payment.
                    If the destination or shipping method changes,
                    the total must be reviewed again.
                </p>

                <button
                    type="submit"
                    name="checkout_action"
                    value="review"
                    id="checkout-review-button"
                    class="storefront-cart-button"
                    aria-describedby="checkout-submit-note"
                    <?= (
                        empty($shippingMethods)
                        || empty($paymentMethods)
                    )
                        ? 'disabled'
                        : '' ?>
                    <?= $quoteReady ? 'hidden' : '' ?>
                >
                    Review Tax &amp; Final Total
                </button>

                <button
                    type="submit"
                    name="checkout_action"
                    value="pay"
                    id="checkout-pay-button"
                    class="storefront-cart-button"
                    aria-describedby="checkout-submit-note"
                    <?= $quoteReady ? '' : 'hidden disabled' ?>
                >
                    Pay &amp; Place Order
                </button>
            </form>
        </section>

        <aside
            class="cart-summary checkout-summary-panel"
            aria-label="Order summary"
        >
            <div class="checkout-summary-heading">
                <div>
                    <span>Review</span>
                    <h2>Order Summary</h2>
                </div>

                <a
                    href="/store/<?= $escape($store['slug']) ?>/cart"
                >
                    Edit Cart
                </a>
            </div>

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
                        $<?= number_format(
                            $displayShipping,
                            2
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th id="checkout-tax-label">
                        <?php if (
                            $quoteReady
                            && $taxRate > 0
                        ): ?>
                            Tax
                            (<?= $escape(
                                rtrim(
                                    rtrim(
                                        number_format(
                                            $taxRate,
                                            5,
                                            '.',
                                            ''
                                        ),
                                        '0'
                                    ),
                                    '.'
                                )
                            ) ?>%)
                        <?php else: ?>
                            Tax
                        <?php endif; ?>
                    </th>

                    <td
                        id="checkout-tax-total"
                        class="checkout-summary-status"
                    >
                        <?php if ($quoteReady): ?>
                            $<?= number_format(
                                (float) $displayTax,
                                2
                            ) ?>
                        <?php else: ?>
                            Review required
                        <?php endif; ?>
                    </td>
                </tr>

                <tr class="summary-total">
                    <th id="checkout-total-label">
                        <?= $quoteReady
                            ? 'Total Due'
                            : 'Estimated Before Tax' ?>
                    </th>

                    <td id="checkout-estimated-total">
                        $<?= number_format(
                            $displayGrandTotal,
                            2
                        ) ?>
                    </td>
                </tr>
            </table>

            <p
                class="checkout-note"
                id="checkout-tax-note"
            >
                <?php if ($quoteReady): ?>
                    Tax and final total were calculated on the
                    server for the entered destination. Review
                    them before selecting Pay &amp; Place Order.
                <?php else: ?>
                    Enter the shipping destination, choose a
                    shipping method, then select Review Tax &amp;
                    Final Total before payment.
                <?php endif; ?>
            </p>

            <ul class="checkout-trust-list">
                <li>Tax is shown before payment.</li>
                <li>The final total is verified again on the server.</li>
                <li>Inventory changes only after payment approval.</li>
                <li>Declined test payments keep the cart available.</li>
            </ul>
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

    let quoteCurrent = <?= $quoteReady
        ? 'true'
        : 'false' ?>;

    const quotedShipping = Number(
        <?= json_encode(
            number_format(
                $quoteReady
                    ? (float) (
                        $quote['shipping_total'] ?? 0
                    )
                    : 0,
                2,
                '.',
                ''
            )
        ) ?>
    );

    const quotedTax = Number(
        <?= json_encode(
            number_format(
                $quoteReady
                    ? (float) (
                        $quote['tax_total'] ?? 0
                    )
                    : 0,
                2,
                '.',
                ''
            )
        ) ?>
    );

    const quotedGrandTotal = Number(
        <?= json_encode(
            number_format(
                $quoteReady
                    ? (float) (
                        $quote['grand_total'] ?? 0
                    )
                    : 0,
                2,
                '.',
                ''
            )
        ) ?>
    );

    const shippingOutput = document.getElementById(
        'checkout-shipping-total'
    );

    const taxOutput = document.getElementById(
        'checkout-tax-total'
    );

    const taxLabel = document.getElementById(
        'checkout-tax-label'
    );

    const totalLabel = document.getElementById(
        'checkout-total-label'
    );

    const estimatedOutput = document.getElementById(
        'checkout-estimated-total'
    );

    const taxNote = document.getElementById(
        'checkout-tax-note'
    );

    const reviewButton = document.getElementById(
        'checkout-review-button'
    );

    const payButton = document.getElementById(
        'checkout-pay-button'
    );

    const shippingInputs = document.querySelectorAll(
        'input[name="shipping_method_id"]'
    );

    const paymentInputs = document.querySelectorAll(
        'input[name="payment_method_id"]'
    );

    const taxAddressInputs = [
        document.getElementById('state'),
        document.getElementById('postal_code'),
        document.getElementById('country')
    ].filter(Boolean);

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

    const selectedShipping = () => {
        const selected = document.querySelector(
            'input[name="shipping_method_id"]:checked'
        );

        return selected
            ? Number(selected.dataset.price || 0)
            : 0;
    };

    const showUnreviewedTotal = () => {
        const shipping = selectedShipping();

        if (shippingOutput) {
            shippingOutput.textContent =
                money(shipping);
        }

        if (taxOutput) {
            taxOutput.textContent =
                'Review required';
        }

        if (taxLabel) {
            taxLabel.textContent = 'Tax';
        }

        if (totalLabel) {
            totalLabel.textContent =
                'Estimated Before Tax';
        }

        if (estimatedOutput) {
            estimatedOutput.textContent =
                money(subtotal + shipping);
        }

        if (taxNote) {
            taxNote.textContent =
                'Shipping or destination information changed. '
                + 'Review tax and the final total again before payment.';
        }

        if (reviewButton) {
            reviewButton.hidden = false;
            reviewButton.disabled =
                <?= (
                    empty($shippingMethods)
                    || empty($paymentMethods)
                )
                    ? 'true'
                    : 'false' ?>;
        }

        if (payButton) {
            payButton.disabled = true;
            payButton.hidden = true;
        }
    };

    const markQuoteStale = () => {
        if (quoteCurrent) {
            quoteCurrent = false;
        }

        showUnreviewedTotal();
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
            markQuoteStale
        );
    });

    taxAddressInputs.forEach((input) => {
        input.addEventListener(
            'input',
            markQuoteStale
        );

        input.addEventListener(
            'change',
            markQuoteStale
        );
    });

    paymentInputs.forEach((input) => {
        input.addEventListener(
            'change',
            updatePaymentPanel
        );
    });

    if (quoteCurrent) {
        if (reviewButton) {
            reviewButton.hidden = true;
        }

        if (payButton) {
            payButton.hidden = false;
            payButton.disabled = false;
        }

        if (shippingOutput) {
            shippingOutput.textContent =
                money(quotedShipping);
        }

        if (taxOutput) {
            taxOutput.textContent =
                money(quotedTax);
        }

        if (totalLabel) {
            totalLabel.textContent = 'Total Due';
        }

        if (estimatedOutput) {
            estimatedOutput.textContent =
                money(quotedGrandTotal);
        }
    } else {
        showUnreviewedTotal();
    }

    updatePaymentPanel();
});
</script>
