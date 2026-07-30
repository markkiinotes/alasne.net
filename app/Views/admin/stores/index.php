<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage connected stores, storefronts, and future drop-shipping channels.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['stores_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['stores_error']); ?>
<?php endif; ?>

<div class="panel">
    <div class="table-header">
        <h2>Stores</h2>

        <a href="/admin/stores/create" class="button-muted">Create Store</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Domain</th>
                <th>Platform</th>
                <th>Status</th>
                <th>Created</th>
				<th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($stores as $store): ?>
                <tr>
                    <td><?= htmlspecialchars($store['name']) ?></td>
                    <td><?= htmlspecialchars($store['slug']) ?></td>
                    <td><?= htmlspecialchars($store['domain'] ?? 'Not assigned') ?></td>
                    <td><?= htmlspecialchars($store['platform']) ?></td>
                    <td>
                        <span class="badge <?= $store['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($store['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($store['created_at'] ?? '') ?></td>
					<td>
						<a
							href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>"
							class="table-link"
						>
							View
						</a>

						&nbsp;|&nbsp;

						<a
							href="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>/edit"
							class="table-link"
						>
							Edit
						</a>
					</td>
				</tr>
            <?php endforeach; ?>

            <?php if (empty($stores)): ?>
                <tr>
                    <td colspan="7">No stores have been created yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>