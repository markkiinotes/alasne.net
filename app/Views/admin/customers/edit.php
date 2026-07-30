<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Edit customer profile, contact details, address, and store assignment.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['customers_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/customers/<?= htmlspecialchars((string) $customer['id']) ?>">

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
                        <?= (int) $customer['store_id'] === (int) $store['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input
                    type="text"
                    name="first_name"
                    required
                    value="<?= htmlspecialchars($customer['first_name']) ?>"
                >
            </div>

            <div class="form-group">
                <label>Last Name</label>
                <input
                    type="text"
                    name="last_name"
                    required
                    value="<?= htmlspecialchars($customer['last_name']) ?>"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label>Address Line 1</label>
            <input
                type="text"
                name="address_line_1"
                value="<?= htmlspecialchars($customer['address_line_1'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label>Address Line 2</label>
            <input
                type="text"
                name="address_line_2"
                value="<?= htmlspecialchars($customer['address_line_2'] ?? '') ?>"
            >
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>City</label>
                <input
                    type="text"
                    name="city"
                    value="<?= htmlspecialchars($customer['city'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label>State</label>
                <input
                    type="text"
                    name="state"
                    value="<?= htmlspecialchars($customer['state'] ?? '') ?>"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Postal Code</label>
                <input
                    type="text"
                    name="postal_code"
                    value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label>Country</label>
                <input
                    type="text"
                    name="country"
                    value="<?= htmlspecialchars($customer['country'] ?? 'United States') ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="active" <?= $customer['status'] === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= $customer['status'] === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Changes
            </button>

            <a href="/admin/customers" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>