<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$storeName = trim(
    (string) (
        $store['name']
        ?? 'Online Store'
    )
);

$storeSlug = trim(
    (string) (
        $store['slug']
        ?? ''
    )
);

$pageTitle = trim(
    (string) (
        $title
        ?? $storeName
    )
);

if ($pageTitle === '') {
    $pageTitle = $storeName;
}

$metaDescription = trim(
    (string) (
        $meta_description
        ?? 'Shop products, track orders, and manage your account.'
    )
);

$cartQuantity = max(
    0,
    (int) (
        $cartQuantity
        ?? 0
    )
);

$storeBase = $storeSlug !== ''
    ? '/store/' . rawurlencode($storeSlug)
    : '';

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= $escape($pageTitle) ?></title>

    <meta
        name="description"
        content="<?= $escape($metaDescription) ?>"
    >

    <meta
        name="robots"
        content="index,follow"
    >

    <link
        rel="stylesheet"
        href="/assets/css/storefront.css"
    >
</head>

<body class="storefront-site">
    <a
        class="sf-skip-link"
        href="#main-content"
    >
        Skip to main content
    </a>

    <?php if ($storeBase !== ''): ?>
        <header class="sf-site-header">
            <div class="sf-utility-bar">
                <div class="storefront-container sf-utility-inner">
                    <span>
                        Secure shopping experience
                    </span>

                    <nav
                        class="sf-utility-nav"
                        aria-label="Customer services"
                    >
                        <a href="<?= $escape($storeBase) ?>/track">
                            Track Order
                        </a>

                        <a href="<?= $escape($storeBase) ?>/returns/request">
                            Returns
                        </a>
                    </nav>
                </div>
            </div>

            <div class="sf-main-header">
                <div class="storefront-container sf-header-inner">
                    <a
                        class="sf-brand"
                        href="<?= $escape($storeBase) ?>"
                        aria-label="<?= $escape($storeName) ?> home"
                    >
                        <span
                            class="sf-brand-mark"
                            aria-hidden="true"
                        >
                            <?= $escape(
                                mb_strtoupper(
                                    mb_substr(
                                        $storeName !== ''
                                            ? $storeName
                                            : 'S',
                                        0,
                                        1
                                    )
                                )
                            ) ?>
                        </span>

                        <span class="sf-brand-copy">
                            <strong>
                                <?= $escape($storeName) ?>
                            </strong>

                            <small>
                                Shop with confidence
                            </small>
                        </span>
                    </a>

                    <nav
                        class="sf-primary-nav"
                        aria-label="Primary navigation"
                    >
                        <a href="<?= $escape($storeBase) ?>">
                            Shop
                        </a>

                        <a href="<?= $escape($storeBase) ?>/account">
                            Account
                        </a>

                        <a href="<?= $escape($storeBase) ?>/track">
                            Track
                        </a>

                        <a
                            class="sf-cart-link"
                            href="<?= $escape($storeBase) ?>/cart"
                        >
                            <span>Cart</span>

                            <?php if ($cartQuantity > 0): ?>
                                <span
                                    class="sf-cart-badge"
                                    aria-label="<?= $escape($cartQuantity) ?> items in cart"
                                >
                                    <?= $escape($cartQuantity) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </nav>

                    <details class="sf-mobile-nav">
                        <summary>
                            <span aria-hidden="true">☰</span>
                            Menu
                        </summary>

                        <nav aria-label="Mobile navigation">
                            <a href="<?= $escape($storeBase) ?>">
                                Shop
                            </a>

                            <a href="<?= $escape($storeBase) ?>/account">
                                Account
                            </a>

                            <a href="<?= $escape($storeBase) ?>/track">
                                Track Order
                            </a>

                            <a href="<?= $escape($storeBase) ?>/returns/request">
                                Returns
                            </a>

                            <a href="<?= $escape($storeBase) ?>/cart">
                                Cart
                                <?php if ($cartQuantity > 0): ?>
                                    (<?= $escape($cartQuantity) ?>)
                                <?php endif; ?>
                            </a>
                        </nav>
                    </details>
                </div>
            </div>
        </header>
    <?php endif; ?>

    <div id="main-content" tabindex="-1">
        <?= $content ?? $viewContent ?? '' ?>
    </div>

    <footer class="sf-site-footer">
        <div class="storefront-container sf-footer-grid">
            <section>
                <strong class="sf-footer-brand">
                    <?= $escape($storeName) ?>
                </strong>

                <p>
                    A secure, customer-focused shopping experience.
                </p>
            </section>

            <?php if ($storeBase !== ''): ?>
                <nav
                    class="sf-footer-links"
                    aria-label="Footer navigation"
                >
                    <a href="<?= $escape($storeBase) ?>">
                        Shop
                    </a>

                    <a href="<?= $escape($storeBase) ?>/account">
                        Account
                    </a>

                    <a href="<?= $escape($storeBase) ?>/track">
                        Track Order
                    </a>

                    <a href="<?= $escape($storeBase) ?>/returns/request">
                        Start a Return
                    </a>
                </nav>
            <?php endif; ?>

            <p class="sf-footer-meta">
                &copy; <?= $escape($year) ?>
                <?= $escape($storeName) ?>.
                All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>
