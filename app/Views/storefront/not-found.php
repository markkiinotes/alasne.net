<main class="storefront-container">
    <section class="category-page-hero">
        <p class="eyebrow dark-eyebrow">
            <?= htmlspecialchars(
                (string) (
                    $error_code
                    ?? 404
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?> Error
        </p>

        <h1>
            <?= htmlspecialchars(
                (string) (
                    $not_found_heading
                    ?? 'Page not found'
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </h1>

        <p>
            <?= htmlspecialchars(
                (string) (
                    $not_found_message
                    ?? 'The page you requested could not be found.'
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </p>
    </section>

    <section class="cart-empty">
        <h2>
            Where would you like to go next?
        </h2>

        <p>
            Check the address or request details and try again,
            or use the button below to continue.
        </p>

        <a
            href="<?= htmlspecialchars(
                (string) ($back_url ?? '/'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>"
            class="button-primary dark-button"
        >
            <?= htmlspecialchars(
                (string) (
                    $back_label
                    ?? 'Return Home'
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </a>
    </section>
</main>
