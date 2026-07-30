<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Edit store details, platform connection, and status.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['stores_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/stores/<?= htmlspecialchars((string) $store['id']) ?>">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Store Name</label>
            <input
                type="text"
                name="name"
                required
                value="<?= htmlspecialchars($store['name']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input
                type="text"
                name="slug"
                required
                value="<?= htmlspecialchars($store['slug']) ?>"
            >
        </div>

        <div class="form-group">
            <label>Domain</label>
            <input
                type="text"
                name="domain"
                value="<?= htmlspecialchars($store['domain'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label>Platform</label>

            <select name="platform" required>
                <option value="custom" <?= $store['platform'] === 'custom' ? 'selected' : '' ?>>Custom</option>
                <option value="shopify" <?= $store['platform'] === 'shopify' ? 'selected' : '' ?>>Shopify</option>
                <option value="woocommerce" <?= $store['platform'] === 'woocommerce' ? 'selected' : '' ?>>WooCommerce</option>
                <option value="amazon" <?= $store['platform'] === 'amazon' ? 'selected' : '' ?>>Amazon</option>
                <option value="ebay" <?= $store['platform'] === 'ebay' ? 'selected' : '' ?>>eBay</option>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="active" <?= $store['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $store['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Changes
            </button>

            <a href="/admin/stores" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>