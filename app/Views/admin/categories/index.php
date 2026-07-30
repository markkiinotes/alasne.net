<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manage store-specific product categories for catalog organization.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['categories_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['categories_error']); ?>
<?php endif; ?>

<div class="panel filter-panel">
    <form method="GET" action="/admin/categories" class="filter-form">

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

        <div class="filter-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>

            <a href="/admin/categories" class="button-muted">
                Reset
            </a>
        </div>

    </form>
</div>

<div class="panel">
    <div class="table-header">
        <h2>Product Categories</h2>

        <a href="/admin/categories/create" class="button-muted">Create Category</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Store</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= htmlspecialchars($category['name']) ?></td>
                    <td><?= htmlspecialchars($category['store_name']) ?></td>
                    <td><?= htmlspecialchars($category['slug']) ?></td>
                    <td><?= htmlspecialchars($category['description'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $category['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?>">
                            <?= htmlspecialchars($category['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($category['created_at'] ?? '') ?></td>
                    <td>
                        <a
                            href="/admin/categories/<?= htmlspecialchars((string) $category['id']) ?>/edit"
                            class="table-link"
                        >
                            Edit
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="7">No categories have been created yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>