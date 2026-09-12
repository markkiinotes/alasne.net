<header class="storefront-hero">
    <div class="storefront-container">
        <p class="eyebrow">Welcome to</p>

        <h1><?= htmlspecialchars($store['name']) ?></h1>

        <p class="hero-text">
            Shop featured products, browse categories, and discover items curated for this store.
        </p>

        <div class="hero-actions">
            <a href="#products" class="button-primary">Shop Products</a>
            <a href="#categories" class="button-secondary">Browse Categories</a>
			<a href="/store/<?= htmlspecialchars($store['slug']) ?>/cart" class="button-secondary">
				Cart <?= (int) ($cartQuantity ?? 0) > 0 ? '(' . htmlspecialchars((string) $cartQuantity) . ')' : '' ?>
			</a>
			<a href="/store/<?= htmlspecialchars($store['slug']) ?>/track" class="button-secondary">
				Track Order
			</a>
		</div>
    </div>
</header>

<main class="storefront-container">

    <section id="categories" class="storefront-section">
        <div class="section-header">
            <h2>Shop by Category</h2>
            <p>Browse product categories available in this store.</p>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <?php
						$categoryUrl = '/store/'
							. rawurlencode((string) $store['slug'])
							. '/category/'
							. rawurlencode((string) $category['slug']);
					?>

						<a href="<?= htmlspecialchars($categoryUrl) ?>" class="category-card category-card-link">
						<h3><?= htmlspecialchars($category['name']) ?></h3>

						<p>
							<?= htmlspecialchars($category['description'] ?: 'Explore products in this category.') ?>
						</p>

						<span>
							<?= htmlspecialchars((string) $category['product_count']) ?>
							product<?= (int) $category['product_count'] === 1 ? '' : 's' ?>
						</span>
					</a>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
                <p>No categories are available yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (! empty($featuredProducts)): ?>
        <section class="storefront-section">
            <div class="section-header">
                <h2>Featured Products</h2>
                <p>Highlighted products from <?= htmlspecialchars($store['name']) ?>.</p>
            </div>

            <div class="product-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php include BASE_PATH . '/app/Views/storefront/partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section id="products" class="storefront-section">
        <div class="section-header">
            <h2>All Products</h2>
            <p>Visible products currently published for this storefront.</p>
        </div>

        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php include BASE_PATH . '/app/Views/storefront/partials/product-card.php'; ?>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
                <p>No products are published to this storefront yet.</p>
            <?php endif; ?>
        </div>
    </section>

</main>
