<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage customer orders across stores.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['orders_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['orders_error']); ?>
<?php endif; ?>

<div class="panel filter-panel">
    <form method="GET" action="/admin/orders" class="filter-form">

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
            <label>Customer</label>

            <select name="customer_id">
                <option value="">All Customers</option>

                <?php foreach ($customers as $customer): ?>
                    <option
                        value="<?= htmlspecialchars((string) $customer['id']) ?>"
                        <?= (string) ($filters['customer_id'] ?? '') === (string) $customer['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(
                            $customer['first_name']
                            . ' '
                            . $customer['last_name']
                            . ' — '
                            . $customer['store_name']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status">
                <option value="">All Statuses</option>

                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option value="paid" <?= ($filters['status'] ?? '') === 'paid' ? 'selected' : '' ?>>
                    Paid
                </option>

                <option value="processing" <?= ($filters['status'] ?? '') === 'processing' ? 'selected' : '' ?>>
                    Processing
                </option>

                <option value="shipped" <?= ($filters['status'] ?? '') === 'shipped' ? 'selected' : '' ?>>
                    Shipped
                </option>

                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>
                    Cancelled
                </option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>

            <a href="/admin/orders" class="button-muted">
                Reset
            </a>
        </div>

    </form>
</div>

<section class="panel form-panel">
    <div class="table-header">
		<h2>Search Orders</h2>

		<div class="table-actions">
			<a
				href="/admin/orders/export?<?= htmlspecialchars(http_build_query($filters ?? [])) ?>"
				class="button-primary"
			>
				Export CSV
			</a>

			<a href="/admin/orders" class="button-muted">
				Clear Filters
			</a>
		</div>
	</div>

    <form method="GET" action="/admin/orders" class="filter-form">
        <div class="form-grid">

            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
                    placeholder="Order number, customer, or email"
                >
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All statuses</option>

                    <?php foreach ($statuses as $status): ?>
                        <option
                            value="<?= htmlspecialchars($status) ?>"
                            <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Store</label>
                <select name="store_id">
                    <option value="">All stores</option>

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
                <label>Date From</label>
                <input
                    type="date"
                    name="date_from"
                    value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label>Date To</label>
                <input
                    type="date"
                    name="date_to"
                    value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>"
                >
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>
        </div>
    </form>
</section>

<br />

<div class="panel">
    <div class="table-header">
        <h2>Orders</h2>

        <a href="/admin/orders/create" class="button-muted">Create Order</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Store</th>
                <th>Customer</th>
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
                    <td><?= htmlspecialchars($order['store_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td>
                        <span class="badge <?= $order['status'] === 'paid' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['subtotal'], 2)) ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) $order['grand_total'], 2)) ?></td>
                    <td><?= htmlspecialchars($order['placed_at'] ?? $order['created_at'] ?? '') ?></td>
               
					<td>
						<a
							href="/admin/orders/<?= htmlspecialchars((string) $order['id']) ?>"
							class="table-link"
						>
							View
						</a>
					</td>
			   </tr>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8">No orders have been created yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>