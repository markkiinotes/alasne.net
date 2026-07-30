<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a new store record for the platform.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['stores_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/stores">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Store Name</label>
            <input type="text" name="name" required placeholder="Alasne Demo Store">
        </div>

        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" required placeholder="alasne-demo-store">
        </div>

        <div class="form-group">
            <label>Domain</label>
            <input type="text" name="domain" placeholder="store.example.com">
        </div>

        <div class="form-group">
            <label>Platform</label>

            <select name="platform" required>
                <option value="custom">Custom</option>
                <option value="shopify">Shopify</option>
                <option value="woocommerce">WooCommerce</option>
                <option value="amazon">Amazon</option>
                <option value="ebay">eBay</option>
            </select>
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
                Create Store
            </button>

            <a href="/admin/stores" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>