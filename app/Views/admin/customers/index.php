<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage customers across stores and future order history.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['customers_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['customers_error']); ?>
<?php endif; ?>

<div class="panel filter-panel">
    <form method="GET" action="/admin/customers" class="filter-form">

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
            <label>Status</label>

            <select name="status">
                <option value="">All Statuses</option>

                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="form-group">
            <label>Search</label>

            <input
                type="text"
                name="search"
                placeholder="Name, email, or phone"
                value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>"
            >
        </div>

        <div class="filter-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>

            <a href="/admin/customers" class="button-muted">
                Reset
            </a>
        </div>

    </form>
</div>

<div class="panel">
    <div class="table-header">
        <h2>Customers</h2>

        <a href="/admin/customers/create" class="button-muted">Create Customer</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Store</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Location</th>
                <th>Status</th>
                <th>Created</th>
				<th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                    </td>
                    <td><?= htmlspecialchars($customer['store_name']) ?></td>
                    <td><?= htmlspecialchars($customer['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($customer['phone'] ?? '—') ?></td>
                    <td>
                        <?= htmlspecialchars(trim(($customer['city'] ?? '') . ', ' . ($customer['state'] ?? ''), ', ') ?: '—') ?>
                    </td>
                    <td>
                        <span class="badge <?= $customer['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($customer['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($customer['created_at'] ?? '') ?></td>
					<td>
						<a
							href="/admin/customers/<?= htmlspecialchars((string) $customer['id']) ?>"
							class="table-link"
						>
							View
						</a>

						&nbsp;|&nbsp;

						<a
							href="/admin/customers/<?= htmlspecialchars((string) $customer['id']) ?>/edit"
							class="table-link"
						>
							Edit
						</a>
					</td>				
				</tr>
            <?php endforeach; ?>

            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="8">No customers have been created yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>