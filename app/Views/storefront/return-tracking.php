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

$eventMessages = [
    'return_requested' =>
        'The store received the return request.',
    'return_approved' =>
        'The return was approved.',
    'return_received' =>
        'The returned merchandise was received.',
    'return_completed' =>
        'The return process was completed.',
    'return_cancelled' =>
        'The return was cancelled.',
    'refund_succeeded' =>
        'The payment refund was completed.',
    'refund_failed' =>
        'The payment refund requires store review.',
];
?>

<style>
.return-track-page {
    display: grid;
    gap: 24px;
    max-width: 1040px;
    margin: 0 auto;
}

.return-track-hero,
.return-track-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 12px 32px rgba(15, 23, 42, 0.07);
}

.return-track-hero {
    padding: 30px;
    text-align: center;
}

.return-track-hero h1 {
    margin: 0 0 10px;
}

.return-track-hero p {
    margin: 0;
    color: #64748b;
}

.return-track-panel {
    padding: 24px;
}

.return-track-form {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr))
        auto;
    gap: 14px;
    align-items: end;
}

.return-track-form label {
    display: block;
    margin-bottom: 7px;
    font-weight: 700;
}

.return-track-form input {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font: inherit;
}

.return-track-button,
.return-track-secondary {
    min-height: 46px;
    padding: 0 20px;
    border: 0;
    border-radius: 10px;
    background: #111827;
    color: #ffffff;
    font: inherit;
    font-weight: 800;
    cursor: pointer;
}

.return-track-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 20px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    color: #111827;
    font-weight: 800;
    text-decoration: none;
}

.return-track-error {
    padding: 14px 16px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
}

.return-track-summary {
    display: grid;
    grid-template-columns:
        repeat(5, minmax(0, 1fr));
    gap: 14px;
}

.return-track-stat {
    padding: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    background: #f8fafc;
}

.return-track-stat span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.return-track-stat strong {
    font-size: 19px;
}

.return-track-table {
    width: 100%;
    border-collapse: collapse;
}

.return-track-table th,
.return-track-table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

.return-track-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
}

.return-track-timeline {
    display: grid;
    gap: 18px;
}

.return-track-event {
    border-left: 3px solid #2563eb;
    padding-left: 15px;
}

.return-track-event strong {
    display: block;
    margin-bottom: 5px;
}

.return-track-event p {
    margin: 0 0 5px;
    color: #475569;
}

@media (max-width: 800px) {
    .return-track-form,
    .return-track-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="return-track-page">
    <section class="return-track-hero">
        <h1>Track a Return</h1>

        <p>
            Enter the return number and the email
            address used for the order.
        </p>
    </section>

    <?php if (! empty($error)): ?>
        <div
            class="return-track-error"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="return-track-panel">
        <form
            method="POST"
            action="/store/<?= $escape(
                $store['slug']
            ) ?>/returns/track"
            class="return-track-form"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div>
                <label for="return_number">
                    Return Number
                </label>

                <input
                    id="return_number"
                    type="text"
                    name="return_number"
                    value="<?= $escape(
                        $return_number
                    ) ?>"
                    placeholder="RET-20260729-..."
                    autocomplete="off"
                    required
                >
            </div>

            <div>
                <label for="email">
                    Order Email
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= $escape(
                        $customer_email
                    ) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <button
                type="submit"
                class="return-track-button"
            >
                Find Return
            </button>
        </form>

        <div style="margin-top:18px;">
            <a
                href="/store/<?= $escape(
                    $store['slug']
                ) ?>/returns/request"
                class="return-track-secondary"
            >
                Start a New Return
            </a>

            <a
                href="/store/<?= $escape(
                    $store['slug']
                ) ?>/returns/policy"
                class="return-track-secondary"
                style="margin-left:8px;"
            >
                Return Policy
            </a>
        </div>
    </section>

    <?php if (! empty($return)): ?>
        <section class="return-track-panel">
            <h2>
                <?= $escape(
                    $return['return_number']
                ) ?>
            </h2>

            <div class="return-track-summary">
                <article class="return-track-stat">
                    <span>Return Status</span>

                    <strong>
                        <?= $escape(
                            $label($return['status'])
                        ) ?>
                    </strong>
                </article>

                <article class="return-track-stat">
                    <span>Order</span>

                    <strong>
                        <?= $escape(
                            $return['order_number']
                        ) ?>
                    </strong>
                </article>

                <article class="return-track-stat">
                    <span>Approved Value</span>

                    <strong>
                        $<?= number_format(
                            (float) $return[
                                'approved_refund_amount'
                            ],
                            2
                        ) ?>
                    </strong>
                </article>

                <article class="return-track-stat">
                    <span>Refund Status</span>

                    <strong>
                        <?= $escape(
                            $label(
                                $return['refund_status']
                            )
                        ) ?>
                    </strong>
                </article>

                <article class="return-track-stat">
                    <span>RMA</span>

                    <strong>
                        <?= $escape(
                            $return['rma_number']
                            ?? 'Pending Approval'
                        ) ?>
                    </strong>
                </article>
            </div>
        </section>


        <?php if (
            ! empty($return['rma_number'])
            && ! in_array(
                $return['status'],
                ['requested', 'cancelled'],
                true
            )
        ): ?>
            <section class="return-track-panel">
                <h2>Return Authorization</h2>

                <p>
                    <strong>RMA:</strong>
                    <?= $escape(
                        $return['rma_number']
                    ) ?>
                    <br>

                    <strong>Authorization Expires:</strong>
                    <?= $escape(
                        $return[
                            'authorization_expires_at'
                        ]
                        ?? '—'
                    ) ?>
                    <br>

                    <strong>Return Shipping:</strong>
                    <?= (
                        $return[
                            'return_shipping_responsibility_snapshot'
                        ]
                        ?? 'customer'
                    ) === 'store'
                        ? 'Store responsibility'
                        : 'Customer responsibility' ?>
                </p>

                <?php if (! empty(
                    $return['return_address_snapshot']
                )): ?>
                    <h3>Return Address</h3>

                    <p>
                        <?= nl2br(
                            $escape(
                                $return[
                                    'return_address_snapshot'
                                ]
                            )
                        ) ?>
                    </p>
                <?php endif; ?>

                <h3>Instructions</h3>

                <p>
                    <?= nl2br(
                        $escape(
                            $return[
                                'return_instructions_snapshot'
                            ]
                            ?? 'Follow the store’s instructions before sending merchandise.'
                        )
                    ) ?>
                </p>

                <form
                    method="POST"
                    action="/store/<?= $escape(
                        $store['slug']
                    ) ?>/returns/authorization"
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
                        name="return_number"
                        value="<?= $escape(
                            $return_number
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="email"
                        value="<?= $escape(
                            $customer_email
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="return-track-button"
                    >
                        Print Return Authorization
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <section class="return-track-panel">
            <h2>Returned Items</h2>

            <div style="overflow-x:auto;">
                <table class="return-track-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Requested</th>
                            <th>Received</th>
                            <th>Resolution</th>
                            <th>Approved</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= $escape(
                                            $item['product_name']
                                        ) ?>
                                    </strong>

                                    <?php if (! empty(
                                        $item['product_sku']
                                    )): ?>
                                        <br>
                                        <small>
                                            <?= $escape(
                                                $item[
                                                    'product_sku'
                                                ]
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
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
                                        $item[
                                            'quantity_received'
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $label(
                                            $item[
                                                'resolution_code'
                                            ]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    $<?= number_format(
                                        (float) $item[
                                            'approved_refund_amount'
                                        ],
                                        2
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="return-track-panel">
            <h2>Return Activity</h2>

            <div class="return-track-timeline">
                <?php foreach ($events as $event): ?>
                    <article class="return-track-event">
                        <strong>
                            <?= $escape(
                                $event['title']
                            ) ?>
                        </strong>

                        <p>
                            <?= $escape(
                                $eventMessages[
                                    $event['type']
                                ]
                                ?? 'The return was updated.'
                            ) ?>
                        </p>

                        <small>
                            <?= $escape(
                                $event['created_at']
                            ) ?>
                        </small>
                    </article>
                <?php endforeach; ?>

                <?php if (empty($events)): ?>
                    <p>
                        No public return activity is
                        available yet.
                    </p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
