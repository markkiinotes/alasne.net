<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
?>

<main class="account-auth-page">
    <section class="account-auth-card account-auth-card-centered">
        <div class="account-auth-icon success" aria-hidden="true">
            ✓
        </div>

        <p class="eyebrow dark-eyebrow">
            Secure link requested
        </p>

        <h1>Check your email</h1>

        <p class="account-auth-intro">
            If <?= $escape($email) ?> matches a customer account,
            a secure one-time link has been sent. It expires in
            30 minutes and can only be used once.
        </p>

        <div class="account-privacy-note">
            Didn’t receive anything? Check spam first, then verify
            that you used the same email and postal code as your
            order before requesting another link.
        </div>

        <div class="account-auth-actions">
            <a
                href="/store/<?= $escape($store['slug']) ?>"
                class="account-primary-button"
            >
                Continue shopping
            </a>

            <a
                href="/store/<?= $escape($store['slug']) ?>/account"
                class="account-secondary-button"
            >
                Request another link
            </a>
        </div>
    </section>
</main>
