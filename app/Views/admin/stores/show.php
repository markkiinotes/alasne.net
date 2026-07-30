<section class="page-header">
    <h1><?= htmlspecialchars($store['name']) ?></h1>
    <p>
        <?= htmlspecialchars($store['domain'] ?? 'No domain assigned') ?>
        ·
        <?= htmlspecialchars($store['platform']) ?>
        ·
        <?= htmlspecialchars($store['status']) ?>
    </p>
</section>

<section class="card-grid metrics-grid">
    <div class="metric-card">
        <span>Products</span>
        <strong><?= htmlspecialchars((string) ($stats['products'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Customers</span>
        <strong><?= htmlspecialchars((string) ($stats['customers'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Orders</span>
        <strong><?= htmlspecialchars((string) ($stats['orders'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Revenue</span>
        <strong>$<?= htmlspecialchars(number_format((float) ($stats['revenue'] ?? 0), 2)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Low Stock</span>
        <strong><?= htmlspecialchars((string) ($stats['low_stock_products'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Out of Stock</span>
        <strong><?= htmlspecialchars((string) ($stats['out_of_stock_products'] ?? 0)) ?></strong>
    </div>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="table-header">
            <h2>Recent Products</h2>

            <a href="/admin/products?store_id=<?= htmlspecialchars((string) $store['id']) ?>" class="button-muted">
                View All
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>Inventory</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                        <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                        <td>$<?= htmlspecialchars(number_format((float) $product['price'], 2)) ?></td>
                        <td><?= htmlspecialchars((string) $product['inventory_quantity']) ?></td>
                        <td><?= htmlspecialchars($product['status']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5">No products for this store yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <div class="table-header">
            <h2>Recent Customers</h2>

            <a href="/admin/customers?store_id=<?= htmlspecialchars((string) $store['id']) ?>" class="button-muted">
                View All
            </a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                        <td><?= htmlspecialchars($customer['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($customer['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($customer['status']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="4">No customers for this store yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="panel">
    <div class="table-header">
        <h2>Recent Orders</h2>

        <a href="/admin/orders?store_id=<?= htmlspecialchars((string) $store['id']) ?>" class="button-muted">
            View All
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['grand_total'], 2)) ?></td>
                    <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
                    <td>
                        <a href="/admin/orders/<?= htmlspecialchars((string) $order['id']) ?>" class="table-link">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6">No orders for this store yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="form-actions">
	<a
		href="/store/<?= htmlspecialchars($store['slug']) ?>"
		class="button-primary"
		target="_blank"
	>
		Preview Storefront
	</a>
    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/shipping-methods"
        class="button-primary"
    >
        Manage Shipping Methods
    </a>

    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/tax-rules"
        class="button-primary"
    >
        Manage Tax Rules
    </a>

    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/payment-methods"
        class="button-primary"
    >
        Manage Payment Methods
    </a>

    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/return-policy"
        class="button-primary"
    >
        Manage Return Policy
    </a>

    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/carrier-integration"
        class="button-primary"
    >
        Manage Carrier Integration
    </a>

    <a
        href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/edit"
        class="button-primary"
    >
        Edit Store
    </a>

    <a href="/admin/stores" class="button-muted">
        Back to Stores
    </a>
</div>