<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$label = static function (mixed $value): string {
    return ucwords(
        str_replace(
            '_',
            ' ',
            trim((string) $value)
        )
    );
};

$shippingAddress = $shippingAddress ?? null;
$paymentTransactions =
    $paymentTransactions ?? [];
$latestSuccessfulCharge =
    $latestSuccessfulCharge ?? null;
$remainingRefundable = round(
    max(
        0,
        (float) ($remainingRefundable ?? 0)
    ),
    2
);

$orderStatus = (string) (
    $order['status'] ?? 'pending'
);

$paymentStatus = (string) (
    $order['payment_status'] ?? 'unpaid'
);

$paymentMethodName = trim(
    (string) (
        $order['payment_method_name'] ?? ''
    )
);

$paymentProvider = trim(
    (string) (
        $order['payment_provider'] ?? ''
    )
);

$currency = strtoupper(
    (string) (
        $order['currency'] ?? 'USD'
    )
);

$amountPaid = round(
    (float) ($order['amount_paid'] ?? 0),
    2
);

$amountRefunded = round(
    (float) (
        $order['amount_refunded'] ?? 0
    ),
    2
);

$shippingMethodName = trim(
    (string) (
        $order['shipping_method_name'] ?? ''
    )
);

$shippingMethodCode = trim(
    (string) (
        $order['shipping_method_code'] ?? ''
    )
);

$estimatedDaysMin = isset(
    $order['shipping_estimated_days_min']
)
    && $order['shipping_estimated_days_min'] !== ''
        ? (int) $order[
            'shipping_estimated_days_min'
        ]
        : null;

$estimatedDaysMax = isset(
    $order['shipping_estimated_days_max']
)
    && $order['shipping_estimated_days_max'] !== ''
        ? (int) $order[
            'shipping_estimated_days_max'
        ]
        : null;

$deliveryEstimate = 'Not recorded';

if (
    $estimatedDaysMin !== null
    && $estimatedDaysMax !== null
) {
    $deliveryEstimate =
        $estimatedDaysMin === $estimatedDaysMax
            ? $estimatedDaysMin
                . ' business days'
            : $estimatedDaysMin
                . '–'
                . $estimatedDaysMax
                . ' business days';
} elseif ($estimatedDaysMin !== null) {
    $deliveryEstimate =
        $estimatedDaysMin . ' business days';
} elseif ($estimatedDaysMax !== null) {
    $deliveryEstimate =
        'Up to '
        . $estimatedDaysMax
        . ' business days';
}

$shippingCharge = round(
    (float) ($order['shipping_total'] ?? 0),
    2
);

$orderTotal = round(
    (float) (
        $order['grand_total']
        ?? $order['total']
        ?? 0
    ),
    2
);

$canRefund =
    $latestSuccessfulCharge !== null
    && $remainingRefundable > 0
    && in_array(
        $paymentStatus,
        ['paid', 'partially_refunded'],
        true
    );

$chargeProvider = strtolower(
    (string) (
        $latestSuccessfulCharge['provider']
        ?? $paymentProvider
    )
);

$statusClass = static function (
    string $status
): string {
    return match ($status) {
        'paid',
        'succeeded',
        'shipped',
        'refunded' =>
            'payment-status-success',

        'partially_refunded',
        'processing',
        'pending' =>
            'payment-status-warning',

        'failed',
        'cancelled' =>
            'payment-status-danger',

        default =>
            'payment-status-neutral',
    };
};
?>

<style>
.payment-admin-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.payment-admin-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 22px;
    box-shadow:
        0 10px 26px rgba(15, 23, 42, 0.06);
}

.payment-admin-panel h2 {
    margin: 0 0 18px;
}

.payment-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 0 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    text-transform: capitalize;
}

.payment-status-success {
    background: #dcfce7;
    color: #166534;
}

.payment-status-warning {
    background: #fef3c7;
    color: #92400e;
}

.payment-status-danger {
    background: #fee2e2;
    color: #991b1b;
}

.payment-status-neutral {
    background: #e2e8f0;
    color: #475569;
}

.payment-money {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.payment-refund-panel {
    border-color: #bfdbfe;
    background: #eff6ff;
}

.payment-refund-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.payment-refund-note {
    margin: 14px 0 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.55;
}

.payment-transaction-wrap {
    overflow-x: auto;
}

.payment-transaction-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-transaction-table th,
.payment-transaction-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

.payment-transaction-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.payment-transaction-table tr:last-child td {
    border-bottom: 0;
}

.payment-transaction-id {
    display: inline-flex;
    padding: 4px 7px;
    border-radius: 8px;
    background: #f1f5f9;
    color: #334155;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.payment-failure-text {
    max-width: 300px;
    color: #991b1b;
    line-height: 1.45;
}

.payment-admin-muted {
    color: #64748b;
}

.order-management-columns {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 20px;
    align-items: start;
}

.order-management-columns > .panel {
    margin: 0;
}

@media (max-width: 900px) {
    .payment-admin-grid,
    .payment-refund-grid,
    .order-management-columns {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="page-header">
    <h1>
        <?= $escape($order['order_number']) ?>
    </h1>

    <p>
        Order, payment, customer, shipping,
        fulfillment, and activity details.
    </p>

    <div class="table-actions">
        <a
            href="/admin/orders/<?= $escape(
                $order['id']
            ) ?>/packing-slip"
            class="button-primary"
            target="_blank"
        >
            Print Packing Slip
        </a>

        <a
            href="/admin/orders/<?= $escape(
                $order['id']
            ) ?>/invoice"
            class="button-muted"
            target="_blank"
        >
            Print Invoice
        </a>

        <a
            href="/admin/orders"
            class="button-muted"
        >
            Back to Orders
        </a>
    </div>
</section>

<?php if (! empty($success)): ?>
    <div class="alert-success" role="alert">
        <?= $escape($success) ?>
    </div>
<?php endif; ?>

<?php if (! empty($error)): ?>
    <div class="alert-danger" role="alert">
        <?= $escape($error) ?>
    </div>
<?php endif; ?>

<div class="payment-admin-grid">
    <section class="payment-admin-panel">
        <h2>Order Summary</h2>

        <table class="detail-table">
            <tr>
                <th>Order Status</th>

                <td>
                    <span
                        class="payment-status-badge <?= $escape(
                            $statusClass($orderStatus)
                        ) ?>"
                    >
                        <?= $escape(
                            $label($orderStatus)
                        ) ?>
                    </span>
                </td>
            </tr>

            <tr>
                <th>Store</th>

                <td>
                    <?= $escape(
                        $order['store_name'] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Placed</th>

                <td>
                    <?= $escape(
                        $order['placed_at']
                        ?? $order['created_at']
                        ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Grand Total</th>

                <td class="payment-money">
                    <strong>
                        $<?= number_format(
                            $orderTotal,
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </strong>
                </td>
            </tr>
        </table>
    </section>

    <section class="payment-admin-panel">
        <h2>Payment Summary</h2>

        <table class="detail-table">
            <tr>
                <th>Payment Status</th>

                <td>
                    <span
                        class="payment-status-badge <?= $escape(
                            $statusClass(
                                $paymentStatus
                            )
                        ) ?>"
                    >
                        <?= $escape(
                            $label($paymentStatus)
                        ) ?>
                    </span>
                </td>
            </tr>

            <tr>
                <th>Method</th>

                <td>
                    <?= $escape(
                        $paymentMethodName ?: '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Provider</th>

                <td>
                    <?= $escape(
                        $paymentProvider ?: '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Amount Paid</th>

                <td class="payment-money">
                    $<?= number_format(
                        $amountPaid,
                        2
                    ) ?>
                    <?= $escape($currency) ?>
                </td>
            </tr>

            <tr>
                <th>Amount Refunded</th>

                <td class="payment-money">
                    $<?= number_format(
                        $amountRefunded,
                        2
                    ) ?>
                    <?= $escape($currency) ?>
                </td>
            </tr>

            <tr>
                <th>Refundable</th>

                <td class="payment-money">
                    $<?= number_format(
                        $remainingRefundable,
                        2
                    ) ?>
                    <?= $escape($currency) ?>
                </td>
            </tr>

            <tr>
                <th>Paid At</th>

                <td>
                    <?= $escape(
                        $order['paid_at'] ?? '—'
                    ) ?>
                </td>
            </tr>
        </table>
    </section>
</div>

<div class="payment-admin-grid">
    <section class="payment-admin-panel">
        <h2>Customer</h2>

        <table class="detail-table">
            <tr>
                <th>Name</th>

                <td>
                    <?= $escape(
                        $order['customer_name'] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Email</th>

                <td>
                    <?= $escape(
                        $order[
                            'customer_email'
                        ] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Phone</th>

                <td>
                    <?= $escape(
                        $order[
                            'customer_phone'
                        ] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Current Location</th>

                <td>
                    <?php
                    $customerLocation = trim(
                        (string) (
                            $order[
                                'customer_city'
                            ] ?? ''
                        )
                        . ', '
                        . (string) (
                            $order[
                                'customer_state'
                            ] ?? ''
                        ),
                        ', '
                    );
                    ?>

                    <?= $escape(
                        $customerLocation ?: '—'
                    ) ?>
                </td>
            </tr>
        </table>
    </section>

    <section class="payment-admin-panel">
        <h2>Shipping Method</h2>

        <table class="detail-table">
            <tr>
                <th>Method</th>

                <td>
                    <?= $escape(
                        $shippingMethodName
                            ?: 'Not recorded'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Code</th>

                <td>
                    <?= $escape(
                        $shippingMethodCode ?: '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Delivery Estimate</th>

                <td>
                    <?= $escape(
                        $deliveryEstimate
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Shipping Charge</th>

                <td class="payment-money">
                    $<?= number_format(
                        $shippingCharge,
                        2
                    ) ?>
                </td>
            </tr>
        </table>
    </section>
</div>

<section class="panel">
    <h2>Shipping Address Snapshot</h2>

    <?php if (! empty($shippingAddress)): ?>
        <address style="
            margin:0;
            font-style:normal;
            line-height:1.7;
        ">
            <strong>
                <?= $escape(
                    $shippingAddress[
                        'full_name'
                    ] ?? ''
                ) ?>
            </strong>

            <?php if (! empty(
                $shippingAddress['company']
            )): ?>
                <br>
                <?= $escape(
                    $shippingAddress['company']
                ) ?>
            <?php endif; ?>

            <br>

            <?= $escape(
                $shippingAddress[
                    'address_line_1'
                ] ?? ''
            ) ?>

            <?php if (! empty(
                $shippingAddress[
                    'address_line_2'
                ]
            )): ?>
                <br>
                <?= $escape(
                    $shippingAddress[
                        'address_line_2'
                    ]
                ) ?>
            <?php endif; ?>

            <br>

            <?= $escape(
                $shippingAddress['city'] ?? ''
            ) ?>,
            <?= $escape(
                $shippingAddress[
                    'state_region'
                ] ?? ''
            ) ?>
            <?= $escape(
                $shippingAddress[
                    'postal_code'
                ] ?? ''
            ) ?>

            <br>

            <?= $escape(
                $shippingAddress[
                    'country_code'
                ] ?? ''
            ) ?>

            <?php if (! empty(
                $shippingAddress['phone']
            )): ?>
                <br>
                <?= $escape(
                    $shippingAddress['phone']
                ) ?>
            <?php endif; ?>
        </address>
    <?php else: ?>
        <p style="margin:0;">
            No immutable shipping-address snapshot is
            available for this order.
        </p>
    <?php endif; ?>
</section>

<br>

<section class="panel">
    <h2>Line Items</h2>

    <div class="payment-transaction-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?= $escape(
                                $item[
                                    'product_name'
                                ] ?? 'Product'
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                $item[
                                    'product_sku'
                                ]
                                ?? $item['sku']
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                $item[
                                    'quantity'
                                ] ?? 0
                            ) ?>
                        </td>

                        <td class="payment-money">
                            $<?= number_format(
                                (float) (
                                    $item[
                                        'unit_price'
                                    ] ?? 0
                                ),
                                2
                            ) ?>
                        </td>

                        <td class="payment-money">
                            $<?= number_format(
                                (float) (
                                    $item[
                                        'line_total'
                                    ] ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5">
                            No order items were found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="panel totals-panel">
    <h2>Totals</h2>

    <table class="detail-table totals-table">
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
                    $shippingCharge,
                    2
                ) ?>
            </td>
        </tr>

        <tr>
            <th>Discount</th>

            <td>
                $<?= number_format(
                    (float) (
                        $order[
                            'discount_total'
                        ] ?? 0
                    ),
                    2
                ) ?>
            </td>
        </tr>

        <tr class="grand-total-row">
            <th>Grand Total</th>

            <td>
                $<?= number_format(
                    $orderTotal,
                    2
                ) ?>
            </td>
        </tr>

        <tr>
            <th>Paid</th>

            <td>
                $<?= number_format(
                    $amountPaid,
                    2
                ) ?>
            </td>
        </tr>

        <tr>
            <th>Refunded</th>

            <td>
                $<?= number_format(
                    $amountRefunded,
                    2
                ) ?>
            </td>
        </tr>

        <tr>
            <th>Net Collected</th>

            <td>
                <strong>
                    $<?= number_format(
                        max(
                            0,
                            $amountPaid
                            - $amountRefunded
                        ),
                        2
                    ) ?>
                </strong>
            </td>
        </tr>
    </table>
</section>

<br>

<section class="payment-admin-panel">
    <h2>Payment Transactions</h2>

    <?php if (
        empty($paymentTransactions)
    ): ?>
        <p class="payment-admin-muted">
            No payment transactions are recorded for
            this order.
        </p>
    <?php else: ?>
        <div class="payment-transaction-wrap">
            <table class="payment-transaction-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Provider</th>
                        <th>Provider Transaction</th>
                        <th>Processed</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $paymentTransactions
                        as $transaction
                    ): ?>
                        <?php
                        $transactionStatus =
                            (string) (
                                $transaction[
                                    'status'
                                ] ?? 'pending'
                            );
                        ?>

                        <tr>
                            <td>
                                <span
                                    class="payment-transaction-id"
                                >
                                    #<?= $escape(
                                        $transaction[
                                            'id'
                                        ] ?? ''
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= $escape(
                                    $label(
                                        $transaction[
                                            'type'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="payment-status-badge <?= $escape(
                                        $statusClass(
                                            $transactionStatus
                                        )
                                    ) ?>"
                                >
                                    <?= $escape(
                                        $label(
                                            $transactionStatus
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td class="payment-money">
                                $<?= number_format(
                                    (float) (
                                        $transaction[
                                            'amount'
                                        ] ?? 0
                                    ),
                                    2
                                ) ?>
                                <?= $escape(
                                    $transaction[
                                        'currency'
                                    ] ?? $currency
                                ) ?>

                                <?php if (
                                    (
                                        $transaction[
                                            'type'
                                        ] ?? ''
                                    ) === 'charge'
                                    && (float) (
                                        $transaction[
                                            'refunded_amount'
                                        ] ?? 0
                                    ) > 0
                                ): ?>
                                    <br>

                                    <small
                                        class="payment-admin-muted"
                                    >
                                        Refunded:
                                        $<?= number_format(
                                            (float) $transaction[
                                                'refunded_amount'
                                            ],
                                            2
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $transaction[
                                        'provider'
                                    ] ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?php if (! empty(
                                    $transaction[
                                        'provider_transaction_id'
                                    ]
                                )): ?>
                                    <span
                                        class="payment-transaction-id"
                                    >
                                        <?= $escape(
                                            $transaction[
                                                'provider_transaction_id'
                                            ]
                                        ) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $transaction[
                                        'processed_at'
                                    ]
                                    ?? $transaction[
                                        'created_at'
                                    ]
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?php if (! empty(
                                    $transaction[
                                        'failure_message'
                                    ]
                                )): ?>
                                    <div
                                        class="payment-failure-text"
                                    >
                                        <?php if (! empty(
                                            $transaction[
                                                'failure_code'
                                            ]
                                        )): ?>
                                            <strong>
                                                <?= $escape(
                                                    $transaction[
                                                        'failure_code'
                                                    ]
                                                ) ?>
                                            </strong>
                                            <br>
                                        <?php endif; ?>

                                        <?= $escape(
                                            $transaction[
                                                'failure_message'
                                            ]
                                        ) ?>
                                    </div>
                                <?php elseif (! empty(
                                    $transaction[
                                        'parent_transaction_id'
                                    ]
                                )): ?>
                                    Refund of transaction
                                    #<?= $escape(
                                        $transaction[
                                            'parent_transaction_id'
                                        ]
                                    ) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($canRefund): ?>
    <br>

    <section
        class="payment-admin-panel payment-refund-panel"
    >
        <h2>Issue Refund</h2>

        <form
            method="POST"
            action="/admin/orders/<?= $escape(
                $order['id']
            ) ?>/refund"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape(
                    $csrf_token
                ) ?>"
            >

            <input
                type="hidden"
                name="refund_request_token"
                value="<?= $escape(
                    $refund_request_token
                ) ?>"
            >

            <div class="payment-refund-grid">
                <div class="form-group">
                    <label for="refund_amount">
                        Refund Amount
                    </label>

                    <input
                        id="refund_amount"
                        type="number"
                        name="refund_amount"
                        value="<?= $escape(
                            number_format(
                                $remainingRefundable,
                                2,
                                '.',
                                ''
                            )
                        ) ?>"
                        min="0.01"
                        max="<?= $escape(
                            number_format(
                                $remainingRefundable,
                                2,
                                '.',
                                ''
                            )
                        ) ?>"
                        step="0.01"
                        inputmode="decimal"
                        required
                    >

                    <small class="form-help">
                        Maximum refundable:
                        $<?= number_format(
                            $remainingRefundable,
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </small>
                </div>

                <?php if (
                    $chargeProvider === 'test'
                ): ?>
                    <div class="form-group">
                        <label for="refund_scenario">
                            Test Refund Result
                        </label>

                        <select
                            id="refund_scenario"
                            name="refund_scenario"
                            required
                        >
                            <option value="approved">
                                Approved
                            </option>

                            <option value="declined">
                                Declined
                            </option>

                            <option value="error">
                                Provider Error
                            </option>
                        </select>
                    </div>
                <?php else: ?>
                    <input
                        type="hidden"
                        name="refund_scenario"
                        value="approved"
                    >
                <?php endif; ?>
            </div>

            <p class="payment-refund-note">
                This action records a refund transaction
                and updates payment totals. Inventory is
                not automatically restocked.
            </p>

            <div class="form-actions">
                <button
                    type="submit"
                    class="button-primary"
                    onclick="return confirm(
                        'Issue this refund? Inventory will not be restocked automatically.'
                    );"
                >
                    Issue Refund
                </button>
            </div>
        </form>
    </section>
<?php elseif (
    in_array(
        $paymentStatus,
        ['refunded', 'partially_refunded'],
        true
    )
): ?>
    <br>

    <section class="payment-admin-panel">
        <h2>Refund Status</h2>

        <p style="margin:0;">
            <?= $paymentStatus === 'refunded'
                ? 'This payment has been fully refunded.'
                : 'This payment has been partially refunded.' ?>
        </p>
    </section>
<?php endif; ?>

<br>

<div class="order-management-columns">
    <section class="panel form-panel">
        <h2>Update Order Status</h2>

        <form
            method="POST"
            action="/admin/orders/<?= $escape(
                $order['id']
            ) ?>/status"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape(
                    $csrf_token
                ) ?>"
            >

            <div class="form-group">
                <label for="order_status">
                    Status
                </label>

                <select
                    id="order_status"
                    name="status"
                    required
                >
                    <?php foreach (
                        [
                            'pending',
                            'paid',
                            'processing',
                            'shipped',
                            'cancelled',
                        ]
                        as $availableStatus
                    ): ?>
                        <option
                            value="<?= $escape(
                                $availableStatus
                            ) ?>"
                            <?= $orderStatus
                                === $availableStatus
                                    ? 'selected'
                                    : '' ?>
                        >
                            <?= $escape(
                                $label(
                                    $availableStatus
                                )
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button
                    type="submit"
                    class="button-primary"
                >
                    Update Status
                </button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Fulfillment Details</h2>

        <table class="detail-table">
            <tr>
                <th>Carrier</th>

                <td>
                    <?= $escape(
                        $order[
                            'shipping_carrier'
                        ] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Tracking Number</th>

                <td>
                    <?= $escape(
                        $order[
                            'tracking_number'
                        ] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Tracking URL</th>

                <td>
                    <?php if (! empty(
                        $order['tracking_url']
                    )): ?>
                        <a
                            href="<?= $escape(
                                $order[
                                    'tracking_url'
                                ]
                            ) ?>"
                            target="_blank"
                            class="table-link"
                        >
                            Open Tracking Link
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th>Shipped At</th>

                <td>
                    <?= $escape(
                        $order['shipped_at'] ?? '—'
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Notes</th>

                <td>
                    <?= nl2br(
                        $escape(
                            $order[
                                'fulfillment_notes'
                            ] ?? '—'
                        )
                    ) ?>
                </td>
            </tr>
        </table>
    </section>
</div>

<br>

<section class="panel form-panel">
    <h2>Update Fulfillment</h2>

    <form
        method="POST"
        action="/admin/orders/<?= $escape(
            $order['id']
        ) ?>/fulfillment"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="form-group">
            <label for="shipping_carrier">
                Shipping Carrier
            </label>

            <input
                id="shipping_carrier"
                type="text"
                name="shipping_carrier"
                value="<?= $escape(
                    $order[
                        'shipping_carrier'
                    ] ?? ''
                ) ?>"
                placeholder="USPS, UPS, FedEx, DHL"
            >
        </div>

        <div class="form-group">
            <label for="tracking_number">
                Tracking Number
            </label>

            <input
                id="tracking_number"
                type="text"
                name="tracking_number"
                value="<?= $escape(
                    $order[
                        'tracking_number'
                    ] ?? ''
                ) ?>"
            >
        </div>

        <div class="form-group">
            <label for="tracking_url">
                Tracking URL
            </label>

            <input
                id="tracking_url"
                type="url"
                name="tracking_url"
                value="<?= $escape(
                    $order[
                        'tracking_url'
                    ] ?? ''
                ) ?>"
                placeholder="https://..."
            >
        </div>

        <div class="form-group">
            <label for="shipped_at">
                Shipped At
            </label>

            <input
                id="shipped_at"
                type="datetime-local"
                name="shipped_at"
                value="<?= ! empty(
                    $order['shipped_at']
                )
                    ? $escape(
                        str_replace(
                            ' ',
                            'T',
                            substr(
                                (string) $order[
                                    'shipped_at'
                                ],
                                0,
                                16
                            )
                        )
                    )
                    : '' ?>"
            >
        </div>

        <div class="form-group">
            <label for="fulfillment_notes">
                Fulfillment Notes
            </label>

            <textarea
                id="fulfillment_notes"
                name="fulfillment_notes"
                rows="4"
            ><?= $escape(
                $order[
                    'fulfillment_notes'
                ] ?? ''
            ) ?></textarea>
        </div>

        <div class="form-actions">
            <button
                type="submit"
                class="button-primary"
            >
                Save Fulfillment
            </button>
        </div>
    </form>
</section>

<br>

<section class="panel form-panel">
    <h2>Add Timeline Note</h2>

    <form
        method="POST"
        action="/admin/orders/<?= $escape(
            $order['id']
        ) ?>/events"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="form-group">
            <label for="timeline_title">
                Note Title
            </label>

            <input
                id="timeline_title"
                type="text"
                name="title"
                required
                placeholder="Customer contacted support"
            >
        </div>

        <div class="form-group">
            <label for="timeline_description">
                Note Description
            </label>

            <textarea
                id="timeline_description"
                name="description"
                rows="4"
                placeholder="Add details about this order update..."
            ></textarea>
        </div>

        <div class="form-group">
            <label for="is_public">
                Visibility
            </label>

            <select
                id="is_public"
                name="is_public"
                required
            >
                <option value="0">
                    Internal only
                </option>

                <option value="1">
                    Customer visible
                </option>
            </select>

            <small class="form-help">
                Customer-visible notes appear on the
                public order-tracking page.
            </small>
        </div>

        <div class="form-actions">
            <button
                type="submit"
                class="button-primary"
            >
                Add Timeline Note
            </button>
        </div>
    </form>
</section>

<br>

<section class="panel">
    <h2>Order Activity Timeline</h2>

    <div class="timeline">
        <?php foreach ($events as $event): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>

                <div class="timeline-content">
                    <strong>
                        <?= $escape(
                            $event['title'] ?? ''
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

                    <?php if (
                        ! empty($event['old_value'])
                        || ! empty(
                            $event['new_value']
                        )
                    ): ?>
                        <p class="timeline-values">
                            <?php if (! empty(
                                $event['old_value']
                            )): ?>
                                From:
                                <strong>
                                    <?= $escape(
                                        $event[
                                            'old_value'
                                        ]
                                    ) ?>
                                </strong>
                            <?php endif; ?>

                            <?php if (! empty(
                                $event['new_value']
                            )): ?>
                                To:
                                <strong>
                                    <?= $escape(
                                        $event[
                                            'new_value'
                                        ]
                                    ) ?>
                                </strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <span>
                        <?= $escape(
                            $event[
                                'created_at'
                            ] ?? ''
                        ) ?>
                        ·
                        <?= (int) (
                            $event[
                                'is_public'
                            ] ?? 0
                        ) === 1
                            ? 'Public'
                            : 'Internal' ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($events)): ?>
            <p>
                No activity has been recorded for
                this order yet.
            </p>
        <?php endif; ?>
    </div>
</section>
