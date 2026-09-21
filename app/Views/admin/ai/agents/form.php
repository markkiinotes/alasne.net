<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$isEdit = $mode === 'edit';
$current = is_array($agent) ? $agent : [];

$value = static function (
    string $key,
    mixed $default = ''
) use ($old, $current): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }

    return $current[$key]
        ?? $default;
};

$currentCapabilities = [];

if (
    isset($old['capabilities'])
    && is_array($old['capabilities'])
) {
    $currentCapabilities =
        $old['capabilities'];
} elseif (
    isset($current['capabilities_json'])
    && is_string(
        $current['capabilities_json']
    )
) {
    $decoded = json_decode(
        $current['capabilities_json'],
        true
    );

    if (is_array($decoded)) {
        $currentCapabilities =
            array_values($decoded);
    }
}

$status = strtolower(
    (string) (
        $current['status']
        ?? 'draft'
    )
);

$action = $isEdit
    ? '/admin/ai/agents/'
        . (int) $current['id']
    : '/admin/ai/agents';
?>

<style>
.agent-admin-page {
    display:grid;
    gap:22px;
}
.agent-admin-header {
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
    flex-wrap:wrap;
}
.agent-admin-header h1 {
    margin:0 0 8px;
}
.agent-admin-header p {
    margin:0;
    color:#64748b;
    line-height:1.55;
    max-width:780px;
}
.agent-admin-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.agent-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:0 16px;
    border:0;
    border-radius:10px;
    background:#111827;
    color:#fff;
    text-decoration:none;
    font:inherit;
    font-weight:800;
    cursor:pointer;
}
.agent-btn.secondary {
    background:#e2e8f0;
    color:#0f172a;
}
.agent-btn.warn {
    background:#b45309;
}
.agent-btn.danger {
    background:#991b1b;
}
.agent-alert {
    padding:14px 16px;
    border-radius:12px;
    line-height:1.55;
}
.agent-alert.success {
    background:#dcfce7;
    color:#166534;
    font-weight:700;
}
.agent-alert.error {
    background:#fee2e2;
    color:#991b1b;
    font-weight:700;
}
.agent-panel {
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
    overflow:hidden;
}
.agent-panel-header {
    padding:18px 20px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}
.agent-panel-header h2 {
    margin:0 0 4px;
}
.agent-panel-header p {
    margin:0;
    color:#64748b;
}
.agent-fields {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
    padding:20px;
}
.agent-field {
    display:grid;
    gap:7px;
}
.agent-field.full {
    grid-column:1 / -1;
}
.agent-field label {
    font-weight:800;
}
.agent-field input,
.agent-field textarea {
    width:100%;
    box-sizing:border-box;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:9px;
    font:inherit;
}
.agent-field textarea {
    min-height:120px;
    resize:vertical;
}
.agent-field textarea.instructions {
    min-height:260px;
}
.agent-field small {
    color:#64748b;
    line-height:1.45;
}
.agent-capabilities {
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}
.agent-capability {
    display:flex;
    gap:8px;
    align-items:center;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:10px;
}
.agent-capability input {
    width:auto;
}
.agent-form-footer {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:18px 20px;
    border-top:1px solid #e2e8f0;
    background:#f8fafc;
}
.agent-meta {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    padding:20px;
}
.agent-meta article {
    padding:14px;
    border:1px solid #e2e8f0;
    border-radius:12px;
}
.agent-meta small {
    display:block;
    margin-bottom:5px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.agent-code {
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
.agent-status {
    display:inline-flex;
    padding:5px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    background:#e2e8f0;
    color:#475569;
}
.agent-status.active {
    background:#dcfce7;
    color:#166534;
}
.agent-status.archived {
    background:#fee2e2;
    color:#991b1b;
}
.agent-status-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
    padding:20px;
}
.agent-status-card {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:12px;
}
.agent-status-card h3 {
    margin:0 0 8px;
}
.agent-status-card p {
    margin:0 0 12px;
    color:#64748b;
    line-height:1.5;
}
.agent-status-card input {
    width:100%;
    box-sizing:border-box;
    margin-bottom:10px;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:9px;
    font:inherit;
}
@media(max-width:900px) {
    .agent-fields,
    .agent-meta,
    .agent-status-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="agent-admin-page">
    <header class="agent-admin-header">
        <div>
            <h1>
                <?= $isEdit
                    ? 'Edit AI Agent'
                    : 'Create AI Agent' ?>
            </h1>

            <p>
                Configure an Alasne AI definition. Changes are
                versioned immutably; archived agents are retained
                rather than deleted.
            </p>
        </div>

        <div class="agent-admin-actions">
            <a
                href="/admin/ai"
                class="agent-btn secondary"
            >
                AI Engine
            </a>

            <?php if ($isEdit): ?>
                <a
                    href="/admin/ai/agents/<?= (int) $current['id'] ?>/versions"
                    class="agent-btn secondary"
                >
                    Version History
                </a>

                <?php if (
                    in_array(
                        'operational_snapshot',
                        $currentCapabilities,
                        true
                    )
                ): ?>
                    <a
                        href="/admin/ai/agents/<?= (int) $current['id'] ?>/context"
                        class="agent-btn secondary"
                    >
                        Preview Context
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($success): ?>
        <div class="agent-alert success">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="agent-alert error">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($isEdit): ?>
        <section class="agent-panel">
            <div class="agent-meta">
                <article>
                    <small>Agent ID</small>
                    <strong>
                        #<?= (int) $current['id'] ?>
                    </strong>
                </article>

                <article>
                    <small>Stable Slug</small>
                    <span class="agent-code">
                        <?= $escape(
                            $current['slug']
                        ) ?>
                    </span>
                </article>

                <article>
                    <small>Status</small>
                    <span class="agent-status <?= $escape($status) ?>">
                        <?= $escape(
                            ucfirst($status)
                        ) ?>
                    </span>
                </article>

                <article>
                    <small>Versions</small>
                    <strong>
                        <?= (int) (
                            $current[
                                'version_count'
                            ] ?? 0
                        ) ?>
                    </strong>
                </article>
            </div>
        </section>
    <?php endif; ?>

    <form
        method="POST"
        action="<?= $escape($action) ?>"
        class="agent-panel"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <header class="agent-panel-header">
            <h2>Agent Definition</h2>
            <p>
                Only approved read-only/manual capabilities are
                available in this phase.
            </p>
        </header>

        <div class="agent-fields">
            <div class="agent-field">
                <label for="agent-name">
                    Agent Name
                </label>

                <input
                    id="agent-name"
                    type="text"
                    name="name"
                    maxlength="120"
                    required
                    value="<?= $escape(
                        $value('name')
                    ) ?>"
                >
            </div>

            <div class="agent-field">
                <label for="agent-model">
                    Model Override
                </label>

                <input
                    id="agent-model"
                    type="text"
                    name="model_override"
                    maxlength="191"
                    value="<?= $escape(
                        $value(
                            'model_override'
                        )
                    ) ?>"
                    placeholder="Leave blank to use the platform default"
                >

                <small>
                    A blank value uses the model configured in
                    Platform Settings.
                </small>
            </div>

            <div class="agent-field full">
                <label for="agent-description">
                    Description
                </label>

                <textarea
                    id="agent-description"
                    name="description"
                    maxlength="500"
                ><?= $escape(
                    $value('description')
                ) ?></textarea>
            </div>

            <div class="agent-field full">
                <label for="agent-instructions">
                    System Instructions
                </label>

                <textarea
                    id="agent-instructions"
                    name="system_instructions"
                    maxlength="50000"
                    required
                    class="instructions"
                ><?= $escape(
                    $value(
                        'system_instructions'
                    )
                ) ?></textarea>

                <small>
                    These instructions are sent as the provider
                    instruction layer during manual execution.
                </small>
            </div>

            <div class="agent-field">
                <label for="agent-tokens">
                    Maximum Output Tokens
                </label>

                <input
                    id="agent-tokens"
                    type="number"
                    name="max_output_tokens"
                    min="1"
                    max="32768"
                    required
                    value="<?= $escape(
                        $value(
                            'max_output_tokens',
                            2000
                        )
                    ) ?>"
                >
            </div>

            <div class="agent-field">
                <label>Approved Capabilities</label>

                <div class="agent-capabilities">
                    <?php foreach (
                        $allowed_capabilities
                        as $capability
                    ): ?>
                        <label class="agent-capability">
                            <input
                                type="checkbox"
                                name="capabilities[]"
                                value="<?= $escape(
                                    $capability
                                ) ?>"
                                <?= in_array(
                                    $capability,
                                    $currentCapabilities,
                                    true
                                )
                                    ? 'checked'
                                    : '' ?>
                            >

                            <span>
                                <?= $escape(
                                    $capability
                                ) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="agent-field full">
                    <label for="agent-change-note">
                        Change Note
                    </label>

                    <input
                        id="agent-change-note"
                        type="text"
                        name="change_note"
                        maxlength="500"
                        required
                        value="<?= $escape(
                            $value(
                                'change_note'
                            )
                        ) ?>"
                        placeholder="Describe why this definition is changing"
                    >

                    <small>
                        Required when saving an edit and stored
                        with the immutable version snapshot.
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <footer class="agent-form-footer">
            <a
                href="/admin/ai"
                class="agent-btn secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="agent-btn"
            >
                <?= $isEdit
                    ? 'Save New Version'
                    : 'Create Draft Agent' ?>
            </button>
        </footer>
    </form>

    <?php if ($isEdit): ?>
        <section class="agent-panel">
            <header class="agent-panel-header">
                <h2>Status Control</h2>
                <p>
                    Status transitions are also versioned. There is
                    no destructive delete action.
                </p>
            </header>

            <div class="agent-status-grid">
                <?php if ($status === 'draft'): ?>
                    <div class="agent-status-card">
                        <h3>Activate Agent</h3>
                        <p>
                            Activation requires the manual_prompting
                            capability and valid system instructions.
                        </p>

                        <form
                            method="POST"
                            action="/admin/ai/agents/<?= (int) $current['id'] ?>/status"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="status"
                                value="active"
                            >
                            <input
                                type="text"
                                name="change_note"
                                maxlength="500"
                                required
                                placeholder="Why is this agent ready to activate?"
                            >
                            <button
                                type="submit"
                                class="agent-btn"
                            >
                                Activate
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'active'): ?>
                    <div class="agent-status-card">
                        <h3>Return to Draft</h3>
                        <p>
                            Keep the definition but remove active
                            status while further changes are made.
                        </p>

                        <form
                            method="POST"
                            action="/admin/ai/agents/<?= (int) $current['id'] ?>/status"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="status"
                                value="draft"
                            >
                            <input
                                type="text"
                                name="change_note"
                                maxlength="500"
                                required
                                placeholder="Why is this agent returning to draft?"
                            >
                            <button
                                type="submit"
                                class="agent-btn warn"
                            >
                                Return to Draft
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($status !== 'archived'): ?>
                    <div class="agent-status-card">
                        <h3>Archive Agent</h3>
                        <p>
                            Archived agents remain fully versioned
                            and cannot be manually executed.
                        </p>

                        <form
                            method="POST"
                            action="/admin/ai/agents/<?= (int) $current['id'] ?>/status"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="status"
                                value="archived"
                            >
                            <input
                                type="text"
                                name="change_note"
                                maxlength="500"
                                required
                                placeholder="Why is this agent being archived?"
                            >
                            <button
                                type="submit"
                                class="agent-btn danger"
                            >
                                Archive
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="agent-status-card">
                        <h3>Restore to Draft</h3>
                        <p>
                            Archived agents must return to Draft
                            before they can be activated again.
                        </p>

                        <form
                            method="POST"
                            action="/admin/ai/agents/<?= (int) $current['id'] ?>/status"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="status"
                                value="draft"
                            >
                            <input
                                type="text"
                                name="change_note"
                                maxlength="500"
                                required
                                placeholder="Why is this agent being restored?"
                            >
                            <button
                                type="submit"
                                class="agent-btn"
                            >
                                Restore to Draft
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
