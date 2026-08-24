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
?>

<main class="account-page account-credit-page">
    <section class="account-hero">
        <div>
            <p class="eyebrow dark-eyebrow">
                Store credit
            </p>

            <h1>Your credit balance</h1>

            <p>
                Store credit issued to your
                <?= $escape($store['name']) ?> account.
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
                href="/store/<?= $escape($store['slug']) ?>"
                class="account-primary-button"
            >
                Shop
            </a>
        </div>
    </section>

    <section class="account-credit-balance">
        <span>Available balance</span>

        <strong>
            <?= $money(
                $account['balance'] ?? 0
            ) ?>
        </strong>

        <small>
            <?= $escape(
                $account['currency'] ?? 'USD'
            ) ?>
        </small>
    </section>

    <section class="account-panel">
        <div class="account-panel-heading">
            <div>
                <p class="eyebrow dark-eyebrow">
                    Activity
                </p>
                <h2>Credit history</h2>
            </div>
        </div>

        <?php if (! empty($transactions)): ?>
            <div class="account-mobile-scroll">
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance after</th>
                            <th>Note</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $transactions as $transaction
                        ): ?>
                            <tr>
                                <td>
                                    <?= $escape(
                                        $transaction[
                                            'created_at'
                                        ] ?? ''
                                    ) ?>
                                </td>
                                <td>
                                    <span class="account-badge">
                                        <?= $escape(
                                            $label(
                                                $transaction[
                                                    'transaction_type'
                                                ]
                                                ?? $transaction['type']
                                                ?? ''
                                            )
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>
                                        <?= $money(
                                            $transaction[
                                                'amount'
                                            ] ?? 0
                                        ) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= $money(
                                        $transaction[
                                            'balance_after'
                                        ] ?? 0
                                    ) ?>
                                </td>
                                <td>
                                    <?= $escape(
                                        $transaction[
                                            'notes'
                                        ]
                                        ?? $transaction['note']
                                        ?? '—'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="account-empty-state">
                <strong>No store-credit activity yet.</strong>
                <p>
                    Credits issued from eligible return
                    resolutions will appear here.
                </p>
            </div>
        <?php endif; ?>
    </section>
</main>
