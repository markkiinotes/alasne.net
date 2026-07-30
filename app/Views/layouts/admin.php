<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Mission Control') ?> | Alasne Platform</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <aside>
        <h2>Alasne Platform</h2>
        <p class="sidebar-subtitle">Mission Control</p>
		<div class="user-box">
			<strong><?= htmlspecialchars(current_user_name()) ?></strong>
			<span>
				<?= htmlspecialchars(implode(', ', current_user_roles()) ?: 'Operator') ?>
			</span>
		</div>
        <nav>
			<a href="/admin" class="<?= nav_active('/admin') ?>">
				Dashboard
			</a>

			<?php if (can('users.manage')): ?>
				<a href="/admin/users" class="<?= nav_active('/admin/users', true) ?>">
					Users
				</a>
			<?php endif; ?>

			<?php if (can('roles.manage')): ?>
				<a href="/admin/roles" class="<?= nav_active('/admin/roles', true) ?>">
					Roles
				</a>
			<?php endif; ?>

			<?php if (can('permissions.manage')): ?>
				<a href="/admin/permissions" class="<?= nav_active('/admin/permissions', true) ?>">
					Permissions
				</a>
			<?php endif; ?>

			<?php if (can('stores.manage')): ?>
				<a href="/admin/stores" class="<?= nav_active('/admin/stores', true) ?>">
					Stores
				</a>
			<?php endif; ?>

			<?php if (can('products.manage')): ?>
				<a href="/admin/products" class="<?= nav_active('/admin/products', true) ?>">
					Products
				</a>
			<?php endif; ?>
			
			<?php if (can('products.manage')): ?>
				<a href="/admin/categories" class="<?= nav_active('/admin/categories', true) ?>">
					Categories
				</a>
			<?php endif; ?>
			
			<?php if (can('products.manage')): ?>
				<a href="/admin/inventory" class="<?= nav_active('/admin/inventory', true) ?>">
					Inventory
				</a>
			<?php endif; ?>

			<?php if (can('orders.manage')): ?>
				<a href="/admin/orders" class="<?= nav_active('/admin/orders', true) ?>">
					Orders
				</a>
			<?php endif; ?>
			
			<?php if (can('orders.manage')): ?>
				<a href="/admin/email-outbox" class="<?= nav_active('/admin/email-outbox') ?>">
					Email Outbox
				</a>
			<?php endif; ?>

			<?php if (can('customers.manage')): ?>
				<a href="/admin/customers" class="<?= nav_active('/admin/customers', true) ?>">
					Customers
				</a>
			<?php endif; ?>

			<?php if (can('ai.manage')): ?>
				<a href="/admin/ai" class="<?= nav_active('/admin/ai', true) ?>">
					AI Engine
				</a>
			<?php endif; ?>

			<?php if (can('settings.manage')): ?>
				<a href="/admin/settings" class="<?= nav_active('/admin/settings', true) ?>">
					Settings
				</a>
			<?php endif; ?>

			<a href="/logout">
				Logout
			</a>
		</nav>
    </aside>

    <main>
        <?= $content ?>
    </main>
</body>
</html>