<header class="storefront-product-header">
    <div class="storefront-container storefront-topbar">
        <a href="/store/<?= htmlspecialchars($store['slug']) ?>" class="back-link">
            ← Continue Shopping
        </a>

        <a href="/store/<?= htmlspecialchars($store['slug']) ?>/cart" class="cart-link">
            Cart <?= (int) ($cartQuantity ?? 0) > 0 ? '(' . htmlspecialchars((string) $cartQuantity) . ')' : '' ?>
        </a>
    </div>
</header>

<main class="storefront-container">

    <section class="category-page-hero">
        <p class="eyebrow dark-eyebrow">Shopping Cart</p>

        <h1>Your Cart</h1>

        <p>
            Review your items before checkout.
        </p>
    </section>

    <?php if (!empty($success)): ?>
        <div class="storefront-alert success">
            <?= htmlspecialchars($success) ?>
        </div>
        <?php unset($_SESSION['cart_success']); ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="storefront-alert danger">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

    <?php if (empty($cartRows)): ?>
        <section class="cart-empty">
            <h2>Your cart is empty.</h2>

            <p>
                Start browsing products and add items to your cart.
            </p>

            <a href="/store/<?= htmlspecialchars($store['slug']) ?>" class="button-primary dark-button">
                Back to Store
            </a>
        </section>
    <?php else: ?>
        <section class="cart-layout">
            <div class="cart-items">
                <?php foreach ($cartRows as $row): ?>
                    <?php
                        $product = $row['product'];
                        $quantity = (int) $row['quantity'];
                    ?>

                    <article class="cart-item">
                        <div class="cart-item-image">
                            <?php if (! empty($product['image_url'])): ?>
                                <img
                                    src="<?= htmlspecialchars($product['image_url']) ?>"
                                    alt="<?= htmlspecialchars($product['image_alt_text'] ?: $product['name']) ?>"
                                >
                            <?php else: ?>
                                <div>No Image</div>
                            <?php endif; ?>
                        </div>

                        <div class="cart-item-info">
                            <p class="product-category">
                                <?= htmlspecialchars($product['categories'] ?? 'Uncategorized') ?>
                            </p>

                            <h2>
                                <a href="/store/<?= htmlspecialchars($store['slug']) ?>/product/<?= htmlspecialchars($product['slug']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </h2>

                            <p>
                                $<?= htmlspecialchars(number_format((float) $product['price'], 2)) ?>
                            </p>

                            <p class="cart-inventory-note">
                                Available inventory:
                                <?= htmlspecialchars((string) $product['inventory_quantity']) ?>
                            </p>

                            <div class="cart-item-actions">
                                <form method="POST" action="/store/<?= htmlspecialchars($store['slug']) ?>/cart/update">
                                    <input
                                        type="hidden"
                                        name="_csrf_token"
                                        value="<?= htmlspecialchars($csrf_token) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= htmlspecialchars((string) $product['id']) ?>"
                                    >

                                    <label>
                                        Quantity
                                        <input
                                            type="number"
                                            name="quantity"
                                            value="<?= htmlspecialchars((string) $quantity) ?>"
                                            min="0"
                                            max="<?= htmlspecialchars((string) $product['inventory_quantity']) ?>"
                                        >
                                    </label>

                                    <button type="submit" class="cart-small-button">
                                        Update
                                    </button>
                                </form>

                                <form method="POST" action="/store/<?= htmlspecialchars($store['slug']) ?>/cart/remove">
                                    <input
                                        type="hidden"
                                        name="_csrf_token"
                                        value="<?= htmlspecialchars($csrf_token) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= htmlspecialchars((string) $product['id']) ?>"
                                    >

                                    <button type="submit" class="cart-remove-button">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="cart-line-total">
                            $<?= htmlspecialchars(number_format((float) $row['line_total'], 2)) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary">
                <h2>Order Summary</h2>

                <table>
                    <tr>
                        <th>Subtotal</th>
                        <td>$<?= htmlspecialchars(number_format((float) $subtotal, 2)) ?></td>
                    </tr>

                    <tr>
                        <th>Shipping</th>
                        <td>Calculated later</td>
                    </tr>

                    <tr>
                        <th>Tax</th>
                        <td>Calculated later</td>
                    </tr>

                    <tr class="summary-total">
                        <th>Estimated Total</th>
                        <td>$<?= htmlspecialchars(number_format((float) $subtotal, 2)) ?></td>
                    </tr>
                </table>

                <a
					href="/store/<?= htmlspecialchars($store['slug']) ?>/checkout"
					class="storefront-cart-button full-width-button checkout-link-button"
				>
					Proceed to Checkout
				</a>
            </aside>
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