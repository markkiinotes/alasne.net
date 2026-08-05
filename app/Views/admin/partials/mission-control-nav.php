<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$sections = $sections ?? [];
$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';
?>

<aside class="mission-side-nav" aria-label="Mission Control navigation">
    <div class="mission-side-nav-header">
        <strong>Mission Control</strong>
        <span>Workflow Navigation</span>
    </div>

    <?php foreach ($sections as $section): ?>
        <section class="mission-side-nav-section">
            <h3>
                <span aria-hidden="true"><?= $escape($section['icon'] ?? '•') ?></span>
                <?= $escape($section['title']) ?>
            </h3>

            <?php foreach ($section['items'] as $item): ?>
                <?php $active = $currentPath === $item['url']; ?>

                <a
                    href="<?= $escape($item['url']) ?>"
                    class="mission-side-nav-link<?= $active ? ' active' : '' ?>"
                >
                    <span>
                        <?= $escape($item['label']) ?>
                        <small><?= $escape($item['description']) ?></small>
                    </span>

                    <?php if (! empty($item['badge'])): ?>
                        <em>
                            <?= $escape($item['badge']['count']) ?>
                        </em>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</aside>
