<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$items = $items ?? [];

$customerName = trim(
    (string) (
        ($order['customer_first_name'] ?? '')
        . ' '
        . ($order['customer_last_name'] ?? '')
    )
);
?>

<style>
.payment-confirmation-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-weight: 800;
    text-transform: capitalize;
}

.confirmation-payment-note {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 14px;
    background: #f0fdf4;
    color: #166534;
    line-height: 1.55;
}
</style>

<section class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a
            href="/store/<?= $escape($store['slug']) ?>"
            class="back-link"
        >
            ← Continue Shopping
        </a>

        <a
            href="/store/<?= $escape($store['slug']) ?>/track"
            class="cart-link"
        >
            Track Order
        </a>
    </div>
</section>

<main class="storefront-container storefront-section">
    <?php if (! empty($success)): ?>
        <div class="storefront-alert success">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <section
        class="checkout-success-panel order-confirmation-panel"
    >
        <p class="eyebrow dark-eyebrow">
            Payment approved
        </p>

        <h1>Thank You</h1>

        <p>
            Your payment was approved and your order
            has been placed successfully.
        </p>

        <div class="order-confirmation-number">
            <span>Order Number</span>

            <strong>
                <?= $escape($order['order_number']) ?>
            </strong>
        </div>

        <div class="confirmation-payment-note">
            <strong>
                <?= $escape(
                    $order['payment_method_name']
                    ?? 'Payment'
                ) ?>
            </strong>
            processed
            $<?= number_format(
                (float) (
                    $order['amount_paid']
                    ?? $order['grand_total']
                    ?? 0
                ),
                2
            ) ?>
            USD.
        </div>
    </section>

    <div class="order-confirmation-grid">
        <section class="checkout-form-panel">
            <h2>Order &amp; Payment</h2>

            <table class="confirmation-table">
                <tr>
                    <th>Order Status</th>

                    <td>
                        <?= $escape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) (
                                        $order['status']
                                        ?? 'paid'
                                    )
                                )
                            )
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Payment</th>

                    <td>
                        <span
                            class="payment-confirmation-badge"
                        >
                            <?= $escape(
                                $order['payment_status']
                                ?? 'paid'
                            ) ?>
                        </span>
                    </td>
                </tr>

                <tr>
                    <th>Method</th>

                    <td>
                        <?= $escape(
                            $order[
                                'payment_method_name'
                            ] ?? '—'
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Transaction</th>

                    <td>
                        #<?= $escape(
                            $order[
                                'payment_transaction_id'
                            ] ?? '—'
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Subtotal</th>

                    <td>
                        $<?= number_format(
                            (float) (
                                $order['subtotal'] ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Tax</th>

                    <td>
                        $<?= number_format(
                            (float) (
                                $order['tax_total'] ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Shipping</th>

                    <td>
                        $<?= number_format(
                            (float) (
                                $order[
                                    'shipping_total'
                                ] ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Total Paid</th>

                    <td>
                        <strong>
                            $<?= number_format(
                                (float) (
                                    $order[
                                        'amount_paid'
                                    ]
                                    ?? $order[
                                        'grand_total'
                                    ]
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </strong>
                    </td>
                </tr>
            </table>
        </section>

        <section class="checkout-form-panel">
            <h2>Shipping Information</h2>

            <table class="confirmation-table">
                <tr>
                    <th>Customer</th>

                    <td>
                        <?= $escape(
                            $customerName ?: '—'
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Email</th>

                    <td>
                        <?= $escape(
                            $order['customer_email']
                            ?? '—'
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Address</th>

                    <td>
                        <?= $escape(
                            $order['address_line_1']
                            ?? ''
                        ) ?>

                        <?php if (! empty(
                            $order['address_line_2']
                        )): ?>
                            <br>
                            <?= $escape(
                                $order[
                                    'address_line_2'
                                ]
                            ) ?>
                        <?php endif; ?>

                        <br>

                        <?= $escape(
                            $order['city'] ?? ''
                        ) ?>,
                        <?= $escape(
                            $order['state'] ?? ''
                        ) ?>
                        <?= $escape(
                            $order['postal_code']
                            ?? ''
                        ) ?>

                        <br>

                        <?= $escape(
                            $order['country'] ?? ''
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>Shipping Method</th>

                    <td>
                        <?= $escape(
                            $order[
                                'shipping_method_name'
                            ] ?? '—'
                        ) ?>
                    </td>
                </tr>
            </table>
        </section>
    </div>

    <section
        class="checkout-form-panel order-items-panel"
    >
        <h2>Order Items</h2>

        <div class="confirmation-items">
            <?php foreach ($items as $item): ?>
                <article class="confirmation-item">
                    <div
                        class="confirmation-item-image"
                    >
                        Item
                    </div>

                    <div>
                        <h3>
                            <?= $escape(
                                $item['product_name']
                                ?? 'Product'
                            ) ?>
                        </h3>

                        <?php if (! empty(
                            $item['product_sku']
                        )): ?>
                            <p>
                                SKU:
                                <?= $escape(
                                    $item['product_sku']
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <p>
                            Quantity:
                            <?= $escape(
                                $item['quantity']
                                ?? 0
                            ) ?>
                        </p>

                        <p>
                            Unit Price:
                            $<?= number_format(
                                (float) (
                                    $item[
                                        'unit_price'
                                    ] ?? 0
                                ),
                                2
                            ) ?>
                        </p>
                    </div>

                    <div
                        class="confirmation-item-pricing"
                    >
                        <strong>
                            $<?= number_format(
                                (float) (
                                    $item[
                                        'line_total'
                                    ] ?? 0
                                ),
                                2
                            ) ?>
                        </strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="confirmation-actions">
            <a
                href="/store/<?= $escape($store['slug']) ?>/track"
                class="storefront-cart-button checkout-link-button"
            >
                Track This Order
            </a>
        </div>
    </section>
</main>
