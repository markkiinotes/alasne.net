<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Create a new platform user and assign an access role.</p>
</section>

<?php if (!empty($error)): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($_SESSION['users_error']); ?>
<?php endif; ?>

<div class="panel form-panel">
    <form method="POST" action="/admin/users">

        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role" required>
                <option value="">Select Role</option>

                <?php foreach ($roles as $role): ?>
                    <option value="<?= htmlspecialchars($role['slug']) ?>">
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Create User
            </button>

            <a href="/admin/users" class="button-muted">
                Cancel
            </a>
        </div>

    </form>
</div>