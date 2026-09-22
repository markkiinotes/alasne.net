<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$agentId = (int) ($agent['id'] ?? 0);
$preview = is_array($preview ?? null) ? $preview : null;
$snapshot = $preview['snapshot'] ?? [];
$json = (string) ($preview['json'] ?? '');
?>

<style>
.ai-scope-page { display:grid; gap:20px; }
.ai-scope-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
.ai-scope-header h1 { margin:0 0 8px; }
.ai-scope-header p { margin:0; max-width:850px; color:#64748b; line-height:1.6; }
.ai-scope-actions { display:flex; gap:10px; flex-wrap:wrap; }
.ai-scope-button {
    display:inline-flex; align-items:center; justify-content:center; min-height:42px;
    padding:0 16px; border:0; border-radius:10px; background:#111827;
    color:white; text-decoration:none; font:inherit; font-weight:800; cursor:pointer;
}
.ai-scope-button.secondary { background:#e2e8f0; color:#0f172a; }
.ai-scope-panel { border:1px solid #e2e8f0; border-radius:16px; background:white; overflow:hidden; }
.ai-scope-panel header { padding:18px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ai-scope-panel h2 { margin:0 0 5px; }
.ai-scope-panel header p { margin:0; color:#64748b; line-height:1.5; }
.ai-scope-fields { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; padding:20px; }
.ai-scope-field { display:grid; gap:7px; }
.ai-scope-field label { font-weight:800; }
.ai-scope-field select, .ai-scope-field input {
    box-sizing:border-box; width:100%; padding:10px 12px;
    border:1px solid #cbd5e1; border-radius:9px; font:inherit;
}
.ai-scope-footer { padding:0 20px 20px; }
.ai-scope-alert { padding:14px 16px; border-radius:12px; line-height:1.6; }
.ai-scope-alert.info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; }
.ai-scope-alert.error { background:#fee2e2; color:#991b1b; }
.ai-scope-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; padding:18px 20px; }
.ai-scope-meta article { padding:14px; border:1px solid #e2e8f0; border-radius:10px; overflow-wrap:anywhere; }
.ai-scope-meta small { display:block; margin-bottom:5px; color:#64748b; font-weight:800; text-transform:uppercase; }
.ai-scope-code { font-family:Consolas,Monaco,monospace; font-size:12px; overflow-wrap:anywhere; }
.ai-scope-pre { margin:0; padding:20px; overflow:auto; white-space:pre-wrap; word-break:break-word; font:13px/1.6 Consolas,Monaco,monospace; }
@media(max-width:850px) { .ai-scope-fields,.ai-scope-meta { grid-template-columns:1fr; } }
</style>

<div class="ai-scope-page">
    <header class="ai-scope-header">
        <div>
            <h1>Scoped AI Operational Context</h1>
            <p>
                <?= $escape($agent['name'] ?? '') ?> · Select one store and reporting period.
                This page builds read-only data locally. It does not enable AI or call OpenAI.
            </p>
        </div>
        <div class="ai-scope-actions">
            <a class="ai-scope-button secondary" href="/admin/ai/agents/<?= $agentId ?>/edit">Edit Agent</a>
            <a class="ai-scope-button secondary" href="/admin/ai">AI Engine</a>
        </div>
    </header>

    <div class="ai-scope-alert info">
        Access is restricted to authorized platform operators. A selected store is
        checked again when a manual run is submitted. Current low-stock inventory
        is not a historical inventory report for the chosen date range.
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="ai-scope-alert error"><?= $escape($error) ?></div>
    <?php endif; ?>

    <section class="ai-scope-panel">
        <header>
            <h2>Reporting Scope</h2>
            <p>No all-stores option. Dates must be valid and the range may not exceed 367 calendar days.</p>
        </header>
        <form method="GET" action="/admin/ai/agents/<?= $agentId ?>/context">
            <div class="ai-scope-fields">
                <div class="ai-scope-field">
                    <label for="ai-context-store">Store</label>
                    <select id="ai-context-store" name="store_id" required>
                        <option value="">Select a store</option>
                        <?php foreach ($stores as $store): ?>
                            <option
                                value="<?= (int) $store['id'] ?>"
                                <?= (string) $selected_store_id === (string) $store['id'] ? 'selected' : '' ?>
                            ><?= $escape($store['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ai-scope-field">
                    <label for="ai-context-from">Date From</label>
                    <input id="ai-context-from" type="date" name="date_from" required value="<?= $escape($date_from) ?>">
                </div>
                <div class="ai-scope-field">
                    <label for="ai-context-to">Date To</label>
                    <input id="ai-context-to" type="date" name="date_to" required value="<?= $escape($date_to) ?>">
                </div>
            </div>
            <div class="ai-scope-footer">
                <button class="ai-scope-button" type="submit">Build Scoped Preview</button>
            </div>
        </form>
    </section>

    <?php if ($preview !== null): ?>
        <section class="ai-scope-panel">
            <header>
                <h2>Validated Read-Only Snapshot</h2>
                <p>Preview only. The snapshot is rebuilt and re-authorized at manual execution time.</p>
            </header>
            <div class="ai-scope-meta">
                <article>
                    <small>Store / Period</small>
                    <strong><?= $escape($snapshot['scope']['store_name'] ?? '') ?></strong><br>
                    <?= $escape($preview['date_from']) ?> to <?= $escape($preview['date_to']) ?>
                </article>
                <article>
                    <small>Context Type / Size</small>
                    <span class="ai-scope-code"><?= $escape($preview['context_type']) ?></span><br>
                    <?= (int) $preview['length'] ?> characters
                </article>
                <article>
                    <small>SHA-256</small>
                    <span class="ai-scope-code"><?= $escape($preview['sha256']) ?></span>
                </article>
            </div>
        </section>

        <section class="ai-scope-panel">
            <header>
                <h2>Snapshot JSON</h2>
                <p>
                    Fixed fields only. No customer names, addresses, contact details,
                    payment credentials, unrestricted SQL, or mutation tools.
                    The JSON body is not persisted in ai_runs.
                </p>
            </header>
            <pre class="ai-scope-pre"><?= $escape($json) ?></pre>
        </section>
    <?php endif; ?>
</div>
