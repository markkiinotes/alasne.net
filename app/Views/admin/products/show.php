<section class="page-header">
    <h1><?= htmlspecialchars($product['name']) ?></h1>
    <p>
        SKU:
        <?= htmlspecialchars($product['sku'] ?? '—') ?>
        ·
        Status:
        <?= htmlspecialchars($product['status']) ?>
    </p>
</section>

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

<section class="card-grid metrics-grid">
    <div class="metric-card">
        <span>Price</span>
        <strong>$<?= htmlspecialchars(number_format((float) $product['price'], 2)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Cost</span>
        <strong>$<?= htmlspecialchars(number_format((float) $product['cost'], 2)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Inventory</span>
        <strong><?= htmlspecialchars((string) $inventory) ?></strong>
    </div>

    <div class="metric-card">
        <span>Low Stock Threshold</span>
        <strong><?= htmlspecialchars((string) $threshold) ?></strong>
    </div>
</section>

<section class="panel product-image-panel">
    <h2>Product Image</h2>

    <?php if (! empty($product['image_url'])): ?>
        <img
            src="<?= htmlspecialchars($product['image_url']) ?>"
            alt="<?= htmlspecialchars($product['image_alt_text'] ?: $product['name']) ?>"
            class="product-detail-image"
        >

        <p class="form-help">
            <?= htmlspecialchars($product['image_alt_text'] ?: 'No image alt text entered.') ?>
        </p>
    <?php else: ?>
        <p>No product image has been added yet.</p>
    <?php endif; ?>
</section>

<div class="detail-grid">
    <div class="panel">
        <h2>Product Details</h2>

        <table class="detail-table">
            <tr>
                <th>Name</th>
                <td><?= htmlspecialchars($product['name']) ?></td>
            </tr>

            <tr>
                <th>Slug</th>
                <td><?= htmlspecialchars($product['slug']) ?></td>
            </tr>

            <tr>
                <th>SKU</th>
                <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
            </tr>
			<tr>
				<th>Image Alt Text</th>
				<td><?= htmlspecialchars($product['image_alt_text'] ?? '—') ?></td>
			</tr>
			<tr>
				<th>Categories</th>
				<td>
					<?php foreach ($categories as $category): ?>
						<span class="badge badge-neutral">
							<?= htmlspecialchars($category['name']) ?>
						</span>
					<?php endforeach; ?>

					<?php if (empty($categories)): ?>
						Uncategorized
					<?php endif; ?>
				</td>
			</tr>

            <tr>
                <th>Stock Status</th>
                <td>
                    <span class="badge <?= $stockClass ?>">
                        <?= htmlspecialchars($stockLabel) ?>
                    </span>
                </td>
            </tr>

            <tr>
                <th>Status</th>
                <td><?= htmlspecialchars($product['status']) ?></td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <h2>Description</h2>

        <p>
            <?= nl2br(htmlspecialchars($product['description'] ?? 'No description entered.')) ?>
        </p>
    </div>	
</div>
<section class="panel">
    <h2>Storefront Publishing</h2>

    <table class="detail-table">
        <tr>
            <th>Visible</th>
            <td>
                <?php if ((int) ($product['is_visible'] ?? 1) === 1): ?>
                    <span class="badge badge-success">Visible on storefront</span>
                <?php else: ?>
                    <span class="badge badge-neutral">Hidden from storefront</span>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th>Featured</th>
            <td>
                <?php if ((int) ($product['is_featured'] ?? 0) === 1): ?>
                    <span class="badge badge-warning">Featured product</span>
                <?php else: ?>
                    <span class="badge badge-neutral">Not featured</span>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th>Sort Order</th>
            <td><?= htmlspecialchars((string) ($product['sort_order'] ?? 0)) ?></td>
        </tr>
    </table>
</section>
<section class="panel">
    <h2>SEO Settings</h2>

    <table class="detail-table">
        <tr>
            <th>SEO Title</th>
            <td><?= htmlspecialchars($product['meta_title'] ?? '—') ?></td>
        </tr>

        <tr>
            <th>SEO Description</th>
            <td><?= nl2br(htmlspecialchars($product['meta_description'] ?? '—')) ?></td>
        </tr>

        <tr>
            <th>SEO Keywords</th>
            <td><?= nl2br(htmlspecialchars($product['seo_keywords'] ?? '—')) ?></td>
        </tr>
    </table>
</section>
<section class="panel">
    <div class="table-header">
        <h2>Recent Inventory Movements</h2>

        <a href="/admin/inventory" class="button-muted">
            View Ledger
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Quantity Changed</th>
                <th>Balance After</th>
                <th>Order</th>
                <th>Note</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= htmlspecialchars($movement['created_at'] ?? '') ?></td>
                    <td><?= htmlspecialchars($movement['type']) ?></td>
                    <td>
                        <span class="badge <?= (int) $movement['quantity'] < 0 ? 'badge-danger' : 'badge-success' ?>">
                            <?= htmlspecialchars((string) $movement['quantity']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars((string) $movement['balance_after']) ?></td>
                    <td><?= htmlspecialchars($movement['order_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($movement['note'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="6">No inventory movements recorded for this product yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Recent Orders</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Line Total</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td><?= htmlspecialchars((string) $order['quantity']) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['unit_price'], 2)) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['line_total'], 2)) ?></td>
                    <td>
                        <a href="/admin/orders/<?= htmlspecialchars((string) $order['id']) ?>" class="table-link">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7">No orders found for this product yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="form-actions">
    <a href="/admin/products/<?= htmlspecialchars((string) $product['id']) ?>/edit" class="button-primary">
        Edit Product
    </a>

    <a href="/admin/products" class="button-muted">
        Back to Products
    </a>
</div>