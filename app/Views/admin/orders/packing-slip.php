<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$shippingAddress = $shippingAddress ?? null;

$shippingMethodName = trim(
    (string) (
        $order['shipping_method_name']
        ?? ''
    )
);

$estimatedDaysMin = isset(
    $order['shipping_estimated_days_min']
)
    && $order['shipping_estimated_days_min'] !== ''
        ? (int) $order['shipping_estimated_days_min']
        : null;

$estimatedDaysMax = isset(
    $order['shipping_estimated_days_max']
)
    && $order['shipping_estimated_days_max'] !== ''
        ? (int) $order['shipping_estimated_days_max']
        : null;

$deliveryEstimate = null;

if (
    $estimatedDaysMin !== null
    && $estimatedDaysMax !== null
) {
    $deliveryEstimate =
        $estimatedDaysMin === $estimatedDaysMax
            ? $estimatedDaysMin . ' business days'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= $escape($title ?? 'Packing Slip') ?>
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .print-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            max-width: 900px;
            margin: 0 auto 24px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
        }

        .button-muted {
            background: #e5e7eb;
            color: #111827;
        }

        .slip {
            max-width: 900px;
            margin: 0 auto;
        }

        .slip-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 24px;
            border-bottom: 2px solid #111827;
        }

        .brand h1,
        .order-meta h2 {
            margin: 0;
        }

        .brand h1 {
            font-size: 28px;
        }

        .brand p,
        .order-meta p,
        .info-card p,
        .info-card address {
            margin: 6px 0;
            color: #374151;
            line-height: 1.55;
        }

        .order-meta {
            text-align: right;
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .info-card {
            padding: 18px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
        }

        .info-card h2 {
            margin: 0 0 12px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-card address {
            font-style: normal;
        }

        .shipping-method {
            display: grid;
            gap: 6px;
        }

        table {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #111827;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .number {
            text-align: right;
        }

        .empty-row {
            text-align: center;
            color: #6b7280;
        }

        .notes {
            margin-top: 28px;
            padding: 18px;
            border: 1px dashed #9ca3af;
            border-radius: 10px;
        }

        .notes h2 {
            margin-top: 0;
            font-size: 16px;
        }

        .packing-check {
            width: 18px;
            height: 18px;
            border: 1px solid #111827;
            display: inline-block;
        }

        @media (max-width: 700px) {
            body {
                padding: 18px;
            }

            .slip-header,
            .print-actions {
                flex-direction: column;
            }

            .order-meta {
                text-align: left;
            }

            .section-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 8px;
            }
        }

        @media print {
            @page {
                margin: 0.5in;
            }

            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .slip {
                max-width: none;
            }

            a {
                color: #111827;
                text-decoration: none;
            }
        }
    </style>
</head>

<body>
<div class="print-actions">
    <a
        href="/admin/orders/<?= $escape(
            $order['id']
        ) ?>"
        class="button button-muted"
    >
        Back to Order
    </a>

    <button
        type="button"
        class="button"
        onclick="window.print()"
    >
        Print Packing Slip
    </button>
</div>

<main class="slip">
    <header class="slip-header">
        <div class="brand">
            <h1>
                <?= $escape(
                    $order['store_name'] ?? 'Store'
                ) ?>
            </h1>

            <p>Packing Slip</p>
        </div>

        <div class="order-meta">
            <h2>
                <?= $escape(
                    $order['order_number'] ?? ''
                ) ?>
            </h2>

            <p>
                <strong>Status:</strong>
                <?= $escape(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            (string) (
                                $order['status'] ?? ''
                            )
                        )
                    )
                ) ?>
            </p>

            <p>
                <strong>Placed:</strong>
                <?= $escape(
                    $order['placed_at']
                    ?? $order['created_at']
                    ?? ''
                ) ?>
            </p>
        </div>
    </header>

    <section class="section-grid">
        <div class="info-card">
            <h2>Ship To</h2>

            <?php if (! empty($shippingAddress)): ?>
                <address>
                    <strong>
                        <?= $escape(
                            $shippingAddress['full_name']
                            ?? ''
                        ) ?>
                    </strong>

                    <?php if (
                        ! empty(
                            $shippingAddress['company']
                        )
                    ): ?>
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

                    <?php if (
                        ! empty(
                            $shippingAddress[
                                'address_line_2'
                            ]
                        )
                    ): ?>
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

                    <?php if (
                        ! empty($shippingAddress['phone'])
                    ): ?>
                        <br>
                        <?= $escape(
                            $shippingAddress['phone']
                        ) ?>
                    <?php endif; ?>
                </address>
            <?php else: ?>
                <p>
                    No shipping-address snapshot is
                    available for this order.
                </p>
            <?php endif; ?>
        </div>

        <div class="info-card">
            <h2>Customer</h2>

            <p>
                <strong>
                    <?= $escape(
                        $order['customer_name'] ?? ''
                    ) ?>
                </strong>
            </p>

            <?php if (
                ! empty($order['customer_email'])
            ): ?>
                <p>
                    <?= $escape(
                        $order['customer_email']
                    ) ?>
                </p>
            <?php endif; ?>

            <?php if (
                ! empty($order['customer_phone'])
            ): ?>
                <p>
                    <?= $escape(
                        $order['customer_phone']
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="info-card">
            <h2>Shipping Method</h2>

            <div class="shipping-method">
                <p>
                    <strong>Method:</strong>
                    <?= $escape(
                        $shippingMethodName
                            ?: 'Not recorded'
                    ) ?>
                </p>

                <?php if (
                    ! empty(
                        $order['shipping_method_code']
                    )
                ): ?>
                    <p>
                        <strong>Code:</strong>
                        <?= $escape(
                            $order[
                                'shipping_method_code'
                            ]
                        ) ?>
                    </p>
                <?php endif; ?>

                <?php if (
                    $deliveryEstimate !== null
                ): ?>
                    <p>
                        <strong>Estimate:</strong>
                        <?= $escape($deliveryEstimate) ?>
                    </p>
                <?php endif; ?>

                <p>
                    <strong>Charge:</strong>
                    $<?= number_format(
                        (float) (
                            $order['shipping_total']
                            ?? 0
                        ),
                        2
                    ) ?>
                </p>
            </div>
        </div>

        <div class="info-card">
            <h2>Fulfillment</h2>

            <?php if (
                ! empty($order['shipping_carrier'])
                || ! empty($order['tracking_number'])
            ): ?>
                <p>
                    <strong>Carrier:</strong>
                    <?= $escape(
                        $order['shipping_carrier']
                        ?? 'Not set'
                    ) ?>
                </p>

                <p>
                    <strong>Tracking:</strong>
                    <?= $escape(
                        $order['tracking_number']
                        ?? 'Not set'
                    ) ?>
                </p>
            <?php else: ?>
                <p>
                    Carrier and tracking details have not
                    been entered.
                </p>
            <?php endif; ?>

            <?php if (
                ! empty($order['shipped_at'])
            ): ?>
                <p>
                    <strong>Shipped:</strong>
                    <?= $escape(
                        $order['shipped_at']
                    ) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th aria-label="Packed"></th>
                <th>Item</th>
                <th>SKU</th>
                <th class="number">Qty</th>
                <th class="number">Unit Price</th>
                <th class="number">Line Total</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <span
                            class="packing-check"
                            aria-hidden="true"
                        ></span>
                    </td>

                    <td>
                        <strong>
                            <?= $escape(
                                $item['product_name']
                                ?? $item['name']
                                ?? 'Product'
                            ) ?>
                        </strong>
                    </td>

                    <td>
                        <?= $escape(
                            $item['product_sku']
                            ?? $item['sku']
                            ?? '—'
                        ) ?>
                    </td>

                    <td class="number">
                        <?= $escape(
                            $item['quantity'] ?? ''
                        ) ?>
                    </td>

                    <td class="number">
                        $<?= number_format(
                            (float) (
                                $item['unit_price']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>

                    <td class="number">
                        $<?= number_format(
                            (float) (
                                $item['line_total']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($items)): ?>
                <tr>
                    <td
                        colspan="6"
                        class="empty-row"
                    >
                        No line items were found for this
                        order.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <section class="notes">
        <h2>Packing Notes</h2>

        <?php if (
            ! empty($order['fulfillment_notes'])
        ): ?>
            <p>
                <?= nl2br(
                    $escape(
                        $order['fulfillment_notes']
                    )
                ) ?>
            </p>
        <?php else: ?>
            <p>No fulfillment notes entered.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>