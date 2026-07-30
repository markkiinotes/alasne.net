<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage platform roles and access levels.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['roles_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['roles_error']); ?>
<?php endif; ?>

<div class="panel">
    <div class="table-header">
        <h2>Platform Roles</h2>

       <a href="/admin/roles/create" class="button-muted">Create Role</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
				<th>Permissions</th>
				<th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($roles as $role): ?>
               <tr>
					<td><?= htmlspecialchars($role['name']) ?></td>
					<td><?= htmlspecialchars($role['slug']) ?></td>
					<td><?= htmlspecialchars($role['description'] ?? '') ?></td>
					<td>
						<span class="permission-list">
							<?= htmlspecialchars($role['permissions']) ?>
						</span>
					</td>
					<td>
						<a
							href="/admin/roles/<?= htmlspecialchars((string) $role['id']) ?>/edit"
							class="table-link"
						>
							Edit
						</a>
					</td>
				</tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>