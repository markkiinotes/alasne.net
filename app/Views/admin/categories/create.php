<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a category for organizing products inside a store catalog.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['categories_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/categories">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Store</label>

            <select name="store_id" required>
                <option value="">Select Store</option>

                <?php foreach ($stores as $store): ?>
                    <option value="<?= htmlspecialchars((string) $store['id']) ?>">
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Category Name</label>
            <input type="text" name="name" required placeholder="Electronics">
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" required placeholder="electronics">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4" placeholder="Products in this category..."></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Create Category
            </button>

            <a href="/admin/categories" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>