<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
?>

<style>
.agent-versions-page {
    display:grid;
    gap:20px;
}
.agent-versions-header {
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
    flex-wrap:wrap;
}
.agent-versions-header h1 {
    margin:0 0 8px;
}
.agent-versions-header p {
    margin:0;
    color:#64748b;
}
.agent-version-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.agent-version-button {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:0 16px;
    border-radius:10px;
    background:#e2e8f0;
    color:#0f172a;
    text-decoration:none;
    font-weight:800;
}
.agent-version-summary,
.agent-version-card {
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
}
.agent-version-summary {
    padding:18px;
}
.agent-version-list {
    display:grid;
    gap:16px;
}
.agent-version-card {
    overflow:hidden;
}
.agent-version-card header {
    display:flex;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    padding:16px 18px;
    background:#f8fafc;
    border-bottom:1px solid #e2e8f0;
}
.agent-version-card header h2 {
    margin:0;
}
.agent-version-meta {
    color:#64748b;
    font-size:13px;
}
.agent-version-body {
    display:grid;
    gap:16px;
    padding:18px;
}
.agent-version-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.agent-version-field {
    padding:12px;
    border:1px solid #e2e8f0;
    border-radius:10px;
}
.agent-version-field small {
    display:block;
    margin-bottom:5px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.agent-version-code {
    display:inline-flex;
    max-width:100%;
    padding:4px 8px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-family:Consolas,Monaco,monospace;
    font-size:11px;
    word-break:break-all;
}
.agent-version-capabilities {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.agent-version-instructions {
    padding:14px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    background:#f8fafc;
    white-space:pre-wrap;
    line-height:1.6;
}
.agent-version-description {
    color:#475569;
    line-height:1.55;
}
@media(max-width:950px) {
    .agent-version-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:620px) {
    .agent-version-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="agent-versions-page">
    <header class="agent-versions-header">
        <div>
            <h1>
                <?= $escape(
                    $agent['name']
                ) ?>
                — Version History
            </h1>

            <p>
                Immutable snapshots of this AI agent definition and
                its status transitions.
            </p>
        </div>

        <div class="agent-version-actions">
            <a
                href="/admin/ai/agents/<?= (int) $agent['id'] ?>/edit"
                class="agent-version-button"
            >
                Edit Agent
            </a>

            <a
                href="/admin/ai"
                class="agent-version-button"
            >
                AI Engine
            </a>
        </div>
    </header>

    <section class="agent-version-summary">
        <strong>
            <?= count($versions) ?>
            immutable version<?= count($versions) === 1 ? '' : 's' ?>
        </strong>
        &nbsp;·&nbsp;
        <span class="agent-version-code">
            <?= $escape(
                $agent['slug']
            ) ?>
        </span>
    </section>

    <div class="agent-version-list">
        <?php foreach ($versions as $version): ?>
            <article class="agent-version-card">
                <header>
                    <div>
                        <h2>
                            Version
                            #<?= (int) $version['version_number'] ?>
                        </h2>

                        <div class="agent-version-meta">
                            <?= $escape(
                                $version['created_at']
                            ) ?>
                            <?php if (
                                ! empty(
                                    $version[
                                        'changed_by_name'
                                    ]
                                )
                            ): ?>
                                ·
                                <?= $escape(
                                    $version[
                                        'changed_by_name'
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <strong>
                            <?= $escape(
                                ucfirst(
                                    $version['status']
                                        ?: 'unknown'
                                )
                            ) ?>
                        </strong>
                    </div>
                </header>

                <div class="agent-version-body">
                    <div>
                        <strong>Change Note</strong>
                        <div class="agent-version-description">
                            <?= $escape(
                                $version[
                                    'change_note'
                                ]
                            ) ?>
                        </div>
                    </div>

                    <div class="agent-version-grid">
                        <div class="agent-version-field">
                            <small>Name</small>
                            <?= $escape(
                                $version['name']
                            ) ?>
                        </div>

                        <div class="agent-version-field">
                            <small>Slug</small>
                            <span class="agent-version-code">
                                <?= $escape(
                                    $version['slug']
                                ) ?>
                            </span>
                        </div>

                        <div class="agent-version-field">
                            <small>Model</small>
                            <?= $version['model_override'] !== ''
                                ? $escape(
                                    $version[
                                        'model_override'
                                    ]
                                )
                                : 'Platform default' ?>
                        </div>

                        <div class="agent-version-field">
                            <small>Output Limit</small>
                            <?= (int) $version[
                                'max_output_tokens'
                            ] ?>
                        </div>
                    </div>

                    <?php if (
                        $version['description'] !== ''
                    ): ?>
                        <div>
                            <strong>Description</strong>
                            <div class="agent-version-description">
                                <?= $escape(
                                    $version[
                                        'description'
                                    ]
                                ) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <strong>Capabilities</strong>
                        <div class="agent-version-capabilities">
                            <?php foreach (
                                $version['capabilities']
                                as $capability
                            ): ?>
                                <span class="agent-version-code">
                                    <?= $escape(
                                        $capability
                                    ) ?>
                                </span>
                            <?php endforeach; ?>

                            <?php if (
                                $version['capabilities']
                                === []
                            ): ?>
                                <span>None</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <details>
                        <summary>
                            <strong>
                                System Instructions
                            </strong>
                        </summary>

                        <div
                            class="agent-version-instructions"
                            style="margin-top:10px;"
                        ><?= $escape(
                            $version[
                                'system_instructions'
                            ]
                        ) ?></div>
                    </details>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($versions === []): ?>
            <section class="agent-version-summary">
                No version snapshots are available.
            </section>
        <?php endif; ?>
    </div>
</div>
