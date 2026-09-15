<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$void = ($return['status'] ?? '') === 'cancelled';
?>

<style>
.authorization-document {
    max-width: 900px;
    margin: 0 auto;
    padding: 28px;
    background: #ffffff;
    color: #111827;
    font-family: Arial, sans-serif;
}

.authorization-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.authorization-actions button {
    padding: 10px 16px;
    border: 0;
    border-radius: 8px;
    background: #111827;
    color: #ffffff;
    font-weight: 800;
    cursor: pointer;
}

.authorization-header {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding-bottom: 20px;
    border-bottom: 3px solid #111827;
}

.authorization-rma {
    font-family: Consolas, monospace;
    font-size: 26px;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.authorization-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin: 24px 0;
}

.authorization-box {
    padding: 18px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
}

.authorization-box h2,
.authorization-box h3 {
    margin-top: 0;
}

.authorization-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.authorization-table th,
.authorization-table td {
    padding: 11px;
    border: 1px solid #cbd5e1;
    text-align: left;
}

.authorization-table th {
    background: #f1f5f9;
}

.authorization-footer {
    margin-top: 28px;
    padding-top: 18px;
    border-top: 1px solid #cbd5e1;
    color: #475569;
    font-size: 13px;
}

.authorization-void {
    margin-bottom: 20px;
    padding: 14px;
    border: 3px solid #991b1b;
    color: #991b1b;
    text-align: center;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 0.15em;
}

@media print {
    .authorization-actions {
        display: none;
    }

    .authorization-document {
        max-width: none;
        padding: 0;
    }
}

@media (max-width: 700px) {
    .authorization-header,
    .authorization-grid {
        display: grid;
        grid-template-columns: 1fr;
    }
}
</style>

<div class="authorization-document">
    <div class="authorization-actions">
        <button
            type="button"
            onclick="window.print();"
        >
            Print Authorization
        </button>
    </div>

    <?php if ($void): ?>
        <div class="authorization-void">
            VOID
        </div>
    <?php endif; ?>

    <header class="authorization-header">
        <div>
            <h1>Return Merchandise Authorization</h1>

            <p>
                <?= $escape(
                    $return['store_name']
                    ?? 'Store'
                ) ?>
            </p>
        </div>

        <div>
            <span>RMA Number</span>

            <div class="authorization-rma">
                <?= $escape(
                    $return['rma_number']
                ) ?>
            </div>
        </div>
    </header>

    <section class="authorization-grid">
        <article class="authorization-box">
            <h2>Return Details</h2>

            <p>
                <strong>Return:</strong>
                <?= $escape(
                    $return['return_number']
                ) ?>
                <br>

                <strong>Order:</strong>
                <?= $escape(
                    $return['order_number']
                ) ?>
                <br>

                <strong>Status:</strong>
                <?= $escape(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            (string) $return['status']
                        )
                    )
                ) ?>
                <br>

                <strong>Issued:</strong>
                <?= $escape(
                    $return[
                        'authorization_issued_at'
                    ]
                    ?? '—'
                ) ?>
                <br>

                <strong>Expires:</strong>
                <?= $escape(
                    $return[
                        'authorization_expires_at'
                    ]
                    ?? '—'
                ) ?>
            </p>
        </article>

        <article class="authorization-box">
            <h2>Customer</h2>

            <p>
                <?= $escape(
                    $return['customer_name']
                    ?? 'Customer'
                ) ?>
                <br>

                <?= $escape(
                    $return['customer_email']
                    ?? ''
                ) ?>
                <br>

                <?= $escape(
                    $return['customer_phone']
                    ?? ''
                ) ?>
            </p>
        </article>

        <article class="authorization-box">
            <h2>Return Address</h2>

            <p>
                <?= ! empty(
                    $return[
                        'return_address_snapshot'
                    ]
                )
                    ? nl2br(
                        $escape(
                            $return[
                                'return_address_snapshot'
                            ]
                        )
                    )
                    : 'Contact the store before shipping. No return address was configured when the authorization was issued.' ?>
            </p>
        </article>

        <article class="authorization-box">
            <h2>Shipping Responsibility</h2>

            <p>
                <?= (
                    $return[
                        'return_shipping_responsibility_snapshot'
                    ]
                    ?? 'customer'
                ) === 'store'
                    ? 'Store responsibility'
                    : 'Customer responsibility' ?>
            </p>

            <p>
                Do not send merchandise using collect-on-
                delivery service unless the store provided
                written approval.
            </p>
        </article>
    </section>

    <section class="authorization-box">
        <h2>Authorized Merchandise</h2>

        <table class="authorization-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Authorized Quantity</th>
                    <th>Reason</th>
                    <th>Approved Value</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?= $escape(
                                $item['product_name']
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                $item['product_sku']
                                ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                $item[
                                    'quantity_requested'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        (string) (
                                            $item[
                                                'reason_code'
                                            ]
                                            ?? $return[
                                                'reason_code'
                                            ]
                                        )
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            $<?= number_format(
                                (float) (
                                    $item[
                                        'approved_refund_amount'
                                    ]
                                    ?? $item[
                                        'requested_refund_amount'
                                    ]
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="authorization-box">
        <h2>Instructions</h2>

        <p>
            <?= nl2br(
                $escape(
                    $return[
                        'return_instructions_snapshot'
                    ]
                    ?? 'Include this authorization with the merchandise and write the RMA number on the package.'
                )
            ) ?>
        </p>
    </section>

    <footer class="authorization-footer">
        This authorization does not guarantee a refund.
        Final credit depends on the quantity and condition
        of merchandise received and the store’s return
        policy.
    </footer>
</div>
