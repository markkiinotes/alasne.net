<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Command center for stores, commerce, automation, and AI operations.</p>
</section>
<section class="card-grid metrics-grid">
    <div class="metric-card">
        <span>Stores</span>
        <strong><?= htmlspecialchars((string) ($stats['stores'] ?? 0)) ?></strong>
    </div>

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
        <span>Pending</span>
        <strong><?= htmlspecialchars((string) ($stats['pending_orders'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Processing</span>
        <strong><?= htmlspecialchars((string) ($stats['processing_orders'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Shipped</span>
        <strong><?= htmlspecialchars((string) ($stats['shipped_orders'] ?? 0)) ?></strong>
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

<br />
<section class="panel">
    <div class="table-header">
        <h2>Inventory Alerts</h2>

        <a href="/admin/products" class="button-muted">
            View Products
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Store</th>
                <th>SKU</th>
                <th>Inventory</th>
                <th>Threshold</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($lowStockProducts as $product): ?>
                <?php
                    $inventory = (int) $product['inventory_quantity'];
                    $threshold = (int) $product['low_stock_threshold'];

                    if ($inventory <= 0) {
                        $stockLabel = 'Out of Stock';
                        $stockClass = 'badge-danger';
                    } else {
                        $stockLabel = 'Low Stock';
                        $stockClass = 'badge-warning';
                    }
                ?>

                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['store_name']) ?></td>
                    <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                    <td><?= htmlspecialchars((string) $inventory) ?></td>
                    <td><?= htmlspecialchars((string) $threshold) ?></td>
                    <td>
                        <span class="badge <?= $stockClass ?>">
                            <?= htmlspecialchars($stockLabel) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($lowStockProducts)): ?>
                <tr>
                    <td colspan="6">No low-stock products right now.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<br />
<section class="card-grid">
    <div class="card">
        <span class="card-label">Stores</span>
        <strong>0</strong>
        <p>Active storefronts</p>
    </div>

    <div class="card">
        <span class="card-label">Products</span>
        <strong>0</strong>
        <p>Catalog items</p>
    </div>

    <div class="card">
        <span class="card-label">Orders</span>
        <strong>0</strong>
        <p>Orders processed</p>
    </div>

    <div class="card">
        <span class="card-label">Customers</span>
        <strong>0</strong>
        <p>Customer records</p>
    </div>	
</section>

<section class="dashboard-grid">
    <div class="panel">
        <h2>System Health</h2>
        <p class="status-good">Operational</p>
        <ul>
            <li>Kernel: Online</li>
            <li>Database: Connected</li>
            <li>Router: Active</li>
            <li>Migration Engine: Ready</li>
        </ul>
    </div>

    <div class="panel">
        <h2>Engines</h2>
        <ul>
            <li>CMS Engine: Planned</li>
            <li>Commerce Engine: Planned</li>
            <li>AI Engine: Planned</li>
            <li>Automation Engine: Planned</li>
        </ul>
    </div>
</section>
<br />
<section class="dashboard-grid">
    <div class="panel">
        <h2>Commander Access</h2>

        <p>
            Mission Control Access:
            <strong>
                <?= $canViewMissionControl ? 'Granted' : 'Denied' ?>
            </strong>
        </p>

        <h3>Roles</h3>
        <ul>
            <?php foreach ($roles as $role): ?>
                <li><?= htmlspecialchars($role) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="panel">
        <h2>Permissions</h2>

        <ul>
            <?php foreach ($permissions as $permission): ?>
                <li><?= htmlspecialchars($permission) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<br />
<section class="panel">
    <div class="table-header">
        <h2>Recent Order Activity</h2>

        <a href="/admin/orders" class="button-muted">
            View Orders
        </a>
    </div>

    <div class="timeline">
        <?php foreach ($recentOrderEvents as $event): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>

                <div class="timeline-content">
                    <strong>
                        <?= htmlspecialchars($event['title']) ?>
                    </strong>

                    <p>
                        <a
                            href="/admin/orders/<?= htmlspecialchars((string) $event['order_id']) ?>"
                            class="table-link"
                        >
                            <?= htmlspecialchars($event['order_number']) ?>
                        </a>

                        ·

                        <?= htmlspecialchars($event['store_name']) ?>

                        ·

                        <?= htmlspecialchars($event['customer_name']) ?>
                    </p>

                    <?php if (! empty($event['description'])): ?>
                        <p>
                            <?= htmlspecialchars($event['description']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (! empty($event['old_value']) || ! empty($event['new_value'])): ?>
                        <p class="timeline-values">
                            <?php if (! empty($event['old_value'])): ?>
                                From:
                                <strong><?= htmlspecialchars($event['old_value']) ?></strong>
                            <?php endif; ?>

                            <?php if (! empty($event['new_value'])): ?>
                                To:
                                <strong><?= htmlspecialchars($event['new_value']) ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <span>
                        <?= htmlspecialchars($event['created_at'] ?? '') ?>
                        ·
                        <?= (int) $event['is_public'] === 1 ? 'Public' : 'Internal' ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($recentOrderEvents)): ?>
            <p>No recent order activity has been recorded yet.</p>
        <?php endif; ?>
    </div>
</section>