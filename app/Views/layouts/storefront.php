<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Storefront') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if (! empty($meta_description)): ?>
        <meta
            name="description"
            content="<?= htmlspecialchars((string) $meta_description) ?>"
        >
    <?php endif; ?>

    <link rel="stylesheet" href="/assets/css/storefront.css">
</head>
<body>

    <?= $content ?>

</body>
</html>