<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/assets/css/admin.css">
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Admin Dashboard') ?></title>
</head>
<body>
    <aside>
        <h2>Alasne CMS</h2>
        <nav>
            <a href="/admin">Dashboard</a>
        </nav>
    </aside>

    <main>
        <?= $content ?>
    </main>
</body>
</html>