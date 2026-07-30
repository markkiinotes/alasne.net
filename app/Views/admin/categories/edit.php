<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Edit category details and store assignment.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['categories_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/categories/<?= htmlspecialchars((string) $category['id']) ?>">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Store</label>

            <select name="store_id" required>
                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= htmlspecialchars((string) $store['id']) ?>"
                        <?= (int) $category['store_id'] === (int) $store['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Category Name</label>
            <input
                type="text"
                name="name"
                required
                value="<?= htmlspecialchars($category['name']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input
                type="text"
                name="slug"
                required
                value="<?= htmlspecialchars($category['slug']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Changes
            </button>

            <a href="/admin/categories" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>