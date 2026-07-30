<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$label = static fn (mixed $value): string =>
    ucwords(
        str_replace('_', ' ', (string) $value)
    );
?>

<style>
.credit-page { display:grid; gap:20px; }
.credit-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.credit-card { padding:20px; border:1px solid #e2e8f0; border-radius:16px; background:#fff; box-shadow:0 10px 26px rgba(15,23,42,.06); }
.credit-card small { display:block; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:6px; }
.credit-table-wrap { overflow-x:auto; }
.credit-table { width:100%; border-collapse:collapse; }
.credit-table th,.credit-table td { padding:12px 10px; border-bottom:1px solid #e2e8f0; text-align:left; }
.credit-table th { background:#f8fafc; color:#475569; font-size:12px; text-transform:uppercase; }
.credit-amount { font-weight:800; color:#166534; }
@media(max-width:800px){ .credit-summary{grid-template-columns:1fr;} }
</style>

<div class="credit-page">
    <section class="page-header">
        <h1>Store Credit</h1>

        <p>
            <?= $escape(
                $customer['customer_name']
                ?? 'Customer'
            ) ?>
            <?php if (! empty(
                $customer['customer_email']
            )): ?>
                · <?= $escape(
                    $customer['customer_email']
                ) ?>
            <?php endif; ?>
        </p>

        <div class="table-actions">
            <a
                href="/admin/customers/<?= $escape(
                    $customer_id
                ) ?>"
                class="button-muted"
            >
                Back to Customer
            </a>
        </div>
    </section>

    <section class="credit-summary">
        <?php foreach ($accounts as $account): ?>
            <article class="credit-card">
                <small>
                    <?= $escape(
                        $account['store_name']
                    ) ?>
                </small>

                <h2>
                    $<?= number_format(
                        (float) $account['balance'],
                        2
                    ) ?>
                    <?= $escape(
                        $account['currency']
                    ) ?>
                </h2>

                <p>
                    Status:
                    <?= $escape(
                        $label($account['status'])
                    ) ?>
                </p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="credit-card">
        <h2>Credit Ledger</h2>

        <div class="credit-table-wrap">
            <table class="credit-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Type</th>
                        <th>Return</th>
                        <th>Order</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Notes</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $transactions
                        as $transaction
                    ): ?>
                        <tr>
                            <td>
                                <?= $escape(
                                    $transaction[
                                        'created_at'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $transaction[
                                        'store_name'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $label(
                                        $transaction['type']
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?php if (! empty(
                                    $transaction[
                                        'return_id'
                                    ]
                                )): ?>
                                    <a
                                        href="/admin/returns/<?= $escape(
                                            $transaction[
                                                'return_id'
                                            ]
                                        ) ?>"
                                        class="table-link"
                                    >
                                        <?= $escape(
                                            $transaction[
                                                'return_number'
                                            ]
                                            ?? '#'
                                            . $transaction[
                                                'return_id'
                                            ]
                                        ) ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (! empty(
                                    $transaction['order_id']
                                )): ?>
                                    <a
                                        href="/admin/orders/<?= $escape(
                                            $transaction[
                                                'order_id'
                                            ]
                                        ) ?>"
                                        class="table-link"
                                    >
                                        <?= $escape(
                                            $transaction[
                                                'order_number'
                                            ]
                                            ?? '#'
                                            . $transaction[
                                                'order_id'
                                            ]
                                        ) ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td class="credit-amount">
                                +$<?= number_format(
                                    (float) $transaction[
                                        'amount'
                                    ],
                                    2
                                ) ?>
                            </td>

                            <td>
                                $<?= number_format(
                                    (float) $transaction[
                                        'balance_after'
                                    ],
                                    2
                                ) ?>
                                <?= $escape(
                                    $transaction['currency']
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $transaction['notes']
                                    ?? ''
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty(
                        $transactions
                    )): ?>
                        <tr>
                            <td colspan="8">
                                No store-credit transactions
                                have been recorded.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
