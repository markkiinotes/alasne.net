<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Track inventory changes caused by orders and future stock adjustments.</p>
</section>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($_SESSION['inventory_success']); ?>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['inventory_error']); ?>
<?php endif; ?>

<div class="panel">
    <div class="table-header">
        <h2>Inventory Movements</h2>
		<a href="/admin/inventory/adjust" class="button-muted">Adjust Inventory</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Store</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Type</th>
                <th>Quantity Changed</th>
                <th>Balance After</th>
                <th>Order</th>
                <th>Note</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= htmlspecialchars($movement['created_at'] ?? '') ?></td>
                    <td><?= htmlspecialchars($movement['store_name']) ?></td>
                    <td><?= htmlspecialchars($movement['product_name']) ?></td>
                    <td><?= htmlspecialchars($movement['product_sku'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($movement['type']) ?></td>
                    <td>
                        <span class="badge <?= (int) $movement['quantity'] < 0 ? 'badge-danger' : 'badge-success' ?>">
                            <?= htmlspecialchars((string) $movement['quantity']) ?>
                        </span>
                    </td>
                    <td>
						<strong>
								<?= htmlspecialchars((string) $movement['balance_after']) ?>
						</strong>
					</td>
								
                    <td><?= htmlspecialchars($movement['order_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($movement['note'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="9">No inventory movements have been recorded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>