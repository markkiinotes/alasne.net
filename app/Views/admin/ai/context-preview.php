<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$agent = $preview['agent'] ?? [];
$snapshot = $preview['snapshot'] ?? [];
$json = (string) ($preview['json'] ?? '');
?>

<style>
.ai-context-page {
    display:grid;
    gap:20px;
}
.ai-context-header {
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
    flex-wrap:wrap;
}
.ai-context-header h1 {
    margin:0 0 8px;
}
.ai-context-header p {
    margin:0;
    color:#64748b;
    line-height:1.55;
    max-width:900px;
}
.ai-context-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.ai-context-button {
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
.ai-context-notice {
    border:1px solid #bfdbfe;
    background:#eff6ff;
    color:#1e3a8a;
    border-radius:14px;
    padding:16px 18px;
    line-height:1.6;
}
.ai-context-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.ai-context-card,
.ai-context-panel {
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
}
.ai-context-card {
    padding:16px;
}
.ai-context-card small {
    display:block;
    margin-bottom:5px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.ai-context-code {
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
.ai-context-panel {
    overflow:hidden;
}
.ai-context-panel header {
    padding:18px 20px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}
.ai-context-panel header h2 {
    margin:0 0 4px;
}
.ai-context-panel header p {
    margin:0;
    color:#64748b;
}
.ai-context-pre {
    margin:0;
    padding:20px;
    overflow:auto;
    white-space:pre-wrap;
    word-break:break-word;
    font-family:Consolas,Monaco,monospace;
    font-size:13px;
    line-height:1.55;
    color:#0f172a;
}
.ai-context-section-list {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    padding:18px 20px;
}
@media(max-width:950px) {
    .ai-context-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:620px) {
    .ai-context-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="ai-context-page">
    <header class="ai-context-header">
        <div>
            <h1>AI Operational Context Preview</h1>
            <p>
                This is the exact bounded read-only snapshot Alasne
                will make available to this agent during a manual run.
                Opening this page does not call OpenAI.
            </p>
        </div>

        <div class="ai-context-actions">
            <a
                href="/admin/ai/agents/<?= (int) ($agent['id'] ?? 0) ?>/edit"
                class="ai-context-button"
            >
                Edit Agent
            </a>

            <a
                href="/admin/ai"
                class="ai-context-button"
            >
                AI Engine
            </a>
        </div>
    </header>

    <div class="ai-context-notice">
        Snapshot values are treated as data, never as instructions.
        Customer names, email addresses, phone numbers, postal
        addresses, payment credentials, and unrestricted database
        access are not included in this capability.
    </div>

    <section class="ai-context-grid">
        <article class="ai-context-card">
            <small>Agent</small>
            <strong>
                <?= $escape(
                    $agent['name'] ?? ''
                ) ?>
            </strong>
        </article>

        <article class="ai-context-card">
            <small>Context Type</small>
            <span class="ai-context-code">
                <?= $escape(
                    $preview['context_type']
                    ?? ''
                ) ?>
            </span>
        </article>

        <article class="ai-context-card">
            <small>Context Length</small>
            <strong>
                <?= (int) (
                    $preview['length']
                    ?? 0
                ) ?>
                chars
            </strong>
        </article>

        <article class="ai-context-card">
            <small>SHA-256</small>
            <span class="ai-context-code">
                <?= $escape(
                    $preview['sha256']
                    ?? ''
                ) ?>
            </span>
        </article>
    </section>

    <section class="ai-context-panel">
        <header>
            <h2>Included Sections</h2>
            <p>
                All sections are server-selected and field-whitelisted.
            </p>
        </header>

        <div class="ai-context-section-list">
            <?php foreach (
                array_keys($snapshot)
                as $section
            ): ?>
                <span class="ai-context-code">
                    <?= $escape($section) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="ai-context-panel">
        <header>
            <h2>Read-Only Snapshot JSON</h2>
            <p>
                Preview only. This snapshot body is not stored in
                ai_runs; only its type, hash, and length are audited.
            </p>
        </header>

        <pre class="ai-context-pre"><?= $escape($json) ?></pre>
    </section>
</div>
