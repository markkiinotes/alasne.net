<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Edit user profile, status, and role assignment.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['users_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/users/<?= htmlspecialchars((string) $user['id']) ?>">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Name</label>
            <input
                type="text"
                name="name"
                required
                value="<?= htmlspecialchars($user['name']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Email</label>
            <input
                type="email"
                name="email"
                required
                value="<?= htmlspecialchars($user['email']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role" required>
                <?php foreach ($roles as $role): ?>
                    <option
                        value="<?= htmlspecialchars($role['slug']) ?>"
                        <?= $user['role_slug'] === $role['slug'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Changes
            </button>

            <a href="/admin/users" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>