<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a product and assign it to a store.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['products_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/products">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Store</label>

            <select name="store_id" required>
                <option value="">Select Store</option>

                <?php foreach ($stores as $store): ?>
                    <option value="<?= htmlspecialchars((string) $store['id']) ?>">
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
		<div class="form-group">
			<label>Categories</label>

			<div class="checkbox-grid">
				<?php foreach ($categories as $category): ?>
					<label class="checkbox-item">
						<input
							type="checkbox"
							name="categories[]"
							value="<?= htmlspecialchars((string) $category['id']) ?>"
						>

						<span>
							<strong><?= htmlspecialchars($category['name']) ?></strong>
							<small>
								<?= htmlspecialchars($category['store_name']) ?>
								·
								<?= htmlspecialchars($category['slug']) ?>
							</small>
						</span>
					</label>
				<?php endforeach; ?>

				<?php if (empty($categories)): ?>
					<p>No active categories found. Create a category first.</p>
				<?php endif; ?>
			</div>

			<small class="form-help">
				Select categories from the same store as the product.
			</small>
		</div>

        <div class="form-group">
            <label>Product Name</label>
            <input type="text" name="name" required placeholder="Wireless Bluetooth Headphones">
        </div>
		<hr>

		<h2>Product Image</h2>

		<div class="form-group">
			<label>Primary Image URL</label>
			<input
				type="url"
				name="image_url"
				placeholder="https://example.com/product-image.jpg"
			>

			<small class="form-help">
				Use an external supplier image URL for now. Local uploads can be added later.
			</small>
		</div>

		<div class="form-group">
			<label>Image Alt Text</label>
			<input
				type="text"
				name="image_alt_text"
				maxlength="255"
				placeholder="Black wireless headphones with charging case"
			>

			<small class="form-help">
				Describes the image for accessibility and SEO.
			</small>
		</div>
		<hr>

		<h2>Storefront Publishing</h2>

		<div class="form-group">
			<label class="checkbox-item">
				<input
					type="checkbox"
					name="is_visible"
					value="1"
					checked
				>

				<span>
					<strong>Visible on storefront</strong>
					<small>Allow this product to appear on customer-facing store pages.</small>
				</span>
			</label>
		</div>

		<div class="form-group">
			<label class="checkbox-item">
				<input
					type="checkbox"
					name="is_featured"
					value="1"
				>

				<span>
					<strong>Featured product</strong>
					<small>Mark this product for featured sections on storefront pages.</small>
				</span>
			</label>
		</div>

		<div class="form-group">
			<label>Sort Order</label>
			<input
				type="number"
				name="sort_order"
				value="0"
				min="0"
			>

			<small class="form-help">
				Lower numbers can appear first when products are sorted manually.
			</small>
		</div>
		
        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" required placeholder="wireless-bluetooth-headphones">
        </div>

        <div class="form-group">
            <label>SKU</label>
            <input type="text" name="sku" placeholder="WBH-001">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="5" placeholder="Product description..."></textarea>
        </div>
		<hr>

		<h2>SEO Settings</h2>

		<div class="form-group">
			<label>SEO Title</label>
			<input
				type="text"
				name="meta_title"
				maxlength="255"
				placeholder="Best Wireless Headphones for Everyday Use"
			>

			<small class="form-help">
				This can be used as the product page title in search results.
			</small>
		</div>

		<div class="form-group">
			<label>SEO Description</label>
			<textarea
				name="meta_description"
				rows="3"
				placeholder="Shop high-quality wireless headphones with long battery life, clear sound, and comfortable fit."
			></textarea>

			<small class="form-help">
				This can be used as the product page description for search engines.
			</small>
		</div>

		<div class="form-group">
			<label>SEO Keywords</label>
			<textarea
				name="seo_keywords"
				rows="2"
				placeholder="wireless headphones, bluetooth headphones, audio accessories"
			></textarea>

			<small class="form-help">
				Internal keyword notes for future AI listing generation and catalog automation.
			</small>
		</div>
		

        <div class="form-row">
            <div class="form-group">
                <label>Price</label>
                <input type="number" step="0.01" min="0" name="price" value="0.00">
            </div>

            <div class="form-group">
                <label>Cost</label>
                <input type="number" step="0.01" min="0" name="cost" value="0.00">
            </div>
        </div>

        <div class="form-row">
			<div class="form-group">
				<label>Inventory Quantity</label>
				<input type="number" min="0" name="inventory_quantity" value="0">
			</div>

			<div class="form-group">
				<label>Low Stock Threshold</label>
				<input type="number" min="0" name="low_stock_threshold" value="5">
			</div>
		</div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Create Product
            </button>

            <a href="/admin/products" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>