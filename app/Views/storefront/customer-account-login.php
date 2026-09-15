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
    <section class="account-auth-card">
        <div class="account-auth-icon" aria-hidden="true">→</div>

        <p class="eyebrow dark-eyebrow">
            Customer account
        </p>

        <h1>Welcome back</h1>

        <p class="account-auth-intro">
            Enter the email address and postal code used on your
            order. We’ll email a secure one-time link to access
            your orders, tracking, returns, and store credit.
        </p>

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

        <form
            method="POST"
            action="/store/<?= $escape($store['slug']) ?>/account/link"
            class="account-auth-form"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="form-group">
                <label for="email">
                    Email address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= $escape($email ?? '') ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="postal_code">
                    Postal code
                </label>

                <input
                    id="postal_code"
                    type="text"
                    name="postal_code"
                    value="<?= $escape($postal_code ?? '') ?>"
                    autocomplete="postal-code"
                    required
                >
            </div>

            <button
                type="submit"
                class="account-primary-button"
            >
                Email my secure link
            </button>
        </form>

        <div class="account-privacy-note">
            <strong>Private by design.</strong>
            The response never reveals whether an email address
            has an account. If the details match, a one-time link
            will be sent and will expire after 30 minutes.
        </div>

        <nav
            class="account-auth-links"
            aria-label="Other customer services"
        >
            <a href="/store/<?= $escape($store['slug']) ?>">
                Continue shopping
            </a>

            <a href="/store/<?= $escape($store['slug']) ?>/track">
                Track an order
            </a>

            <a href="/store/<?= $escape($store['slug']) ?>/returns/request">
                Request a return
            </a>
        </nav>
    </section>
</main>
