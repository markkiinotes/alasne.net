<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage platform users, operators, administrators, and future store staff.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['users_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['users_error']); ?>
<?php endif; ?>

<div class="panel">
    <div class="table-header">
        <h2>Platform Users</h2>

        <a href="/admin/users/create" class="button-muted">Create User</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created</th>
				<th>Actions</th>
			</tr>
        </thead>

        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['roles']) ?></td>
                    <td>
                        <span class="badge badge-success">
                            <?= htmlspecialchars($user['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($user['last_login_at'] ?? 'Never') ?></td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
					<td>
						<a
							href="/admin/users/<?= htmlspecialchars((string) $user['id']) ?>/edit"
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