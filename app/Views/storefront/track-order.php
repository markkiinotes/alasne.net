<header class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a href="/store/<?= htmlspecialchars($store['slug']) ?>" class="back-link">
            ← Back to <?= htmlspecialchars($store['name']) ?>
        </a>

        <a href="/store/<?= htmlspecialchars($store['slug']) ?>/cart" class="cart-link">
            Cart <?= (int) ($cartQuantity ?? 0) > 0 ? '(' . htmlspecialchars((string) $cartQuantity) . ')' : '' ?>
        </a>
    </div>
</header>

<main class="storefront-container">

    <section class="category-page-hero">
        <p class="eyebrow dark-eyebrow">Order Tracking</p>

        <h1>Track Your Order</h1>

        <p>
            Enter your order number and email address to view your order status.
        </p>
    </section>

    <?php if (!empty($error)): ?>
        <div class="storefront-alert danger">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php unset($_SESSION['tracking_error']); ?>
    <?php endif; ?>

    <section class="tracking-layout">

        <div class="checkout-form-panel">
            <h2>Find Your Order</h2>

            <form method="POST" action="/store/<?= htmlspecialchars($store['slug']) ?>/track">
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= htmlspecialchars($csrf_token) ?>"
                >

                <div class="form-group">
                    <label>Order Number</label>
                    <input
                        type="text"
                        name="order_number"
                        required
                        placeholder="WEB-20260712-120000-1234"
                        value="<?= htmlspecialchars($submitted_order_number ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input
                        type="email"
                        name="email"
                        required
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($submitted_email ?? '') ?>"
                    >
                </div>

                <button type="submit" class="storefront-cart-button">
                    Track Order
                </button>
            </form>
        </div>

        <div class="tracking-help-panel">
            <h2>Where is my order number?</h2>

            <p>
                Your order number appears on the order confirmation page after checkout.
            </p>

            <p>
                It starts with <strong>WEB-</strong> for storefront orders.
            </p>
        </div>

    </section>

    <?php if (!empty($order)): ?>
        <section class="checkout-success-panel order-confirmation-panel tracking-result-panel">
					
            <p class="eyebrow dark-eyebrow">Order Found</p>
			
            <h1><?= htmlspecialchars($order['order_number']) ?></h1>

            <p>
                This order is currently marked as:
            </p>

            <div class="tracking-status-wrap">
                <span class="tracking-status <?= htmlspecialchars('status-' . $order['status']) ?>">
                    <?= htmlspecialchars($order['status']) ?>
                </span>
            </div>
			<br />
			<?php
				$receiptQuery = http_build_query([
					'order_number' => $order['order_number'] ?? '',
					'email' => $submitted_email ?? '',
				]);
				?>

				<div class="storefront-actions">
					<a
						href="/store/<?= htmlspecialchars($store['slug']) ?>/receipt?<?= htmlspecialchars($receiptQuery) ?>"
						class="storefront-button"
						target="_blank"
					>
						Print Receipt
					</a>
				</div>
			
			
        </section>

        <section class="order-confirmation-grid">

            <div class="checkout-form-panel">
                <h2>Order Details</h2>

                <table class="confirmation-table">
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="stock-pill stock-low">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Placed</th>
                        <td><?= htmlspecialchars($order['placed_at'] ?? $order['created_at'] ?? '') ?></td>
                    </tr>
					<tr>
						<th>Carrier</th>
						<td><?= htmlspecialchars($order['shipping_carrier'] ?? '—') ?></td>
					</tr>

					<tr>
						<th>Tracking Number</th>
						<td><?= htmlspecialchars($order['tracking_number'] ?? '—') ?></td>
					</tr>

					<tr>
						<th>Tracking Link</th>
						<td>
							<?php if (! empty($order['tracking_url'])): ?>
								<a href="<?= htmlspecialchars($order['tracking_url']) ?>" target="_blank">
									Track Shipment
								</a>
							<?php else: ?>
								—
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th>Shipped</th>
						<td><?= htmlspecialchars($order['shipped_at'] ?? '—') ?></td>
					</tr>
                    <tr>
                        <th>Subtotal</th>
                        <td>$<?= htmlspecialchars(number_format((float) $order['subtotal'], 2)) ?></td>
                    </tr>

                    <tr>
                        <th>Shipping</th>
                        <td>$<?= htmlspecialchars(number_format((float) $order['shipping_total'], 2)) ?></td>
                    </tr>

                    <tr>
                        <th>Tax</th>
                        <td>$<?= htmlspecialchars(number_format((float) $order['tax_total'], 2)) ?></td>
                    </tr>

                    <tr class="summary-total">
                        <th>Total</th>
                        <td>$<?= htmlspecialchars(number_format((float) $order['grand_total'], 2)) ?></td>
                    </tr>
                </table>
            </div>

            <div class="checkout-form-panel">
                <h2>Shipping Information</h2>

                <table class="confirmation-table">
                    <tr>
                        <th>Name</th>
                        <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                    </tr>

                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($order['email']) ?></td>
                    </tr>

                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($order['phone'] ?? '—') ?></td>
                    </tr>

                    <tr>
                        <th>Address</th>
                        <td>
                            <?= htmlspecialchars($order['address_line_1']) ?><br>

                            <?php if (! empty($order['address_line_2'])): ?>
                                <?= htmlspecialchars($order['address_line_2']) ?><br>
                            <?php endif; ?>

                            <?= htmlspecialchars($order['city']) ?>,
                            <?= htmlspecialchars($order['state']) ?>
                            <?= htmlspecialchars($order['postal_code']) ?><br>

                            <?= htmlspecialchars($order['country']) ?>
                        </td>
                    </tr>
                </table>
            </div>

        </section>
		
		<section class="checkout-form-panel order-items-panel">
			<h2>Order Timeline</h2>

			<div class="storefront-timeline">
				<?php foreach ($events as $event): ?>
					<div class="storefront-timeline-item">
						<div class="storefront-timeline-dot"></div>

						<div>
							<strong><?= htmlspecialchars($event['title']) ?></strong>

							<p>
								<?= htmlspecialchars($event['description'] ?? '') ?>
							</p>

							<span><?= htmlspecialchars($event['created_at'] ?? '') ?></span>
						</div>
					</div>
				<?php endforeach; ?>

				<?php if (empty($events)): ?>
					<p>No public timeline updates are available yet.</p>
				<?php endif; ?>
			</div>
		</section>
        <section class="checkout-form-panel order-items-panel">
            <h2>Items Ordered</h2>

            <div class="confirmation-items">
                <?php foreach ($items as $item): ?>
                    <article class="confirmation-item">
                        <div class="confirmation-item-image">
                            <?php if (! empty($item['image_url'])): ?>
                                <img
                                    src="<?= htmlspecialchars($item['image_url']) ?>"
                                    alt="<?= htmlspecialchars($item['image_alt_text'] ?: $item['product_name']) ?>"
                                >
                            <?php else: ?>
                                <div>No Image</div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h3><?= htmlspecialchars($item['product_name']) ?></h3>

                            <?php if (! empty($item['product_sku'])): ?>
                                <p>SKU: <?= htmlspecialchars($item['product_sku']) ?></p>
                            <?php endif; ?>

                            <p>
                                Quantity:
                                <?= htmlspecialchars((string) $item['quantity']) ?>
                            </p>
                        </div>

                        <div class="confirmation-item-pricing">
                            <p>
                                $<?= htmlspecialchars(number_format((float) $item['unit_price'], 2)) ?>
                                each
                            </p>

                            <strong>
                                $<?= htmlspecialchars(number_format((float) $item['line_total'], 2)) ?>
                            </strong>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</main>

<footer class="storefront-footer">
    <div class="storefront-container">
        <p>
            &copy; <?= date('Y') ?>
            <?= htmlspecialchars($store['name']) ?>.
            Powered by Alasne.
        </p>
    </div>
</footer>