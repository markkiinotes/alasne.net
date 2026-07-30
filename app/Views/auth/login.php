<div class="login-container">

    <div class="login-card">

        <h1>Alasne Platform</h1>

        <h2>Mission Control</h2>

        <p class="subtitle">
            Sign in to access the Platform.
        </p>

        <?php if (!empty($error)): ?>

            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="/login">

		<input
			type="hidden"
			name="_csrf_token"
			value="<?= htmlspecialchars($csrf_token) ?>"
		>
            <div class="form-group">
                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit">
                Sign In
            </button>

        </form>

    </div>

</div>