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

    <section class="category-page-hero">
        <p class="eyebrow dark-eyebrow">Category</p>

        <h1><?= htmlspecialchars($category['name']) ?></h1>

        <p>
            <?= htmlspecialchars(
                $category['description']
                ?: 'Browse products available in this category.'
            ) ?>
        </p>
    </section>

    <section class="storefront-section">
        <div class="section-header">
            <h2><?= htmlspecialchars($category['name']) ?> Products</h2>
            <p>
                Products currently published in this category.
            </p>
        </div>

        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php include BASE_PATH . '/app/Views/storefront/partials/product-card.php'; ?>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
                <p>No products are currently published in this category.</p>
            <?php endif; ?>
        </div>
    </section>

</main>
