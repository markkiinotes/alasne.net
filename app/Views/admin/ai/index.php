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

$badge = static function (bool $ok): string {
    return $ok
        ? 'ai-badge ok'
        : 'ai-badge warn';
};
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
    max-width:820px;
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
    border-radius:10px;
    background:#111827;
    color:#fff;
    text-decoration:none;
    font-weight:800;
}
.ai-button.secondary {
    background:#e2e8f0;
    color:#0f172a;
}
.ai-notice {
    padding:16px 18px;
    border:1px solid #bfdbfe;
    border-radius:14px;
    background:#eff6ff;
    color:#1e3a8a;
    line-height:1.6;
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
                Configure Alasne's internal AI foundation and review
                agent definitions. Outbound AI execution is intentionally
                unavailable in this foundation phase.
            </p>
        </div>

        <div class="ai-actions">
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

    <div class="ai-notice">
        AI credentials remain protected environment variables.
        This page may display the configured environment-variable
        <em>name</em> and whether a value exists, but it never displays
        the credential itself. AI execution will be added only after
        configuration, provider, and guardrail acceptance is complete.
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
            <div class="ai-config-value">
                <span class="<?= $escape(
                    $badge(
                        ! empty($config['enabled'])
                    )
                ) ?>">
                    <?= ! empty($config['enabled'])
                        ? 'Enabled'
                        : 'Disabled' ?>
                </span>
            </div>
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
            <small>Execution</small>
            <div class="ai-config-value">
                <span class="<?= $escape(
                    $badge(
                        ! empty(
                            $config[
                                'execution_available'
                            ]
                        )
                    )
                ) ?>">
                    <?= ! empty(
                        $config[
                            'execution_available'
                        ]
                    )
                        ? 'Available'
                        : 'Foundation Only' ?>
                </span>
            </div>
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
            <div class="ai-config-value">
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
            </div>
        </article>

        <article class="ai-card">
            <small>Manual-Only Guardrail</small>
            <div class="ai-config-value">
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
            </div>
        </article>

        <article class="ai-card">
            <small>Configuration Readiness</small>
            <div class="ai-config-value">
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
            </div>
        </article>
    </section>

    <section class="ai-panel">
        <header class="ai-panel-header">
            <h2>Agent Definitions</h2>
            <p>
                Agents are configuration records only in this phase.
                No agent can execute or call an external provider yet.
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
                                <?php
                                $status =
                                    (string) $agent['status'];
                                ?>
                                <span class="ai-badge <?= $status === 'active'
                                    ? 'ok'
                                    : 'neutral' ?>">
                                    <?= $escape(
                                        ucfirst($status)
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
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($agents === []): ?>
                        <tr>
                            <td
                                colspan="7"
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
</div>
