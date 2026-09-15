<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
?>

<style>
.return-success-page {
    max-width: 760px;
    margin: 0 auto;
}

.return-success-card {
    padding: 34px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    box-shadow:
        0 14px 36px rgba(15, 23, 42, 0.08);
    text-align: center;
}

.return-success-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    margin-bottom: 18px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 30px;
    font-weight: 900;
}

.return-success-number {
    margin: 22px 0;
    padding: 18px;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    background: #eff6ff;
}

.return-success-number span {
    display: block;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.return-success-number strong {
    font-size: 22px;
}

.return-success-warning {
    margin: 18px 0;
    padding: 14px;
    border-radius: 12px;
    background: #fef3c7;
    color: #92400e;
    font-weight: 700;
}

.return-success-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
}

.return-success-button {
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

.return-success-secondary {
    background: #ffffff;
    color: #111827;
    border: 1px solid #cbd5e1;
}
</style>

<div class="return-success-page">
    <section class="return-success-card">
        <div class="return-success-icon">
            ✓
        </div>

        <h1>Return Request Received</h1>

        <p>
            Your request is now waiting for store
            review. Keep the return number below for
            tracking.
        </p>

        <div class="return-success-number">
            <span>Return Number</span>

            <strong>
                <?= $escape(
                    $return['return_number']
                ) ?>
            </strong>
        </div>

        <?php if (! empty(
            $return['rma_number']
        )): ?>
            <div class="return-success-number">
                <span>RMA</span>

                <strong>
                    <?= $escape(
                        $return['rma_number']
                    ) ?>
                </strong>
            </div>

            <p>
                Your request was automatically approved.
                Open the tracking page to review and print
                the authorization.
            </p>
        <?php endif; ?>

        <?php if (! empty(
            $notification_warning
        )): ?>
            <div class="return-success-warning">
                <?= $escape(
                    $notification_warning
                ) ?>
            </div>
        <?php else: ?>
            <p>
                A confirmation email was queued for the
                email address used on the order.
            </p>
        <?php endif; ?>

        <div class="return-success-actions">
            <a
                href="<?= $escape($tracking_url) ?>"
                class="return-success-button"
            >
                Track Return
            </a>

            <a
                href="/store/<?= $escape(
                    $store['slug']
                ) ?>"
                class="return-success-button return-success-secondary"
            >
                Back to Store
            </a>
        </div>
    </section>
</div>
