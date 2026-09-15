<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Receipt') ?></title>

    <style>
        body {
            margin: 0;
            padding: 32px;
            font-family: Arial, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .print-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .button {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            font-size: 14px;
        }

        .button-muted {
            background: #e5e7eb;
            color: #111827;
        }

        .receipt {
            max-width: 900px;
            margin: 0 auto;
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 24px;
            border-bottom: 2px solid #111827;
        }

        .brand h1 {
            margin: 0;
            font-size: 30px;
        }

        .brand p,
        .receipt-meta p,
        .info-card p {
            margin: 6px 0;
            color: #374151;
        }

        .receipt-meta {
            text-align: right;
        }

        .receipt-meta h2 {
            margin: 0 0 10px;
            font-size: 26px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
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

        .summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 28px;
        }

        .summary-card {
            width: 320px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row:last-child {
            border-bottom: 0;
        }

        .summary-row.total {
            background: #111827;
            color: #ffffff;
            font-weight: bold;
            font-size: 18px;
        }

        .receipt-note {
            margin-top: 28px;
            padding: 18px;
            border: 1px dashed #9ca3af;
            border-radius: 10px;
            color: #374151;
        }

        .receipt-note h2 {
            margin-top: 0;
            font-size: 16px;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .receipt {
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
        href="/store/<?= htmlspecialchars($store['slug']) ?>/track"
        class="button button-muted"
    >
        Back to Tracking
    </a>

    <button type="button" class="button" onclick="window.print()">
        Print Receipt
    </button>
</div>

<main class="receipt">

    <header class="receipt-header">
        <div class="brand">
            <h1><?= htmlspecialchars($store['name'] ?? 'Store') ?></h1>
            <p>Customer Receipt</p>
        </div>

        <div class="receipt-meta">
            <h2>Receipt</h2>

            <p>
                <strong>Order:</strong>
                <?= htmlspecialchars($order['order_number'] ?? '') ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['status'] ?? ''))) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars($order['placed_at'] ?? $order['created_at'] ?? '') ?>
            </p>
        </div>
    </header>

    <section class="section-grid">
        <div class="info-card">
            <h2>Customer</h2>

            <p>
                <strong><?= htmlspecialchars($order['customer_name'] ?? '') ?></strong>
            </p>

            <p><?= htmlspecialchars($order['customer_email'] ?? '') ?></p>

            <?php if (! empty($order['customer_phone'])): ?>
                <p><?= htmlspecialchars($order['customer_phone']) ?></p>
            <?php endif; ?>
        </div>

        <div class="info-card">
            <h2>Order Details</h2>

            <p>
                <strong>Store:</strong>
                <?= htmlspecialchars($store['name'] ?? '') ?>
            </p>

            <p>
                <strong>Order Number:</strong>
                <?= htmlspecialchars($order['order_number'] ?? '') ?>
            </p>

            <p>
                <strong>Order Status:</strong>
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['status'] ?? ''))) ?>
            </p>

            <?php if (! empty($order['tracking_number'])): ?>
                <p>
                    <strong>Tracking:</strong>
                    <?= htmlspecialchars($order['tracking_number']) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <table>
        <thead>
        <tr>
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
                    <strong>
                        <?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Product') ?>
                    </strong>
                </td>

                <td>
                    <?= htmlspecialchars($item['product_sku'] ?? $item['sku'] ?? '') ?>
                </td>

                <td class="number">
                    <?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?>
                </td>

                <td class="number">
                    $<?= number_format((float) ($item['unit_price'] ?? 0), 2) ?>
                </td>

                <td class="number">
                    $<?= number_format((float) ($item['line_total'] ?? 0), 2) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <section class="summary">
        <div class="summary-card">
            <div class="summary-row">
                <span>Subtotal</span>
                <strong>$<?= number_format((float) ($order['subtotal'] ?? 0), 2) ?></strong>
            </div>

            <div class="summary-row">
                <span>Tax</span>
                <strong>$<?= number_format((float) ($order['tax_total'] ?? 0), 2) ?></strong>
            </div>

            <div class="summary-row">
                <span>Shipping</span>
                <strong>$<?= number_format((float) ($order['shipping_total'] ?? 0), 2) ?></strong>
            </div>

            <div class="summary-row">
                <span>Discount</span>
                <strong>$<?= number_format((float) ($order['discount_total'] ?? 0), 2) ?></strong>
            </div>

            <div class="summary-row total">
                <span>Total</span>
                <strong>
                    $<?= number_format(
                        (float) (
                            $order['grand_total']
                            ?? $order['amount_paid']
                            ?? 0
                        ),
                        2
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="receipt-note">
        <h2>Thank You</h2>

        <p>
            Thank you for your order. Please keep this receipt for your records.
        </p>

        <?php if (! empty($order['tracking_url'])): ?>
            <p>
                <strong>Tracking Link:</strong>
                <a href="<?= htmlspecialchars($order['tracking_url']) ?>">
                    <?= htmlspecialchars($order['tracking_url']) ?>
                </a>
            </p>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
