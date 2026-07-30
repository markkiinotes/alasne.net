<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Edit role details and permission assignments.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['roles_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/roles/<?= htmlspecialchars((string) $role['id']) ?>">

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
                value="<?= htmlspecialchars($role['name']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input
                type="text"
                name="slug"
                required
                value="<?= htmlspecialchars($role['slug']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Description</label>
            <input
                type="text"
                name="description"
                value="<?= htmlspecialchars($role['description'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label>Permissions</label>

            <div class="checkbox-grid">
                <?php foreach ($permissions as $permission): ?>
                    <?php $permissionId = (int) $permission['id']; ?>

                    <label class="checkbox-item">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="<?= htmlspecialchars((string) $permissionId) ?>"
                            <?= in_array($permissionId, $selectedPermissions, true) ? 'checked' : '' ?>
                        >

                        <span>
                            <strong><?= htmlspecialchars($permission['slug']) ?></strong>
                            <small><?= htmlspecialchars($permission['description'] ?? '') ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Changes
            </button>

            <a href="/admin/roles" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>