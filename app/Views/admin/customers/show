<section class="page-header">
    <h1><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
    <p>
        <?= htmlspecialchars($customer['store_name']) ?>
        ·
        <?= htmlspecialchars($customer['status']) ?>
    </p>
</section>

<section class="card-grid metrics-grid">
    <div class="metric-card">
        <span>Total Orders</span>
        <strong><?= htmlspecialchars((string) ($stats['orders'] ?? 0)) ?></strong>
    </div>

    <div class="metric-card">
        <span>Total Revenue</span>
        <strong>$<?= htmlspecialchars(number_format((float) ($stats['revenue'] ?? 0), 2)) ?></strong>
    </div>
</section>

<div class="detail-grid">
    <div class="panel">
        <h2>Customer Profile</h2>

        <table class="detail-table">
            <tr>
                <th>Name</th>
                <td><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
            </tr>

            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($customer['email'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>Phone</th>
                <td><?= htmlspecialchars($customer['phone'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>Store</th>
                <td><?= htmlspecialchars($customer['store_name']) ?></td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    <span class="badge <?= $customer['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                        <?= htmlspecialchars($customer['status']) ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <h2>Address</h2>

        <table class="detail-table">
            <tr>
                <th>Address 1</th>
                <td><?= htmlspecialchars($customer['address_line_1'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>Address 2</th>
                <td><?= htmlspecialchars($customer['address_line_2'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>City</th>
                <td><?= htmlspecialchars($customer['city'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>State</th>
                <td><?= htmlspecialchars($customer['state'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>Postal Code</th>
                <td><?= htmlspecialchars($customer['postal_code'] ?? '—') ?></td>
            </tr>

            <tr>
                <th>Country</th>
                <td><?= htmlspecialchars($customer['country'] ?? '—') ?></td>
            </tr>
        </table>
    </div>
</div>

<section class="panel">
    <div class="table-header">
        <h2>Recent Orders</h2>

        <a href="/admin/orders?customer_id=<?= htmlspecialchars((string) $customer['id']) ?>" class="button-muted">
            View All
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Status</th>
                <th>Subtotal</th>
                <th>Grand Total</th>
                <th>Placed</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                    <td>
                        <span class="badge <?= $order['status'] === 'paid' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['subtotal'], 2)) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['grand_total'], 2)) ?></td>
                    <td><?= htmlspecialchars($order['placed_at'] ?? $order['created_at'] ?? '') ?></td>
                    <td>
                        <a href="/admin/orders/<?= htmlspecialchars((string) $order['id']) ?>" class="table-link">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6">No orders found for this customer yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="form-actions">
    <a href="/admin/customers/<?= htmlspecialchars((string) $customer['id']) ?>/edit" class="button-primary">
        Edit Customer
    </a>

    <a href="/admin/customers" class="button-muted">
        Back to Customers
    </a>
</div>