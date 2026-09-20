<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$groups = $dashboard['groups'] ?? [];
$history = $dashboard['history'] ?? [];
$environment = $dashboard['environment'] ?? [];

$submittedValue = static function (
    array $setting
) use ($old): mixed {
    $key = (string) ($setting['setting_key'] ?? '');

    if (array_key_exists($key, $old)) {
        return $old[$key];
    }

    return $setting['typed_value'] ?? '';
};
?>

<style>
.platform-settings-page {
    display:grid;
    gap:22px;
}
.platform-settings-header {
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
    flex-wrap:wrap;
}
.platform-settings-header h1 {
    margin:0 0 8px;
}
.platform-settings-header p {
    margin:0;
    color:#64748b;
    line-height:1.55;
    max-width:780px;
}
.platform-settings-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.platform-settings-button {
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
.platform-settings-button.secondary {
    background:#e2e8f0;
    color:#0f172a;
}
.platform-settings-alert,
.platform-settings-notice {
    padding:14px 16px;
    border-radius:12px;
    line-height:1.55;
}
.platform-settings-alert.success {
    background:#dcfce7;
    color:#166534;
    font-weight:700;
}
.platform-settings-alert.error {
    background:#fee2e2;
    color:#991b1b;
    font-weight:700;
}
.platform-settings-notice {
    border:1px solid #bfdbfe;
    background:#eff6ff;
    color:#1e3a8a;
}
.platform-settings-environment {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
}
.platform-settings-environment article,
.platform-settings-panel {
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
}
.platform-settings-environment article {
    padding:18px;
}
.platform-settings-environment small {
    display:block;
    margin-bottom:5px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.platform-settings-environment strong {
    word-break:break-word;
}
.platform-settings-panel {
    overflow:hidden;
}
.platform-settings-panel-header {
    padding:18px 20px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}
.platform-settings-panel-header h2 {
    margin:0;
}
.platform-settings-fields {
    display:grid;
}
.platform-setting-row {
    display:grid;
    grid-template-columns:minmax(230px,.8fr) minmax(320px,1.2fr);
    gap:24px;
    padding:20px;
    border-bottom:1px solid #e2e8f0;
    align-items:start;
}
.platform-setting-row:last-child {
    border-bottom:0;
}
.platform-setting-meta {
    display:grid;
    gap:6px;
}
.platform-setting-meta strong {
    font-size:15px;
}
.platform-setting-meta p {
    margin:0;
    color:#64748b;
    line-height:1.5;
}
.platform-setting-key {
    display:inline-flex;
    width:max-content;
    max-width:100%;
    padding:4px 8px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-family:Consolas,Monaco,monospace;
    font-size:11px;
    word-break:break-all;
}
.platform-setting-control {
    display:grid;
    gap:7px;
}
.platform-setting-control input,
.platform-setting-control select,
.platform-setting-control textarea {
    width:100%;
    min-height:42px;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:9px;
    background:#fff;
    color:#0f172a;
    font:inherit;
    box-sizing:border-box;
}
.platform-setting-control textarea {
    min-height:100px;
    resize:vertical;
}
.platform-setting-control small {
    color:#64748b;
}
.platform-settings-footer {
    display:flex;
    justify-content:flex-end;
    padding:18px 20px;
    border-top:1px solid #e2e8f0;
    background:#f8fafc;
}
.platform-settings-table-wrap {
    overflow-x:auto;
}
.platform-settings-table {
    width:100%;
    border-collapse:collapse;
}
.platform-settings-table th,
.platform-settings-table td {
    padding:13px 15px;
    border-bottom:1px solid #e2e8f0;
    text-align:left;
    vertical-align:top;
}
.platform-settings-table th {
    background:#f8fafc;
    color:#475569;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.platform-settings-table tr:last-child td {
    border-bottom:0;
}
.platform-settings-value {
    max-width:280px;
    white-space:normal;
    word-break:break-word;
    color:#334155;
}
.platform-settings-muted {
    color:#64748b;
}
@media(max-width:900px) {
    .platform-settings-environment,
    .platform-setting-row {
        grid-template-columns:1fr;
    }
}
</style>

<div class="platform-settings-page">
    <header class="platform-settings-header">
        <div>
            <h1>Platform Settings</h1>

            <p>
                Manage global, non-secret Alasne defaults.
                Store-specific checkout, tax, shipping, return,
                and payment settings remain under each store.
            </p>
        </div>

        <div class="platform-settings-actions">
            <a
                href="/admin"
                class="platform-settings-button secondary"
            >
                Mission Control
            </a>

            <a
                href="/admin/production-readiness"
                class="platform-settings-button secondary"
            >
                Production Readiness
            </a>
        </div>
    </header>

    <?php if ($success): ?>
        <div
            class="platform-settings-alert success"
            role="alert"
        >
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            class="platform-settings-alert error"
            role="alert"
        >
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <div class="platform-settings-notice">
        Credentials and secrets do not belong here. Database
        passwords, Stripe secrets, SMTP passwords, AI API keys,
        application keys, and supplier credentials remain protected
        environment variables on XAMPP or the production host.
        Future integrations may store only an environment-variable
        <em>name</em> here, never the secret value itself.
    </div>

    <section class="platform-settings-environment">
        <article>
            <small>Runtime Environment</small>
            <strong>
                <?= $escape(
                    strtoupper(
                        (string) (
                            $environment['app_env']
                            ?? 'local'
                        )
                    )
                ) ?>
            </strong>
        </article>

        <article>
            <small>Configured APP_URL</small>
            <strong>
                <?= $escape(
                    $environment['app_url']
                    ?? ''
                ) ?>
            </strong>
        </article>
    </section>

    <form
        method="POST"
        action="/admin/settings"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="platform-settings-page">
            <?php foreach ($groups as $group): ?>
                <section class="platform-settings-panel">
                    <header class="platform-settings-panel-header">
                        <h2>
                            <?= $escape($group['label']) ?>
                        </h2>
                    </header>

                    <div class="platform-settings-fields">
                        <?php foreach (
                            $group['settings'] as $setting
                        ): ?>
                            <?php
                            $key = (string) $setting['setting_key'];
                            $type = (string) $setting['value_type'];
                            $value = $submittedValue($setting);
                            $options = $setting['options'] ?? [];
                            $validation =
                                $setting['validation'] ?? [];
                            ?>

                            <div class="platform-setting-row">
                                <div class="platform-setting-meta">
                                    <strong>
                                        <?= $escape(
                                            $setting['label']
                                        ) ?>
                                    </strong>

                                    <span class="platform-setting-key">
                                        <?= $escape($key) ?>
                                    </span>

                                    <?php if (
                                        ! empty(
                                            $setting['description']
                                        )
                                    ): ?>
                                        <p>
                                            <?= $escape(
                                                $setting[
                                                    'description'
                                                ]
                                            ) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="platform-setting-control">
                                    <?php if ($type === 'select'): ?>
                                        <select
                                            name="settings[<?= $escape($key) ?>]"
                                            <?= ! empty(
                                                $validation['required']
                                            ) ? 'required' : '' ?>
                                        >
                                            <?php foreach (
                                                $options
                                                as $optionValue
                                                => $optionLabel
                                            ): ?>
                                                <option
                                                    value="<?= $escape($optionValue) ?>"
                                                    <?= (string) $value === (string) $optionValue
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= $escape(
                                                        $optionLabel
                                                    ) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (
                                        $type === 'timezone'
                                    ): ?>
                                        <select
                                            name="settings[<?= $escape($key) ?>]"
                                            required
                                        >
                                            <?php foreach (
                                                timezone_identifiers_list()
                                                as $timezone
                                            ): ?>
                                                <option
                                                    value="<?= $escape($timezone) ?>"
                                                    <?= (string) $value === $timezone
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= $escape($timezone) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (
                                        $type === 'integer'
                                    ): ?>
                                        <input
                                            type="number"
                                            name="settings[<?= $escape($key) ?>]"
                                            value="<?= $escape($value) ?>"
                                            <?= isset($validation['min'])
                                                ? 'min="' . $escape($validation['min']) . '"'
                                                : '' ?>
                                            <?= isset($validation['max'])
                                                ? 'max="' . $escape($validation['max']) . '"'
                                                : '' ?>
                                            <?= ! empty(
                                                $validation['required']
                                            ) ? 'required' : '' ?>
                                        >
                                    <?php elseif (
                                        $type === 'email'
                                    ): ?>
                                        <input
                                            type="email"
                                            name="settings[<?= $escape($key) ?>]"
                                            value="<?= $escape($value) ?>"
                                            <?= ! empty(
                                                $validation['required']
                                            ) ? 'required' : '' ?>
                                        >
                                    <?php elseif (
                                        $type === 'text'
                                    ): ?>
                                        <textarea
                                            name="settings[<?= $escape($key) ?>]"
                                            <?= ! empty(
                                                $validation['required']
                                            ) ? 'required' : '' ?>
                                        ><?= $escape($value) ?></textarea>
                                    <?php elseif (
                                        $type === 'boolean'
                                    ): ?>
                                        <input
                                            type="hidden"
                                            name="settings[<?= $escape($key) ?>]"
                                            value="0"
                                        >

                                        <label>
                                            <input
                                                type="checkbox"
                                                name="settings[<?= $escape($key) ?>]"
                                                value="1"
                                                <?= $value ? 'checked' : '' ?>
                                                style="width:auto;min-height:auto;"
                                            >
                                            Enabled
                                        </label>
                                    <?php else: ?>
                                        <input
                                            type="text"
                                            name="settings[<?= $escape($key) ?>]"
                                            value="<?= $escape($value) ?>"
                                            <?= ! empty(
                                                $validation['required']
                                            ) ? 'required' : '' ?>
                                        >
                                    <?php endif; ?>

                                    <?php if (
                                        ! empty(
                                            $setting['updated_at']
                                        )
                                    ): ?>
                                        <small>
                                            Last updated
                                            <?= $escape(
                                                $setting['updated_at']
                                            ) ?>
                                            <?php if (
                                                ! empty(
                                                    $setting[
                                                        'updated_by_name'
                                                    ]
                                                )
                                            ): ?>
                                                by
                                                <?= $escape(
                                                    $setting[
                                                        'updated_by_name'
                                                    ]
                                                ) ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="platform-settings-panel">
                <div class="platform-settings-footer">
                    <button
                        type="submit"
                        class="platform-settings-button"
                    >
                        Save Platform Settings
                    </button>
                </div>
            </section>
        </div>
    </form>

    <section class="platform-settings-panel">
        <header class="platform-settings-panel-header">
            <h2>Recent Setting Changes</h2>
        </header>

        <div class="platform-settings-table-wrap">
            <table class="platform-settings-table">
                <thead>
                    <tr>
                        <th>Changed</th>
                        <th>Setting</th>
                        <th>Previous</th>
                        <th>New</th>
                        <th>Operator</th>
                        <th>Source</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td>
                                <?= $escape(
                                    $entry['created_at']
                                ) ?>
                            </td>

                            <td>
                                <span class="platform-setting-key">
                                    <?= $escape(
                                        $entry['setting_key']
                                    ) ?>
                                </span>
                            </td>

                            <td class="platform-settings-value">
                                <?= $escape(
                                    $entry[
                                        'old_value_text'
                                    ] ?? ''
                                ) ?>
                            </td>

                            <td class="platform-settings-value">
                                <?= $escape(
                                    $entry[
                                        'new_value_text'
                                    ] ?? ''
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $entry[
                                        'changed_by_name'
                                    ]
                                    ?? 'System'
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $entry[
                                        'change_source'
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($history === []): ?>
                        <tr>
                            <td
                                colspan="6"
                                class="platform-settings-muted"
                            >
                                No platform setting changes have
                                been recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
