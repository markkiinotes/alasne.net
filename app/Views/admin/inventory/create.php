<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Manually adjust product inventory and record the change in the ledger.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['inventory_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/inventory/adjust">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Product</label>

            <select name="product_id" required>
                <option value="">Select Product</option>

                <?php foreach ($products as $product): ?>
                    <option value="<?= htmlspecialchars((string) $product['id']) ?>">
                        <?= htmlspecialchars(
                            $product['name']
                            . ' — '
                            . $product['store_name']
                            . ' — Current Stock: '
                            . $product['inventory_quantity']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Adjustment Type</label>

            <select name="type" required>
                <option value="manual_adjustment">Manual Adjustment</option>
                <option value="restock">Restock</option>
                <option value="correction">Correction</option>
                <option value="damaged">Damaged</option>
                <option value="lost">Lost</option>
                <option value="return">Return</option>
            </select>
        </div>

        <div class="form-group">
            <label>Quantity Change</label>

            <input
                type="number"
                name="quantity_change"
                required
                placeholder="Example: 10 or -2"
            >

            <small class="form-help">
                Use a positive number to add stock, such as 10. Use a negative number to remove stock, such as -2.
            </small>
        </div>

        <div class="form-group">
            <label>Note</label>

            <textarea
                name="note"
                rows="4"
                placeholder="Example: Restocked from supplier shipment."
            ></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Save Adjustment
            </button>

            <a href="/admin/inventory" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>