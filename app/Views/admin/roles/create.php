<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a new access role and assign permissions.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['roles_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/roles">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Store Manager">
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" required placeholder="store_manager">
        </div>

        <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" placeholder="Can manage assigned stores.">
        </div>

        <div class="form-group">
            <label>Permissions</label>

            <div class="checkbox-grid">
                <?php foreach ($permissions as $permission): ?>
                    <label class="checkbox-item">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="<?= htmlspecialchars((string) $permission['id']) ?>"
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
                Create Role
            </button>

            <a href="/admin/roles" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>