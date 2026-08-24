<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$label = static fn (mixed $value): string =>
    ucwords(
        str_replace(
            '_',
            ' ',
            trim((string) $value)
        )
    );

$receiptQuery = http_build_query([
    'order_number' => $order['order_number'] ?? '',
    'email' => $customer['email'] ?? '',
]);
?>

<main class="account-page">
    <section class="account-hero account-order-hero">
        <div>
            <p class="eyebrow dark-eyebrow">
                Order detail
            </p>

            <h1><?= $escape($order['order_number']) ?></h1>

            <p>
                Placed
                <?= $escape(
                    $order['placed_at']
                    ?? $order['created_at']
                    ?? '—'
                ) ?>
                at <?= $escape($store['name']) ?>.
            </p>
        </div>

        <div class="account-actions">
            <a
                href="/store/<?= $escape($store['slug']) ?>/account/dashboard"
                class="account-secondary-button"
            >
                Back to Account
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/track"
                class="account-secondary-button"
            >
                Track Order
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/receipt?<?= $escape($receiptQuery) ?>"
                class="account-secondary-button"
                target="_blank"
                rel="noopener noreferrer"
            >
                Print Receipt
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/returns/request"
                class="account-primary-button"
            >
                Request Return
            </a>
        </div>
    </section>

    <section
        class="account-summary"
        aria-label="Order summary"
    >
        <article>
            <span>Order status</span>
            <strong>
                <?= $escape(
                    $label($order['status'] ?? '')
                ) ?>
            </strong>
        </article>

        <article>
            <span>Payment</span>
            <strong>
                <?= $escape(
                    $label($order['payment_status'] ?? '')
                ) ?>
            </strong>
        </article>

        <article>
            <span>Order total</span>
            <strong>
                <?= $money(
                    $order['grand_total'] ?? 0
                ) ?>
            </strong>
        </article>

        <article>
            <span>Tracking</span>
            <strong>
                <?= $escape(
                    $order['tracking_number']
                    ?? 'Not assigned'
                ) ?>
            </strong>
        </article>
    </section>

    <div class="account-content-grid">
        <section class="account-panel">
            <div class="account-panel-heading">
                <div>
                    <p class="eyebrow dark-eyebrow">
                        Fulfillment
                    </p>
                    <h2>Shipping status</h2>
                </div>
            </div>

            <dl class="account-detail-list">
                <div>
                    <dt>Carrier</dt>
                    <dd>
                        <?= $escape(
                            $order['shipping_carrier']
                            ?? 'Not assigned'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Tracking number</dt>
                    <dd>
                        <?= $escape(
                            $order['tracking_number']
                            ?? 'Not assigned'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Shipped</dt>
                    <dd>
                        <?= $escape(
                            $order['shipped_at']
                            ?? 'Not shipped yet'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Tracking link</dt>
                    <dd>
                        <?php if (! empty(
                            $order['tracking_url']
                        )): ?>
                            <a
                                href="<?= $escape(
                                    $order['tracking_url']
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Track shipment
                            </a>
                        <?php else: ?>
                            Not available yet
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="account-panel">
            <div class="account-panel-heading">
                <div>
                    <p class="eyebrow dark-eyebrow">
                        Payment
                    </p>
                    <h2>Order totals</h2>
                </div>
            </div>

            <dl class="account-money-list">
                <div>
                    <dt>Subtotal</dt>
                    <dd>
                        <?= $money(
                            $order['subtotal'] ?? 0
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Shipping</dt>
                    <dd>
                        <?= $money(
                            $order['shipping_total'] ?? 0
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Tax</dt>
                    <dd>
                        <?= $money(
                            $order['tax_total'] ?? 0
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Paid</dt>
                    <dd>
                        <?= $money(
                            $order['amount_paid'] ?? 0
                        ) ?>
                    </dd>
                </div>

                <?php if (
                    (float) (
                        $order['amount_refunded']
                        ?? 0
                    ) > 0
                ): ?>
                    <div>
                        <dt>Refunded</dt>
                        <dd>
                            −<?= $money(
                                $order[
                                    'amount_refunded'
                                ] ?? 0
                            ) ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <div class="total">
                    <dt>Order total</dt>
                    <dd>
                        <?= $money(
                            $order['grand_total'] ?? 0
                        ) ?>
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="account-panel">
        <div class="account-panel-heading">
            <div>
                <p class="eyebrow dark-eyebrow">
                    Items
                </p>
                <h2>What you ordered</h2>
            </div>
        </div>

        <div class="account-mobile-scroll">
            <table class="account-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= $escape(
                                        $item[
                                            'product_name'
                                        ] ?? 'Product'
                                    ) ?>
                                </strong>
                            </td>
                            <td>
                                <?= $escape(
                                    $item[
                                        'product_sku'
                                    ] ?? '—'
                                ) ?>
                            </td>
                            <td>
                                <?= $escape(
                                    $item['quantity'] ?? 0
                                ) ?>
                            </td>
                            <td>
                                <?= $money(
                                    $item[
                                        'unit_price'
                                    ] ?? 0
                                ) ?>
                            </td>
                            <td>
                                <strong>
                                    <?= $money(
                                        $item[
                                            'line_total'
                                        ] ?? 0
                                    ) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5">
                                No order items found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="account-panel">
        <div class="account-panel-heading">
            <div>
                <p class="eyebrow dark-eyebrow">
                    Packages
                </p>
                <h2>Shipment tracking</h2>
            </div>

            <small>
                Supplier identities and internal fulfillment
                details are not exposed here.
            </small>
        </div>

        <?php if (! empty($purchase_orders)): ?>
            <div class="account-shipment-grid">
                <?php foreach (
                    $purchase_orders as $index => $shipment
                ): ?>
                    <article class="account-shipment-card">
                        <div class="account-shipment-heading">
                            <div>
                                <span>Shipment</span>
                                <strong>
                                    <?= $escape(
                                        $index + 1
                                    ) ?>
                                </strong>
                            </div>

                            <span class="account-badge">
                                <?= $escape(
                                    $label(
                                        $shipment[
                                            'tracking_status'
                                        ]
                                        ?? $shipment['status']
                                        ?? 'Preparing'
                                    )
                                ) ?>
                            </span>
                        </div>

                        <dl>
                            <div>
                                <dt>Carrier</dt>
                                <dd>
                                    <?= $escape(
                                        $shipment[
                                            'shipping_carrier'
                                        ] ?? 'Not assigned'
                                    ) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Tracking</dt>
                                <dd>
                                    <?php if (! empty(
                                        $shipment[
                                            'tracking_url'
                                        ]
                                    )): ?>
                                        <a
                                            href="<?= $escape(
                                                $shipment[
                                                    'tracking_url'
                                                ]
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <?= $escape(
                                                $shipment[
                                                    'tracking_number'
                                                ]
                                                ?? 'Track package'
                                            ) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $escape(
                                            $shipment[
                                                'tracking_number'
                                            ] ?? 'Not assigned'
                                        ) ?>
                                    <?php endif; ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Expected ship</dt>
                                <dd>
                                    <?= $escape(
                                        $shipment[
                                            'expected_ship_at'
                                        ] ?? '—'
                                    ) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Delivered</dt>
                                <dd>
                                    <?= $escape(
                                        $shipment[
                                            'delivered_at'
                                        ] ?? '—'
                                    ) ?>
                                </dd>
                            </div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="account-empty-state">
                <strong>No package tracking yet.</strong>
                <p>
                    Shipment details will appear here after the
                    order is routed and fulfillment information
                    becomes available.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <section class="account-panel">
        <div class="account-panel-heading">
            <div>
                <p class="eyebrow dark-eyebrow">
                    Updates
                </p>
                <h2>Order timeline</h2>
            </div>
        </div>

        <div class="account-timeline">
            <?php foreach ($events as $event): ?>
                <article class="account-timeline-event">
                    <span
                        class="account-timeline-dot"
                        aria-hidden="true"
                    ></span>

                    <div>
                        <strong>
                            <?= $escape(
                                $event['title']
                                ?? $label(
                                    $event['type']
                                    ?? 'Update'
                                )
                            ) ?>
                        </strong>

                        <?php if (! empty(
                            $event['description']
                        )): ?>
                            <p>
                                <?= $escape(
                                    $event[
                                        'description'
                                    ]
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <small>
                            <?= $escape(
                                $event['created_at']
                                ?? ''
                            ) ?>
                        </small>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (empty($events)): ?>
                <div class="account-empty-state">
                    No customer-visible order updates have
                    been posted yet.
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
