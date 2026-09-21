<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$config = $dashboard['configuration'] ?? [];
$summary = $dashboard['summary'] ?? [];
$agents = $dashboard['agents'] ?? [];
$recentRuns = $dashboard['recent_runs'] ?? [];

$badge = static function (bool $ok): string {
    return $ok
        ? 'ai-badge ok'
        : 'ai-badge warn';
};

$statusBadge = static function (
    string $status
): string {
    return match (strtolower($status)) {
        'succeeded', 'active' => 'ai-badge ok',
        'failed' => 'ai-badge bad',
        'pending', 'draft' => 'ai-badge neutral',
        default => 'ai-badge neutral',
    };
};

$executionAvailable =
    ! empty($config['execution_available']);
?>

<style>
.ai-page {
    display:grid;
    gap:22px;
}
.ai-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    flex-wrap:wrap;
}
.ai-header h1 {
    margin:0 0 8px;
}
.ai-header p {
    margin:0;
    color:#64748b;
    line-height:1.55;
    max-width:860px;
}
.ai-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.ai-button {
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
.ai-button.secondary {
    background:#e2e8f0;
    color:#0f172a;
}
.ai-button:disabled {
    opacity:.55;
    cursor:not-allowed;
}
.ai-notice,
.ai-alert {
    padding:16px 18px;
    border-radius:14px;
    line-height:1.6;
}
.ai-notice {
    border:1px solid #bfdbfe;
    background:#eff6ff;
    color:#1e3a8a;
}
.ai-alert.success {
    background:#dcfce7;
    color:#166534;
    font-weight:700;
}
.ai-alert.error {
    background:#fee2e2;
    color:#991b1b;
    font-weight:700;
}
.ai-summary,
.ai-config-grid {
    display:grid;
    gap:14px;
}
.ai-summary {
    grid-template-columns:repeat(3,minmax(0,1fr));
}
.ai-config-grid {
    grid-template-columns:repeat(4,minmax(0,1fr));
}
.ai-card,
.ai-panel {
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
}
.ai-card {
    padding:18px;
}
.ai-card small {
    display:block;
    margin-bottom:5px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.ai-card strong {
    font-size:24px;
}
.ai-config-value {
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
}
.ai-badge {
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.ai-badge.ok {
    background:#dcfce7;
    color:#166534;
}
.ai-badge.warn {
    background:#fef3c7;
    color:#92400e;
}
.ai-badge.bad {
    background:#fee2e2;
    color:#991b1b;
}
.ai-badge.neutral {
    background:#e2e8f0;
    color:#475569;
}
.ai-panel {
    overflow:hidden;
}
.ai-panel-header {
    padding:18px 20px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}
.ai-panel-header h2 {
    margin:0 0 4px;
}
.ai-panel-header p {
    margin:0;
    color:#64748b;
}
.ai-panel-body {
    padding:20px;
}
.ai-table-wrap {
    overflow-x:auto;
}
.ai-table {
    width:100%;
    border-collapse:collapse;
}
.ai-table th,
.ai-table td {
    padding:15px;
    border-bottom:1px solid #e2e8f0;
    text-align:left;
    vertical-align:top;
}
.ai-table th {
    background:#f8fafc;
    color:#475569;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.ai-table tr:last-child td {
    border-bottom:0;
}
.ai-code {
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
.ai-capabilities {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.ai-muted {
    color:#64748b;
}
.ai-run-grid {
    display:grid;
    gap:16px;
}
.ai-run-card {
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:18px;
    background:#fff;
}
.ai-run-card h3 {
    margin:0 0 6px;
}
.ai-run-card p {
    margin:0 0 14px;
    color:#64748b;
}
.ai-run-card textarea {
    width:100%;
    min-height:150px;
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    resize:vertical;
    font:inherit;
    box-sizing:border-box;
}
.ai-run-footer {
    display:flex;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    align-items:center;
    margin-top:12px;
}
.ai-response {
    white-space:pre-wrap;
    line-height:1.65;
    color:#0f172a;
}
.ai-response-meta {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:14px;
}
.ai-response-meta span {
    padding:5px 9px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-size:12px;
}
@media(max-width:1100px) {
    .ai-config-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:760px) {
    .ai-summary,
    .ai-config-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="ai-page">
    <header class="ai-header">
        <div>
            <h1>AI Engine</h1>

            <p>
                Configure Alasne's internal AI provider and run
                explicitly authorized manual tests. Automated actions
                remain disabled in this phase.
            </p>
        </div>

        <div class="ai-actions">
            <a
                href="/admin/ai/agents/create"
                class="ai-button"
            >
                Create Agent
            </a>

            <a
                href="/admin/settings"
                class="ai-button secondary"
            >
                Platform Settings
            </a>

            <a
                href="/admin"
                class="ai-button secondary"
            >
                Mission Control
            </a>
        </div>
    </header>

    <?php if ($success): ?>
        <div class="ai-alert success">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="ai-alert error">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <div class="ai-notice">
        AI credentials remain protected environment variables.
        Alasne displays only the configured environment-variable
        <em>name</em> and whether a value exists. Manual prompts and
        responses are not persisted in the AI run audit table; only
        run metadata, usage, latency, hashes, and errors are recorded.
    </div>

    <section class="ai-summary">
        <article class="ai-card">
            <small>Total Agents</small>
            <strong>
                <?= $escape(
                    $summary['total_agents'] ?? 0
                ) ?>
            </strong>
        </article>

        <article class="ai-card">
            <small>Active Agents</small>
            <strong>
                <?= $escape(
                    $summary['active_agents'] ?? 0
                ) ?>
            </strong>
        </article>

        <article class="ai-card">
            <small>Draft Agents</small>
            <strong>
                <?= $escape(
                    $summary['draft_agents'] ?? 0
                ) ?>
            </strong>
        </article>
    </section>

    <section class="ai-config-grid">
        <article class="ai-card">
            <small>AI Engine</small>
            <span class="<?= $escape(
                $badge(
                    ! empty($config['enabled'])
                )
            ) ?>">
                <?= ! empty($config['enabled'])
                    ? 'Enabled'
                    : 'Disabled' ?>
            </span>
        </article>

        <article class="ai-card">
            <small>Provider</small>
            <strong style="font-size:18px;">
                <?= $escape(
                    $config['provider']
                    ?? 'Not configured'
                ) ?>
            </strong>
        </article>

        <article class="ai-card">
            <small>Default Model</small>
            <strong style="font-size:18px;">
                <?= $escape(
                    $config['model']
                    ?? 'Not selected'
                ) ?>
            </strong>
        </article>

        <article class="ai-card">
            <small>Manual Execution</small>
            <span class="<?= $escape(
                $badge($executionAvailable)
            ) ?>">
                <?= $executionAvailable
                    ? 'Available'
                    : 'Locked' ?>
            </span>
        </article>

        <article class="ai-card">
            <small>Credential Variable</small>
            <span class="ai-code">
                <?= $escape(
                    $config['api_key_env']
                    ?? 'Not configured'
                ) ?>
            </span>
        </article>

        <article class="ai-card">
            <small>Credential Presence</small>
            <span class="<?= $escape(
                $badge(
                    ! empty(
                        $config[
                            'credential_configured'
                        ]
                    )
                )
            ) ?>">
                <?= ! empty(
                    $config[
                        'credential_configured'
                    ]
                )
                    ? 'Configured'
                    : 'Missing' ?>
            </span>
        </article>

        <article class="ai-card">
            <small>Manual-Only Guardrail</small>
            <span class="<?= $escape(
                $badge(
                    ! empty(
                        $config[
                            'manual_execution_only'
                        ]
                    )
                )
            ) ?>">
                <?= ! empty(
                    $config[
                        'manual_execution_only'
                    ]
                )
                    ? 'Required'
                    : 'Disabled' ?>
            </span>
        </article>

        <article class="ai-card">
            <small>Configuration Readiness</small>
            <span class="<?= $escape(
                $badge(
                    ! empty(
                        $config[
                            'configuration_ready'
                        ]
                    )
                )
            ) ?>">
                <?= ! empty(
                    $config[
                        'configuration_ready'
                    ]
                )
                    ? 'Ready'
                    : 'Not Ready' ?>
            </span>
        </article>
    </section>

    <section class="ai-panel">
        <header class="ai-panel-header">
            <h2>Agent Definitions</h2>
            <p>
                Draft and active agents may be manually tested only
                when configuration readiness is satisfied.
            </p>
        </header>

        <div class="ai-table-wrap">
            <table class="ai-table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Model Override</th>
                        <th>Output Limit</th>
                        <th>Capabilities</th>
                        <th>Versions</th>
                        <th>Updated</th>
                        <th>Manage</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= $escape(
                                        $agent['name']
                                    ) ?>
                                </strong>
                                <br>
                                <span class="ai-code">
                                    <?= $escape(
                                        $agent['slug']
                                    ) ?>
                                </span>

                                <?php if (
                                    $agent['description'] !== ''
                                ): ?>
                                    <div
                                        class="ai-muted"
                                        style="margin-top:7px;"
                                    >
                                        <?= $escape(
                                            $agent[
                                                'description'
                                            ]
                                        ) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="<?= $escape(
                                    $statusBadge(
                                        (string) $agent['status']
                                    )
                                ) ?>">
                                    <?= $escape(
                                        ucfirst(
                                            (string) $agent['status']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= $agent['model_override'] !== ''
                                    ? $escape(
                                        $agent[
                                            'model_override'
                                        ]
                                    )
                                    : '<span class="ai-muted">Uses platform default</span>' ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $agent[
                                        'max_output_tokens'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <div class="ai-capabilities">
                                    <?php foreach (
                                        $agent['capabilities']
                                        as $capability
                                    ): ?>
                                        <span class="ai-code">
                                            <?= $escape(
                                                $capability
                                            ) ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <?php if (
                                        $agent['capabilities']
                                        === []
                                    ): ?>
                                        <span class="ai-muted">
                                            None
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <?= $escape(
                                    $agent[
                                        'version_count'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $agent['updated_at']
                                    ?? ''
                                ) ?>
                            </td>

                            <td>
                                <div class="ai-actions">
                                    <a
                                        href="/admin/ai/agents/<?= (int) $agent['id'] ?>/edit"
                                        class="ai-button secondary"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="/admin/ai/agents/<?= (int) $agent['id'] ?>/versions"
                                        class="ai-button secondary"
                                    >
                                        Versions
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($agents === []): ?>
                        <tr>
                            <td
                                colspan="8"
                                class="ai-muted"
                            >
                                No AI agents are defined.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section
        class="ai-panel"
        id="manual-execution"
    >
        <header class="ai-panel-header">
            <h2>Manual Agent Test</h2>

            <p>
                Explicit operator-initiated execution only. No tools,
                external actions, scheduled runs, or autonomous loops
                are enabled.
            </p>
        </header>

        <div class="ai-panel-body">
            <?php if (! $executionAvailable): ?>
                <div class="ai-notice">
                    Manual execution is locked. To become ready,
                    Platform Settings must have AI Engine enabled,
                    a model selected, the configured credential
                    environment variable populated, and Manual
                    Execution Only kept enabled.
                </div>
            <?php endif; ?>

            <div
                class="ai-run-grid"
                style="margin-top:16px;"
            >
                <?php foreach ($agents as $agent): ?>
                    <?php
                    $canPrompt =
                        in_array(
                            'manual_prompting',
                            $agent['capabilities'],
                            true
                        )
                        && in_array(
                            $agent['status'],
                            ['draft', 'active'],
                            true
                        );
                    ?>

                    <article class="ai-run-card">
                        <h3>
                            <?= $escape(
                                $agent['name']
                            ) ?>
                        </h3>

                        <p>
                            <?= $escape(
                                $agent['description']
                            ) ?>
                        </p>

                        <form
                            method="POST"
                            action="/admin/ai/agents/<?= $escape(
                                $agent['id']
                            ) ?>/run"
                        >
                            <input
                                type="hidden"
                                name="_csrf_token"
                                value="<?= $escape(
                                    $csrf_token
                                ) ?>"
                            >

                            <textarea
                                name="prompt"
                                maxlength="12000"
                                placeholder="Enter a manual test prompt..."
                                <?= ! (
                                    $executionAvailable
                                    && $canPrompt
                                )
                                    ? 'disabled'
                                    : '' ?>
                            ><?= $escape($old_prompt) ?></textarea>

                            <div class="ai-run-footer">
                                <span class="ai-muted">
                                    Maximum 12,000 characters.
                                    Response content is shown once
                                    and is not written to ai_runs.
                                </span>

                                <button
                                    type="submit"
                                    class="ai-button"
                                    <?= ! (
                                        $executionAvailable
                                        && $canPrompt
                                    )
                                        ? 'disabled'
                                        : '' ?>
                                >
                                    Run Manual Test
                                </button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($last_result): ?>
        <section class="ai-panel">
            <header class="ai-panel-header">
                <h2>
                    Latest Manual Response
                </h2>

                <p>
                    This response is presented from the current
                    session and is not stored in the AI run table.
                </p>
            </header>

            <div class="ai-panel-body">
                <div class="ai-response"><?= $escape(
                    $last_result['text']
                    ?? ''
                ) ?></div>

                <div class="ai-response-meta">
                    <span>
                        Run #<?= $escape(
                            $last_result['run_id']
                            ?? ''
                        ) ?>
                    </span>

                    <span>
                        <?= $escape(
                            $last_result['provider']
                            ?? ''
                        ) ?>
                    </span>

                    <span>
                        <?= $escape(
                            $last_result['model']
                            ?? ''
                        ) ?>
                    </span>

                    <?php if (
                        $last_result[
                            'input_tokens'
                        ] ?? null
                    ): ?>
                        <span>
                            Input:
                            <?= $escape(
                                $last_result[
                                    'input_tokens'
                                ]
                            ) ?>
                            tokens
                        </span>
                    <?php endif; ?>

                    <?php if (
                        $last_result[
                            'output_tokens'
                        ] ?? null
                    ): ?>
                        <span>
                            Output:
                            <?= $escape(
                                $last_result[
                                    'output_tokens'
                                ]
                            ) ?>
                            tokens
                        </span>
                    <?php endif; ?>

                    <span>
                        <?= $escape(
                            $last_result[
                                'latency_ms'
                            ] ?? 0
                        ) ?>
                        ms
                    </span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="ai-panel">
        <header class="ai-panel-header">
            <h2>Recent AI Runs</h2>

            <p>
                Metadata-only audit history. Prompt text and response
                text are intentionally not stored here.
            </p>
        </header>

        <div class="ai-table-wrap">
            <table class="ai-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Provider / Model</th>
                        <th>Usage</th>
                        <th>Latency</th>
                        <th>Operator</th>
                        <th>Created</th>
                        <th>Error</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $recentRuns as $run
                    ): ?>
                        <tr>
                            <td>
                                #<?= $escape(
                                    $run['id']
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $run[
                                        'agent_name'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <span class="<?= $escape(
                                    $statusBadge(
                                        (string) $run[
                                            'status'
                                        ]
                                    )
                                ) ?>">
                                    <?= $escape(
                                        ucfirst(
                                            (string) $run[
                                                'status'
                                            ]
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= $escape(
                                    $run['provider']
                                ) ?>
                                <br>
                                <span class="ai-code">
                                    <?= $escape(
                                        $run['model']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?php if (
                                    $run['total_tokens']
                                    !== null
                                ): ?>
                                    <?= $escape(
                                        $run[
                                            'total_tokens'
                                        ]
                                    ) ?>
                                    total
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $run['latency_ms']
                                    !== null
                                    ? $escape(
                                        $run[
                                            'latency_ms'
                                        ]
                                    ) . ' ms'
                                    : '—' ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $run[
                                        'requested_by_name'
                                    ]
                                    ?? 'System'
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $run['created_at']
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $run[
                                        'error_message'
                                    ] ?? '—'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (
                        $recentRuns === []
                    ): ?>
                        <tr>
                            <td
                                colspan="9"
                                class="ai-muted"
                            >
                                No AI runs have been recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
