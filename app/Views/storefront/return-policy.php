<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$enabled = (int) (
    $policy['is_enabled'] ?? 0
) === 1;
?>

<style>
.public-return-policy {
    display: grid;
    gap: 22px;
    max-width: 900px;
    margin: 0 auto;
}

.public-return-policy-card {
    padding: 28px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 12px 32px rgba(15, 23, 42, 0.07);
}

.public-return-policy-hero {
    text-align: center;
}

.public-return-policy-hero h1 {
    margin: 0 0 10px;
}

.public-return-policy-hero p {
    margin: 0;
    color: #64748b;
}

.public-return-policy-status {
    display: inline-flex;
    margin-top: 16px;
    padding: 7px 11px;
    border-radius: 999px;
    background: <?= $enabled
        ? '#dcfce7'
        : '#fee2e2' ?>;
    color: <?= $enabled
        ? '#166534'
        : '#991b1b' ?>;
    font-size: 12px;
    font-weight: 800;
}

.public-return-policy-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.public-return-policy-item {
    padding: 17px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.public-return-policy-item span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.public-return-policy-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
}

.public-return-policy-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 20px;
    border-radius: 10px;
    background: #111827;
    color: #ffffff;
    font-weight: 800;
    text-decoration: none;
}

.public-return-policy-button.secondary {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #111827;
}

@media (max-width: 700px) {
    .public-return-policy-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="public-return-policy">
    <section
        class="public-return-policy-card public-return-policy-hero"
    >
        <h1>
            <?= $escape(
                $policy['policy_title']
                ?? 'Return Policy'
            ) ?>
        </h1>

        <p>
            <?= $escape($store['name']) ?>
        </p>

        <span class="public-return-policy-status">
            <?= $enabled
                ? 'Customer Return Requests Available'
                : 'Customer Return Requests Unavailable' ?>
        </span>
    </section>

    <section class="public-return-policy-card">
        <?php if (! empty(
            $policy['policy_text']
        )): ?>
            <p>
                <?= nl2br(
                    $escape(
                        $policy['policy_text']
                    )
                ) ?>
            </p>
        <?php endif; ?>

        <div class="public-return-policy-grid">
            <article class="public-return-policy-item">
                <span>Request Window</span>

                <strong>
                    <?= $escape(
                        $policy[
                            'return_window_days'
                        ] ?? 30
                    ) ?>
                    days after the order date
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>Order Stage</span>

                <strong>
                    <?= (int) (
                        $policy[
                            'require_fulfilled_status'
                        ] ?? 0
                    ) === 1
                        ? 'Order must be shipped or completed'
                        : 'Paid order required' ?>
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>Changed Mind</span>

                <strong>
                    <?= (int) (
                        $policy[
                            'allow_changed_mind'
                        ] ?? 0
                    ) === 1
                        ? 'Eligible'
                        : 'Not eligible' ?>
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>Return Shipping</span>

                <strong>
                    <?= (int) (
                        $policy[
                            'customer_pays_return_shipping'
                        ] ?? 1
                    ) === 1
                        ? 'Customer responsibility'
                        : 'Store responsibility' ?>
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>RMA Validity</span>

                <strong>
                    <?= $escape(
                        $policy[
                            'authorization_valid_days'
                        ] ?? 30
                    ) ?>
                    days after approval
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>Approval</span>

                <strong>
                    <?= (int) (
                        $policy[
                            'auto_approve_customer_requests'
                        ] ?? 0
                    ) === 1
                        ? 'Eligible requests are automatically approved'
                        : 'Store review is required' ?>
                </strong>
            </article>

            <article class="public-return-policy-item">
                <span>Refund Timing</span>

                <strong>
                    Refunds are processed after returned
                    merchandise is received and reviewed
                </strong>
            </article>
        </div>
    </section>

    <?php if (! empty(
        $policy['return_instructions']
    )): ?>
        <section class="public-return-policy-card">
            <h2>Return Instructions</h2>

            <p>
                <?= nl2br(
                    $escape(
                        $policy[
                            'return_instructions'
                        ]
                    )
                ) ?>
            </p>
        </section>
    <?php endif; ?>

    <div class="public-return-policy-actions">
        <?php if ($enabled): ?>
            <a
                href="/store/<?= $escape(
                    $store['slug']
                ) ?>/returns/request"
                class="public-return-policy-button"
            >
                Request a Return
            </a>
        <?php endif; ?>

        <a
            href="/store/<?= $escape(
                $store['slug']
            ) ?>/returns/track"
            class="public-return-policy-button secondary"
        >
            Track a Return
        </a>

        <a
            href="/store/<?= $escape(
                $store['slug']
            ) ?>"
            class="public-return-policy-button secondary"
        >
            Back to Store
        </a>
    </div>
</div>
