<?php

declare(strict_types=1);

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$absoluteUrl = static function (mixed $value): string {
    $url = trim((string) $value);

    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url) === 1) {
        return $url;
    }

    if (! str_starts_with($url, '/')) {
        return '';
    }

    $forwardedProto = trim(
        explode(
            ',',
            (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
        )[0]
    );

    if (in_array(strtolower($forwardedProto), ['http', 'https'], true)) {
        $scheme = strtolower($forwardedProto);
    } else {
        $https = strtolower(
            trim(
                (string) ($_SERVER['HTTPS'] ?? '')
            )
        );

        $scheme = ($https !== '' && $https !== 'off')
            ? 'https'
            : 'http';
    }

    $forwardedHost = trim(
        explode(
            ',',
            (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '')
        )[0]
    );

    $host = $forwardedHost !== ''
        ? $forwardedHost
        : trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (
        $host === ''
        || preg_match(
            '/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/',
            $host
        ) !== 1
    ) {
        return $url;
    }

    return $scheme . '://' . $host . $url;
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

$robots = trim(
    (string) (
        $robots
        ?? 'noindex,follow'
    )
);

if ($robots === '') {
    $robots = 'noindex,follow';
}

$canonicalUrl = $absoluteUrl(
    $canonical_url
    ?? ''
);

$socialPreview = (bool) (
    $social_preview
    ?? false
);

$ogTitle = trim(
    (string) (
        $og_title
        ?? $pageTitle
    )
);

$ogDescription = trim(
    (string) (
        $og_description
        ?? $metaDescription
    )
);

$ogType = trim(
    (string) (
        $og_type
        ?? 'website'
    )
);

if ($ogType === '') {
    $ogType = 'website';
}

$ogImage = $absoluteUrl(
    $og_image
    ?? ''
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
        content="<?= $escape($robots) ?>"
    >

    <?php if ($canonicalUrl !== ''): ?>
        <link
            rel="canonical"
            href="<?= $escape($canonicalUrl) ?>"
        >
    <?php endif; ?>

    <?php if ($socialPreview): ?>
        <meta
            property="og:site_name"
            content="<?= $escape($storeName) ?>"
        >

        <meta
            property="og:title"
            content="<?= $escape($ogTitle) ?>"
        >

        <meta
            property="og:description"
            content="<?= $escape($ogDescription) ?>"
        >

        <meta
            property="og:type"
            content="<?= $escape($ogType) ?>"
        >

        <?php if ($canonicalUrl !== ''): ?>
            <meta
                property="og:url"
                content="<?= $escape($canonicalUrl) ?>"
            >
        <?php endif; ?>

        <?php if ($ogImage !== ''): ?>
            <meta
                property="og:image"
                content="<?= $escape($ogImage) ?>"
            >
        <?php endif; ?>

        <meta
            name="twitter:card"
            content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>"
        >

        <meta
            name="twitter:title"
            content="<?= $escape($ogTitle) ?>"
        >

        <meta
            name="twitter:description"
            content="<?= $escape($ogDescription) ?>"
        >

        <?php if ($ogImage !== ''): ?>
            <meta
                name="twitter:image"
                content="<?= $escape($ogImage) ?>"
            >
        <?php endif; ?>
    <?php endif; ?>

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
