<header class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a href="/store/<?= htmlspecialchars($store['slug']) ?>" class="back-link">
            ← Back to <?= htmlspecialchars($store['name']) ?>
        </a>

		<a href="/store/<?= htmlspecialchars($store['slug']) ?>/cart" class="cart-link">
			Cart <?= (int) ($cartQuantity ?? 0) > 0 ? '(' . htmlspecialchars((string) $cartQuantity) . ')' : '' ?>
		</a>
    </div>
</header>

<main class="storefront-container">

    <?php
        $inventory = (int) $product['inventory_quantity'];
        $threshold = (int) $product['low_stock_threshold'];

        if ($inventory <= 0) {
            $stockLabel = 'Out of stock';
            $stockClass = 'stock-out';
        } elseif ($inventory <= $threshold) {
            $stockLabel = 'Low stock';
            $stockClass = 'stock-low';
        } else {
            $stockLabel = 'In stock';
            $stockClass = 'stock-in';
        }
    ?>

    <section class="product-detail-layout">
        <div class="product-detail-media">
            <?php if (! empty($product['image_url'])): ?>
                <img
                    src="<?= htmlspecialchars($product['image_url']) ?>"
                    alt="<?= htmlspecialchars($product['image_alt_text'] ?: $product['name']) ?>"
                    class="product-detail-photo"
                >
            <?php else: ?>
                <div class="product-detail-placeholder">
                    No Image
                </div>
            <?php endif; ?>
        </div>

        <div class="product-detail-info">
            <p class="product-category">
                <?php foreach ($categories as $category): ?>
                    <?= htmlspecialchars($category['name']) ?>
                    <?= $category !== end($categories) ? ' · ' : '' ?>
                <?php endforeach; ?>

                <?php if (empty($categories)): ?>
                    Uncategorized
                <?php endif; ?>
            </p>

            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <?php if (! empty($product['sku'])): ?>
                <p class="product-sku">
                    SKU: <?= htmlspecialchars($product['sku']) ?>
                </p>
            <?php endif; ?>

            <div class="product-detail-price-row">
                <strong>$<?= htmlspecialchars(number_format((float) $product['price'], 2)) ?></strong>

                <span class="stock-pill <?= htmlspecialchars($stockClass) ?>">
                    <?= htmlspecialchars($stockLabel) ?>
                </span>
            </div>

            <div class="product-detail-description">
                <?= nl2br(htmlspecialchars(
                    $product['description']
                    ?: $product['meta_description']
                    ?: 'Product details coming soon.'
                )) ?>
            </div>

            <div class="product-detail-actions">
				<?php if ($inventory > 0): ?>
					<form
						method="POST"
						action="/store/<?= htmlspecialchars($store['slug']) ?>/cart/add"
						class="add-to-cart-form"
					>
						<input
							type="hidden"
							name="_csrf_token"
							value="<?= htmlspecialchars($csrf_token) ?>"
						>

						<input
							type="hidden"
							name="product_slug"
							value="<?= htmlspecialchars($product['slug']) ?>"
						>

						<label>
							Quantity
							<input
								type="number"
								name="quantity"
								value="1"
								min="1"
								max="<?= htmlspecialchars((string) $inventory) ?>"
							>
						</label>

						<button class="storefront-cart-button" type="submit">
							Add to Cart
						</button>
					</form>
				<?php else: ?>
					<button class="storefront-cart-button disabled" type="button" disabled>
						Currently Unavailable
					</button>
				<?php endif; ?>
			</div>
        </div>
    </section>

    <?php if (! empty($relatedProducts)): ?>
        <section class="storefront-section">
            <div class="section-header">
                <h2>More from <?= htmlspecialchars($store['name']) ?></h2>
                <p>Other products available in this storefront.</p>
            </div>

            <div class="product-grid">
                <?php foreach ($relatedProducts as $product): ?>
                    <?php include BASE_PATH . '/app/Views/storefront/partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</main>
