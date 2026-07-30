<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage products, pricing, inventory, and store assignments.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['products_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['products_error']); ?>
<?php endif; ?>

<div class="panel filter-panel">
    <form method="GET" action="/admin/products" class="filter-form">

        <div class="form-group">
            <label>Store</label>

            <select name="store_id">
                <option value="">All Stores</option>

                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= htmlspecialchars((string) $store['id']) ?>"
                        <?= (string) ($filters['store_id'] ?? '') === (string) $store['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
		
		<div class="form-group">
			<label>Category</label>

			<select name="category_id">
				<option value="">All Categories</option>

				<?php foreach ($categories as $category): ?>
					<option
						value="<?= htmlspecialchars((string) $category['id']) ?>"
						<?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>
					>
						<?= htmlspecialchars(
							$category['name']
							. ' — '
							. $category['store_name']
						) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

        <div class="form-group">
            <label>Status</label>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>
                    Draft
                </option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                    Active
                </option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="form-group">
            <label>Stock</label>

            <select name="stock">
                <option value="">All Stock</option>
                <option value="in_stock" <?= ($filters['stock'] ?? '') === 'in_stock' ? 'selected' : '' ?>>
                    In Stock
                </option>
                <option value="low_stock" <?= ($filters['stock'] ?? '') === 'low_stock' ? 'selected' : '' ?>>
                    Low Stock
                </option>
                <option value="out_of_stock" <?= ($filters['stock'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>
                    Out of Stock
                </option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>

            <a href="/admin/products" class="button-muted">
                Reset
            </a>
        </div>

    </form>
</div>

<div class="panel">
    <div class="table-header">
        <h2>Products</h2>

        <a href="/admin/products/create" class="button-muted">Create Product</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
				<th>Image</th>
                <th>Store</th>
				<th>Categories</th>
                <th>SKU</th>
                <th>Price</th>
                <th>Cost</th>
                <th>Inventory</th>
				<th>Stock</th>
                <th>Status</th>
				<th>Publishing</th>
                <th>Created</th>
				<th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
						<?php if (! empty($product['image_url'])): ?>
							<img
								src="<?= htmlspecialchars($product['image_url']) ?>"
								alt="<?= htmlspecialchars($product['image_alt_text'] ?: $product['name']) ?>"
								class="product-thumb"
							>
						<?php else: ?>
							<span class="badge badge-neutral">No image</span>
						<?php endif; ?>
					</td>					
					<td><?= htmlspecialchars($product['store_name']) ?></td>
					<td><?= htmlspecialchars($product['categories'] ?? 'Uncategorized') ?></td>
                    <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $product['price'], 2)) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $product['cost'], 2)) ?></td>
                    <td>
						<?php $inventory = (int) $product['inventory_quantity']; ?>

						<span class="badge <?= $inventory > 0 ? 'badge-success' : 'badge-danger' ?>">
							<?= htmlspecialchars((string) $inventory) ?>
						</span>
					</td>
					<td>
						<?php
							$inventory = (int) $product['inventory_quantity'];
							$threshold = (int) ($product['low_stock_threshold'] ?? 5);

							if ($inventory <= 0) {
								$stockLabel = 'Out of Stock';
								$stockClass = 'badge-danger';
							} elseif ($inventory <= $threshold) {
								$stockLabel = 'Low Stock';
								$stockClass = 'badge-warning';
							} else {
								$stockLabel = 'In Stock';
								$stockClass = 'badge-success';
							}
						?>

						<span class="badge <?= $stockClass ?>">
							<?= htmlspecialchars($stockLabel) ?>
						</span>
					</td>
                    <td>
                        <span class="badge <?= $product['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($product['status']) ?>
                        </span>
                    </td>
					<td>
						<?php if ((int) ($product['is_visible'] ?? 1) === 1): ?>
							<span class="badge badge-success">Visible</span>
						<?php else: ?>
							<span class="badge badge-neutral">Hidden</span>
						<?php endif; ?>

						<?php if ((int) ($product['is_featured'] ?? 0) === 1): ?>
							<span class="badge badge-warning">Featured</span>
						<?php endif; ?>

						<span class="badge badge-neutral">
							Sort: <?= htmlspecialchars((string) ($product['sort_order'] ?? 0)) ?>
						</span>
					</td>
                    <td><?= htmlspecialchars($product['created_at'] ?? '') ?></td>
					<td>
						<a
							href="/admin/products/<?= htmlspecialchars((string) $product['id']) ?>"
							class="table-link"
						>
							View
						</a>

						&nbsp;|&nbsp;

						<a
							href="/admin/products/<?= htmlspecialchars((string) $product['id']) ?>/edit"
							class="table-link"
						>
							Edit
						</a>
					</td>
				</tr>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="13">No products have been created yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>