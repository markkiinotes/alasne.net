<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a customer and assign them to a store.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['customers_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/customers">

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

        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required>
            </div>

            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
        </div>

        <div class="form-group">
            <label>Address Line 1</label>
            <input type="text" name="address_line_1">
        </div>

        <div class="form-group">
            <label>Address Line 2</label>
            <input type="text" name="address_line_2">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city">
            </div>

            <div class="form-group">
                <label>State</label>
                <input type="text" name="state">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="postal_code">
            </div>

            <div class="form-group">
                <label>Country</label>
                <input type="text" name="country" value="United States">
            </div>
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
                Create Customer
            </button>

            <a href="/admin/customers" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>