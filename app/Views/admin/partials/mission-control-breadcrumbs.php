<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$breadcrumbs = $breadcrumbs ?? [];
?>

<?php if (! empty($breadcrumbs)): ?>
    <nav class="mission-breadcrumbs" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php if ($index > 0): ?>
                <span class="mission-breadcrumb-separator">/</span>
            <?php endif; ?>

            <?php if (! empty($crumb['url'])): ?>
                <a href="<?= $escape($crumb['url']) ?>">
                    <?= $escape($crumb['label']) ?>
                </a>
            <?php else: ?>
                <span><?= $escape($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
