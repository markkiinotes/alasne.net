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
        <span class="back-link">
            Secure Payment
        </span>

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

            <li class="is-complete">
                <span>2</span>
                Payment
            </li>

            <li
                class="is-current"
                aria-current="step"
            >
                <span>3</span>
                Confirming
            </li>
        </ol>
    </div>
</div>

<main
    id="main-content"
    class="storefront-container storefront-section"
>
    <section
        class="checkout-success-panel order-confirmation-panel"
        aria-live="polite"
    >
        <p class="eyebrow dark-eyebrow">
            Stripe confirmation
        </p>

        <h1 id="stripe-status-title">
            Confirming Your Payment
        </h1>

        <p id="stripe-status-message">
            Stripe has returned control to Alasne. We are waiting
            for the signed Stripe webhook to finalize your order.
        </p>

        <div class="order-confirmation-number">
            <span>Order Number</span>
            <strong>
                <?= $escape(
                    $order['order_number']
                ) ?>
            </strong>
        </div>

        <p>
            Do not submit another order while this payment
            confirmation is in progress.
        </p>

        <div
            id="stripe-status-actions"
            hidden
        >
            <p>
                <a
                    id="stripe-payment-link"
                    href="<?= $escape($paymentUrl) ?>"
                    class="button primary"
                >
                    Return to Payment
                </a>
            </p>

            <p>
                <a
                    id="stripe-checkout-link"
                    href="<?= $escape($checkoutUrl) ?>"
                >
                    Review Checkout
                </a>
            </p>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const statusUrl =
        <?= json_encode($statusUrl) ?>;

    const title = document.getElementById(
        'stripe-status-title'
    );

    const message = document.getElementById(
        'stripe-status-message'
    );

    const actions = document.getElementById(
        'stripe-status-actions'
    );

    let attempts = 0;
    const maximumAttempts = 60;

    const showFailure = (text) => {
        title.textContent =
            'Payment Not Completed';

        message.textContent =
            text
            || 'Stripe did not complete the payment.';

        actions.hidden = false;
    };

    const checkStatus = async () => {
        attempts += 1;

        try {
            const response = await fetch(
                statusUrl,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const result = await response.json();

            if (! response.ok || ! result.ok) {
                throw new Error(
                    result.message
                    || 'Unable to confirm payment status.'
                );
            }

            if (result.state === 'paid') {
                title.textContent =
                    'Payment Confirmed';

                message.textContent =
                    'Your order is complete. Opening your confirmation...';

                window.location.assign(
                    result.redirect_url
                );

                return;
            }

            if (result.state === 'failed') {
                showFailure(
                    result.message
                );

                return;
            }

            if (attempts >= maximumAttempts) {
                title.textContent =
                    'Still Confirming Payment';

                message.textContent =
                    'Stripe confirmation is taking longer than expected. You can keep this page open or refresh it safely.';

                return;
            }

            window.setTimeout(
                checkStatus,
                1500
            );
        } catch (error) {
            if (attempts >= maximumAttempts) {
                title.textContent =
                    'Unable to Confirm Payment';

                message.textContent =
                    error instanceof Error
                        ? error.message
                        : 'Unable to confirm payment status.';

                actions.hidden = false;

                return;
            }

            window.setTimeout(
                checkStatus,
                2000
            );
        }
    };

    checkStatus();
});
</script>
