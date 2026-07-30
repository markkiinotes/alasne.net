<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$old = $old ?? [];

$oldQuantities = is_array(
    $old['quantities'] ?? null
)
    ? $old['quantities']
    : [];
?>

<style>
.customer-return-page {
    display: grid;
    gap: 24px;
    max-width: 1080px;
    margin: 0 auto;
}

.customer-return-card {
    padding: 26px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 12px 32px rgba(15, 23, 42, 0.07);
}

.customer-return-hero {
    text-align: center;
}

.customer-return-hero h1 {
    margin: 0 0 10px;
}

.customer-return-hero p {
    margin: 0;
    color: #64748b;
}

.customer-return-alert {
    padding: 14px 16px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
}

.customer-return-lookup {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr))
        auto;
    gap: 14px;
    align-items: end;
}

.customer-return-field label {
    display: block;
    margin-bottom: 7px;
    font-weight: 750;
}

.customer-return-field input,
.customer-return-field select,
.customer-return-field textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    font: inherit;
}

.customer-return-button,
.customer-return-secondary {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 46px;
    padding: 0 20px;
    border-radius: 10px;
    font: inherit;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.customer-return-button {
    border: 0;
    background: #111827;
    color: #ffffff;
}

.customer-return-secondary {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #111827;
}

.customer-return-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.customer-return-order {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.customer-return-stat {
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.customer-return-stat span {
    display: block;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.customer-return-table-wrap {
    overflow-x: auto;
}

.customer-return-table {
    width: 100%;
    border-collapse: collapse;
}

.customer-return-table th,
.customer-return-table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
}

.customer-return-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
}

.customer-return-table input[type="number"] {
    width: 88px;
    padding: 9px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.customer-return-details {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 22px;
}

.customer-return-note {
    padding: 14px;
    border-radius: 12px;
    background: #eff6ff;
    color: #1e3a8a;
    line-height: 1.5;
}

@media (max-width: 820px) {
    .customer-return-lookup,
    .customer-return-order,
    .customer-return-details {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="customer-return-page">
    <section
        class="customer-return-card customer-return-hero"
    >
        <h1>Request a Return</h1>

        <p>
            Verify your paid order, then select the
            merchandise you need to return.
        </p>
    </section>

    <?php if (! empty($error)): ?>
        <div
            class="customer-return-alert"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($order)): ?>
        <section class="customer-return-card">
            <h2>Find Your Order</h2>

            <form
                method="POST"
                action="/store/<?= $escape(
                    $store['slug']
                ) ?>/returns/request/lookup"
                class="customer-return-lookup"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape(
                        $csrf_token
                    ) ?>"
                >

                <div class="customer-return-field">
                    <label for="order_number">
                        Order Number
                    </label>

                    <input
                        id="order_number"
                        type="text"
                        name="order_number"
                        value="<?= $escape(
                            $order_number
                        ) ?>"
                        placeholder="WEB-20260730-..."
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="customer-return-field">
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
                    class="customer-return-button"
                >
                    Find Order
                </button>
            </form>

            <div
                class="customer-return-actions"
                style="margin-top:18px;"
            >
                <a
                    href="/store/<?= $escape(
                        $store['slug']
                    ) ?>/returns/track"
                    class="customer-return-secondary"
                >
                    Track Existing Return
                </a>

                <a
                    href="/store/<?= $escape(
                        $store['slug']
                    ) ?>"
                    class="customer-return-secondary"
                >
                    Back to Store
                </a>
            </div>
        </section>
    <?php else: ?>
        <form
            method="POST"
            action="/store/<?= $escape(
                $store['slug']
            ) ?>/returns/request"
            class="customer-return-card"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <input
                type="hidden"
                name="access_token"
                value="<?= $escape(
                    $access_token
                ) ?>"
            >

            <h2>Select Return Items</h2>

            <div class="customer-return-order">
                <article class="customer-return-stat">
                    <span>Order</span>

                    <strong>
                        <?= $escape(
                            $order['order_number']
                        ) ?>
                    </strong>
                </article>

                <article class="customer-return-stat">
                    <span>Order Status</span>

                    <strong>
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
                    </strong>
                </article>

                <article class="customer-return-stat">
                    <span>Amount Paid</span>

                    <strong>
                        $<?= number_format(
                            (float) (
                                $order[
                                    'verified_amount_paid'
                                ]
                                ?? $order[
                                    'amount_paid'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                    </strong>
                </article>
            </div>

            <p class="customer-return-note">
                Select only the quantity you intend to
                return. Requested merchandise value is
                based on the original item price. Final
                approval occurs after store review.
            </p>

            <div class="customer-return-table-wrap">
                <table class="customer-return-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Purchased</th>
                            <th>Already Requested</th>
                            <th>Available</th>
                            <th>Unit Price</th>
                            <th>Return Qty</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $available = (int) $item[
                                'quantity_available_to_return'
                            ];
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= $escape(
                                            $item[
                                                'product_name'
                                            ]
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
                                        $item['quantity']
                                    ) ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $item[
                                            'quantity_already_requested'
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <?= $escape($available) ?>
                                </td>

                                <td>
                                    $<?= number_format(
                                        (float) $item[
                                            'unit_price'
                                        ],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        name="quantities[<?= $escape(
                                            $item['id']
                                        ) ?>]"
                                        value="<?= $escape(
                                            $oldQuantities[
                                                $item['id']
                                            ]
                                            ?? 0
                                        ) ?>"
                                        min="0"
                                        max="<?= $escape(
                                            $available
                                        ) ?>"
                                        step="1"
                                        <?= $available <= 0
                                            ? 'disabled'
                                            : '' ?>
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="customer-return-details">
                <div class="customer-return-field">
                    <label for="reason_code">
                        Primary Reason
                    </label>

                    <select
                        id="reason_code"
                        name="reason_code"
                        required
                    >
                        <?php foreach (
                            [
                                'damaged' => 'Damaged',
                                'defective' => 'Defective',
                                'wrong_item' => 'Wrong Item',
                                'not_as_described' =>
                                    'Not as Described',
                                'changed_mind' =>
                                    'Changed Mind',
                                'other' => 'Other',
                            ]
                            as $value => $text
                        ): ?>
                            <option
                                value="<?= $escape(
                                    $value
                                ) ?>"
                                <?= (
                                    $old['reason_code']
                                    ?? 'other'
                                ) === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $escape($text) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="customer-return-field">
                    <label for="reason_details">
                        What happened?
                    </label>

                    <textarea
                        id="reason_details"
                        name="reason_details"
                        rows="4"
                        maxlength="500"
                        placeholder="Describe the issue with the merchandise."
                    ><?= $escape(
                        $old['reason_details'] ?? ''
                    ) ?></textarea>
                </div>

                <div
                    class="customer-return-field"
                    style="grid-column:1/-1;"
                >
                    <label for="customer_notes">
                        Additional Notes
                    </label>

                    <textarea
                        id="customer_notes"
                        name="customer_notes"
                        rows="4"
                        placeholder="Add any details the store should know."
                    ><?= $escape(
                        $old['customer_notes'] ?? ''
                    ) ?></textarea>
                </div>
            </div>

            <div
                class="customer-return-actions"
                style="margin-top:22px;"
            >
                <button
                    type="submit"
                    class="customer-return-button"
                >
                    Submit Return Request
                </button>

                <a
                    href="/store/<?= $escape(
                        $store['slug']
                    ) ?>/returns/request"
                    class="customer-return-secondary"
                >
                    Start Over
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>
