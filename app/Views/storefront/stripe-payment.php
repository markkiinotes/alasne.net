<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};
?>

<section class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a
            href="/store/<?= $escape($store['slug']) ?>/checkout"
            class="back-link"
        >
            ← Back to Checkout
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
                Payment
            </li>

            <li>
                <span>3</span>
                Complete
            </li>
        </ol>
    </div>
</div>

<main
    id="main-content"
    class="storefront-container storefront-section"
>
    <div class="section-header checkout-page-header">
        <p class="eyebrow dark-eyebrow">
            Secure Stripe payment
        </p>

        <h1>Complete Payment</h1>

        <p>
            Your order is prepared. Payment details are collected
            securely by Stripe and are never stored by Alasne.
        </p>
    </div>

    <div class="checkout-layout">
        <section class="checkout-form-panel">
            <form id="stripe-payment-form">
                <div
                    id="stripe-payment-element"
                    aria-label="Stripe payment details"
                ></div>

                <div
                    id="stripe-payment-message"
                    class="storefront-alert danger"
                    role="alert"
                    hidden
                ></div>

                <button
                    id="stripe-submit-button"
                    type="submit"
                    class="button primary"
                >
                    Pay
                    $<?= number_format(
                        (float) (
                            $order[
                                'external_payment_amount'
                            ]
                            ?? $order['grand_total']
                            ?? 0
                        ),
                        2
                    ) ?>
                </button>
            </form>
        </section>

        <aside
            class="checkout-summary-panel"
            aria-label="Payment summary"
        >
            <div class="checkout-summary-heading">
                <div>
                    <span>Order</span>
                    <h2>
                        <?= $escape(
                            $order['order_number']
                        ) ?>
                    </h2>
                </div>
            </div>

            <table>
                <tr>
                    <th>Payment Method</th>
                    <td>
                        <?= $escape(
                            $order[
                                'payment_method_name'
                            ]
                            ?? 'Stripe'
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Order Total</th>
                    <td>
                        $<?= number_format(
                            (float) (
                                $order['grand_total']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>

                <?php
                $reservedCredit = (float) (
                    $order[
                        'store_credit_reserved_amount'
                    ] ?? 0
                );
                ?>

                <?php if ($reservedCredit > 0): ?>
                    <tr>
                        <th>Store Credit Reserved</th>
                        <td>
                            -$<?= number_format(
                                $reservedCredit,
                                2
                            ) ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr class="summary-total">
                    <th>Stripe Payment Due</th>
                    <td>
                        $<?= number_format(
                            (float) (
                                $order[
                                    'external_payment_amount'
                                ]
                                ?? $order['grand_total']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>
            </table>

            <ul class="checkout-trust-list">
                <li>
                    Stripe securely handles payment details.
                </li>
                <li>
                    Alasne never receives card numbers or CVC.
                </li>
                <li>
                    Inventory changes only after Stripe confirms payment.
                </li>
            </ul>
        </aside>
    </div>
</main>

<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const publishableKey =
        <?= json_encode($publishableKey) ?>;

    const clientSecret =
        <?= json_encode($clientSecret) ?>;

    const processingUrl =
        <?= json_encode($processingUrl) ?>;

    const returnPath =
        <?= json_encode($returnUrl) ?>;

    const message = document.getElementById(
        'stripe-payment-message'
    );

    const button = document.getElementById(
        'stripe-submit-button'
    );

    const form = document.getElementById(
        'stripe-payment-form'
    );

    const showMessage = (text) => {
        if (! message) {
            return;
        }

        message.textContent =
            text || 'Unable to complete payment.';

        message.hidden = false;
    };

    if (
        typeof Stripe !== 'function'
        || ! publishableKey
        || ! clientSecret
    ) {
        showMessage(
            'Stripe could not be initialized. Refresh and try again.'
        );

        if (button) {
            button.disabled = true;
        }

        return;
    }

    const stripe = Stripe(publishableKey);

    const elements = stripe.elements({
        clientSecret: clientSecret
    });

    const paymentElement = elements.create(
        'payment'
    );

    paymentElement.mount(
        '#stripe-payment-element'
    );

    form.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            message.hidden = true;
            button.disabled = true;
            button.textContent =
                'Processing payment...';

            const returnUrl =
                window.location.origin
                + returnPath;

            const result =
                await stripe.confirmPayment({
                    elements: elements,
                    confirmParams: {
                        return_url: returnUrl
                    },
                    redirect: 'if_required'
                });

            if (result.error) {
                showMessage(
                    result.error.message
                    || 'Stripe could not complete the payment.'
                );

                button.disabled = false;
                button.textContent =
                    'Try Payment Again';

                return;
            }

            window.location.assign(
                processingUrl
            );
        }
    );
});
</script>
