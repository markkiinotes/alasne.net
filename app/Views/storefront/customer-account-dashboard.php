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

$customerName = trim(
    (string) ($customer['first_name'] ?? '')
    . ' '
    . (string) ($customer['last_name'] ?? '')
);
?>

<main class="account-page">
    <section class="account-hero">
        <div>
            <p class="eyebrow dark-eyebrow">
                My account
            </p>

            <h1>
                Welcome<?= $customerName !== ''
                    ? ', ' . $escape($customerName)
                    : '' ?>
            </h1>

            <p>
                Orders, tracking, returns, store credit, and your
                profile for <?= $escape($store['name']) ?>.
            </p>

            <?php if (! empty($summary['last_order_at'])): ?>
                <small>
                    Last order:
                    <?= $escape($summary['last_order_at']) ?>
                </small>
            <?php endif; ?>
        </div>

        <div class="account-actions">
            <a
                href="/store/<?= $escape($store['slug']) ?>"
                class="account-secondary-button"
            >
                Shop
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/track"
                class="account-secondary-button"
            >
                Track Order
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/returns/request"
                class="account-secondary-button"
            >
                Start Return
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/account/store-credit"
                class="account-secondary-button"
            >
                Store Credit
            </a>

            <form
                method="POST"
                action="/store/<?= $escape($store['slug']) ?>/account/logout"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <button
                    type="submit"
                    class="account-primary-button"
                >
                    Sign out
                </button>
            </form>
        </div>
    </section>

    <?php if (! empty($success)): ?>
        <div
            class="account-alert success"
            role="status"
        >
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div
            class="account-alert error"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <section
        class="account-summary"
        aria-label="Account summary"
    >
        <article>
            <span>Orders</span>
            <strong>
                <?= $escape($summary['order_count'] ?? 0) ?>
            </strong>
            <small>Successful / active orders</small>
        </article>

        <article>
            <span>Net paid</span>
            <strong>
                <?= $money($summary['lifetime_spend'] ?? 0) ?>
            </strong>
            <small>Paid less recorded refunds</small>
        </article>

        <article>
            <span>Returns</span>
            <strong>
                <?= $escape($summary['return_count'] ?? 0) ?>
            </strong>
            <small>Return requests on your account</small>
        </article>

        <article>
            <span>Store credit</span>
            <strong>
                <?= $money($summary['store_credit_balance'] ?? 0) ?>
            </strong>
            <small>Available account balance</small>
        </article>
    </section>

    <section class="account-panel">
        <div class="account-panel-heading">
            <div>
                <p class="eyebrow dark-eyebrow">
                    Purchase history
                </p>
                <h2>Orders</h2>
            </div>

            <a
                href="/store/<?= $escape($store['slug']) ?>/track"
                class="account-text-link"
            >
                Track with order number
            </a>
        </div>

        <?php if (! empty($orders)): ?>
            <div class="account-order-list">
                <?php foreach ($orders as $order): ?>
                    <article class="account-order-card">
                        <div class="account-order-main">
                            <div>
                                <span class="account-order-kicker">
                                    Order
                                </span>

                                <strong>
                                    <?= $escape(
                                        $order['order_number']
                                        ?? ''
                                    ) ?>
                                </strong>
                            </div>

                            <span
                                class="account-status-badge status-<?= $escape(
                                    strtolower(
                                        (string) (
                                            $order['status']
                                            ?? ''
                                        )
                                    )
                                ) ?>"
                            >
                                <?= $escape(
                                    $label(
                                        $order['status']
                                        ?? ''
                                    )
                                ) ?>
                            </span>
                        </div>

                        <div class="account-order-meta">
                            <span>
                                Placed
                                <strong>
                                    <?= $escape(
                                        $order['placed_at']
                                        ?? $order['created_at']
                                        ?? '—'
                                    ) ?>
                                </strong>
                            </span>

                            <span>
                                Payment
                                <strong>
                                    <?= $escape(
                                        $label(
                                            $order[
                                                'payment_status'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </strong>
                            </span>

                            <span>
                                Total
                                <strong>
                                    <?= $money(
                                        $order[
                                            'grand_total'
                                        ] ?? 0
                                    ) ?>
                                </strong>
                            </span>

                            <span>
                                Tracking
                                <strong>
                                    <?= $escape(
                                        $order[
                                            'tracking_number'
                                        ] ?? 'Not assigned'
                                    ) ?>
                                </strong>
                            </span>
                        </div>

                        <a
                            class="account-order-open"
                            href="/store/<?= $escape($store['slug']) ?>/account/orders/<?= $escape($order['id']) ?>"
                        >
                            View order details
                            <span aria-hidden="true">→</span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="account-empty-state">
                <strong>No orders to show yet.</strong>
                <p>
                    Successful and active orders will appear here.
                    Failed checkout attempts are not shown as purchases.
                </p>
                <a
                    href="/store/<?= $escape($store['slug']) ?>"
                    class="account-primary-button"
                >
                    Start shopping
                </a>
            </div>
        <?php endif; ?>
    </section>

    <div class="account-content-grid">
        <section class="account-panel">
            <div class="account-panel-heading">
                <div>
                    <p class="eyebrow dark-eyebrow">
                        Returns
                    </p>
                    <h2>Return activity</h2>
                </div>

                <a
                    href="/store/<?= $escape($store['slug']) ?>/returns/request"
                    class="account-text-link"
                >
                    Start a return
                </a>
            </div>

            <div class="account-mobile-scroll">
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>Return</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($returns as $return): ?>
                            <tr>
                                <td>
                                    <?= $escape(
                                        $return[
                                            'return_number'
                                        ]
                                        ?? (
                                            '#'
                                            . (
                                                $return['id']
                                                ?? ''
                                            )
                                        )
                                    ) ?>
                                </td>
                                <td>
                                    <?= $escape(
                                        $return[
                                            'order_number'
                                        ] ?? '—'
                                    ) ?>
                                </td>
                                <td>
                                    <span class="account-badge">
                                        <?= $escape(
                                            $label(
                                                $return[
                                                    'status'
                                                ] ?? ''
                                            )
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $escape(
                                        $return[
                                            'created_at'
                                        ] ?? '—'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($returns)): ?>
                            <tr>
                                <td colspan="4">
                                    No return activity yet.
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
                        Profile
                    </p>
                    <h2>Contact &amp; shipping</h2>
                </div>
            </div>

            <form
                method="POST"
                action="/store/<?= $escape($store['slug']) ?>/account/profile"
                class="account-profile-form"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <?php foreach ([
                    'first_name' => [
                        'First name',
                        'given-name',
                        true,
                    ],
                    'last_name' => [
                        'Last name',
                        'family-name',
                        true,
                    ],
                    'phone' => [
                        'Phone',
                        'tel',
                        false,
                    ],
                    'address_line_1' => [
                        'Address line 1',
                        'address-line1',
                        true,
                    ],
                    'address_line_2' => [
                        'Address line 2',
                        'address-line2',
                        false,
                    ],
                    'city' => [
                        'City',
                        'address-level2',
                        true,
                    ],
                    'state' => [
                        'State / region',
                        'address-level1',
                        true,
                    ],
                    'postal_code' => [
                        'Postal code',
                        'postal-code',
                        true,
                    ],
                    'country' => [
                        'Country',
                        'country-name',
                        true,
                    ],
                ] as $field => $config): ?>
                    <div class="<?= in_array(
                        $field,
                        [
                            'address_line_1',
                            'address_line_2',
                        ],
                        true
                    ) ? 'account-form-wide' : '' ?>">
                        <label for="<?= $escape($field) ?>">
                            <?= $escape($config[0]) ?>
                        </label>

                        <input
                            id="<?= $escape($field) ?>"
                            name="<?= $escape($field) ?>"
                            value="<?= $escape(
                                $customer[$field] ?? ''
                            ) ?>"
                            autocomplete="<?= $escape(
                                $config[1]
                            ) ?>"
                            <?= $config[2]
                                ? 'required'
                                : '' ?>
                        >
                    </div>
                <?php endforeach; ?>

                <div class="account-form-wide">
                    <button
                        type="submit"
                        class="account-primary-button"
                    >
                        Save profile
                    </button>
                </div>
            </form>
        </section>
    </div>
</main>
