<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a simple one-product order.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['orders_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/orders">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Customer</label>

            <select name="customer_id" required>
                <option value="">Select Customer</option>

                <?php foreach ($customers as $customer): ?>
                    <option value="<?= htmlspecialchars((string) $customer['id']) ?>">
                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' — ' . $customer['store_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Product</label>

            <select name="product_id" required>
                <option value="">Select Product</option>

                <?php foreach ($products as $product): ?>
                    <option value="<?= htmlspecialchars((string) $product['id']) ?>">
                        <?= htmlspecialchars($product['name'] . ' — ' . $product['store_name'] . ' — $' . number_format((float) $product['price'], 2)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <small class="form-help">
                Customer and product must belong to the same store.
            </small>
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" value="1" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Shipping</label>
                <input type="number" step="0.01" min="0" name="shipping_total" value="0.00">
            </div>

            <div class="form-group">
                <label>Tax</label>
                <input type="number" step="0.01" min="0" name="tax_total" value="0.00">
            </div>
        </div>

        <div class="form-group">
            <label>Discount</label>
            <input type="number" step="0.01" min="0" name="discount_total" value="0.00">
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Create Order
            </button>

            <a href="/admin/orders" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>