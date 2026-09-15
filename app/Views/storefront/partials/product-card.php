<?php

use App\Support\StorefrontImage;

$productUrl = '/store/'
    . rawurlencode((string) $store['slug'])
    . '/product/'
    . rawurlencode((string) $product['slug']);

$image = StorefrontImage::resolve(
    $product['image_url'] ?? null
);
?>

<article class="product-card">
    <a
        href="<?= htmlspecialchars($productUrl) ?>"
        class="product-card-link"
    >
        <div class="product-image-wrap">
            <?php if ($image['src'] !== ''): ?>
                <picture>
                    <?php if (! empty($image['webp'])): ?>
                        <source
                            srcset="<?= htmlspecialchars(
                                (string) $image['webp']
                            ) ?>"
                            type="image/webp"
                        >
                    <?php endif; ?>

                    <img
                        src="<?= htmlspecialchars(
                            (string) $image['src']
                        ) ?>"
                        alt="<?= htmlspecialchars(
                            $product['image_alt_text']
                            ?: $product['name']
                        ) ?>"
                        class="product-image"
                        loading="lazy"
                        decoding="async"
                        <?php if (
                            ! empty($image['width'])
                            && ! empty($image['height'])
                        ): ?>
                            width="<?= (int) $image['width'] ?>"
                            height="<?= (int) $image['height'] ?>"
                        <?php endif; ?>
                    >
                </picture>
            <?php else: ?>
                <div class="product-image-placeholder">
                    No Image
                </div>
            <?php endif; ?>

            <?php if (
                (int) (
                    $product['is_featured']
                    ?? 0
                ) === 1
            ): ?>
                <span class="featured-pill">
                    Featured
                </span>
            <?php endif; ?>
        </div>

        <div class="product-card-body">
            <p class="product-category">
                <?= htmlspecialchars(
                    $product['categories']
                    ?? 'Uncategorized'
                ) ?>
            </p>

            <h3>
                <?= htmlspecialchars(
                    $product['name']
                ) ?>
            </h3>

            <p class="product-description">
                <?= htmlspecialchars(
                    $product['meta_description']
                    ?: $product['description']
                    ?: 'Product details coming soon.'
                ) ?>
            </p>

            <div class="product-meta">
                <strong>
                    $<?= htmlspecialchars(
                        number_format(
                            (float) $product['price'],
                            2
                        )
                    ) ?>
                </strong>

                <?php if (
                    (int) $product[
                        'inventory_quantity'
                    ] <= 0
                ): ?>
                    <span class="stock-pill stock-out">
                        Out of stock
                    </span>
                <?php elseif (
                    (int) $product[
                        'inventory_quantity'
                    ]
                    <= (int) $product[
                        'low_stock_threshold'
                    ]
                ): ?>
                    <span class="stock-pill stock-low">
                        Low stock
                    </span>
                <?php else: ?>
                    <span class="stock-pill stock-in">
                        In stock
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </a>
</article>
