<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$enabled = ! empty($template['is_enabled']);

$samplePayload = json_encode(
    $preview['payload'] ?? [],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

$variablesJson = (string) ($template['variables_json'] ?? '');
$sampleJson = (string) ($template['sample_payload_json'] ?? '');
?>

<style>
.template-editor-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.template-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.template-preview-box {
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px;
    background:#fff;
    white-space:pre-wrap;
}
.template-html-preview {
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px;
    background:#fff;
}
.template-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.template-success { background:#dcfce7; color:#166534; }
.template-warning { background:#fef3c7; color:#92400e; }
.template-info { background:#e0f2fe; color:#075985; }
.template-neutral { background:#e2e8f0; color:#334155; }
.template-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.template-code {
    width:100%;
    min-height:120px;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
@media(max-width:1150px) {
    .template-editor-grid,
    .template-form-grid {
        grid-template-columns:1fr;
    }
}

/* v2 readability hotfix */
.template-readable,
.template-readable input,
.template-readable select,
.template-readable textarea,
.template-readable table,
.template-readable th,
.template-readable td,
.template-readable p,
.template-readable small,
.template-readable label,
.template-readable code,
.template-readable pre {
    color:#0f172a;
}
.template-readable input,
.template-readable select,
.template-readable textarea {
    background:#ffffff;
    color:#0f172a;
    border-color:#cbd5e1;
}
.template-readable input::placeholder,
.template-readable textarea::placeholder {
    color:#64748b;
}
.template-readable .template-preview-box,
.template-readable .template-html-preview,
.template-readable .template-card,
.template-readable .panel,
.template-readable .data-table {
    color:#0f172a;
}
.template-readable .data-table th {
    color:#334155;
}
.template-readable .data-table td {
    color:#0f172a;
}
.template-readable .alert-info,
.template-readable .alert-success,
.template-readable .alert-danger {
    color:#0f172a;
}


/* v3 layout cleanup hotfix */
.template-readable,
.template-readable * {
    box-sizing:border-box;
}
.template-readable {
    max-width:100%;
    overflow-x:hidden;
}
.template-readable .panel,
.template-readable aside,
.template-readable section,
.template-readable article,
.template-readable form,
.template-readable .template-preview-box,
.template-readable .template-html-preview,
.template-readable .template-table-wrap,
.template-readable .table-header,
.template-readable .form-group {
    min-width:0;
    max-width:100%;
}
.template-readable .template-editor-grid,
.template-readable .template-grid,
.template-readable .template-form-grid,
.template-readable .template-filter-grid {
    min-width:0;
    max-width:100%;
}
.template-readable input,
.template-readable select,
.template-readable textarea,
.template-readable pre,
.template-readable code {
    max-width:100%;
}
.template-readable textarea,
.template-readable input {
    width:100%;
}
.template-readable .template-preview-box,
.template-readable .template-html-preview,
.template-readable .template-html-preview *,
.template-readable .data-table,
.template-readable .data-table th,
.template-readable .data-table td,
.template-readable p,
.template-readable small,
.template-readable code,
.template-readable pre,
.template-readable .template-badge {
    overflow-wrap:anywhere;
    word-break:break-word;
}
.template-readable .template-badge {
    white-space:normal;
    max-width:100%;
    line-height:1.35;
    margin:2px 2px 4px 0;
}
.template-readable .template-table-wrap {
    overflow-x:auto;
}
.template-readable .data-table {
    width:100%;
    table-layout:fixed;
}
.template-readable .data-table th,
.template-readable .data-table td {
    vertical-align:top;
}
.template-readable .template-preview-box {
    overflow-x:auto;
}
.template-readable .template-html-preview {
    overflow-x:auto;
}
.template-readable .template-html-preview a {
    display:inline-block;
    max-width:100%;
}
.template-readable .table-header {
    gap:12px;
    flex-wrap:wrap;
}
.template-readable .template-actions {
    min-width:0;
}

</style>

<div class="template-readable">
<section class="page-header">
    <div>
        <h1><?= $escape($template['name']) ?></h1>
        <p>
            <?= $escape($template['template_key']) ?>
            —
            <?= $escape($label((string) $template['category'])) ?>
            /
            <?= $escape($label((string) $template['audience'])) ?>
        </p>
    </div>

    <div class="template-actions">
        <a href="/admin/notification-templates" class="button-muted">Templates</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
        <a href="/admin/email-delivery" class="button-muted">Email Delivery</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="template-editor-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Edit Template</h2>
                <p>
                    Use placeholders like <code>{{customer_name}}</code> and
                    preview with sample JSON before automation sends.
                </p>
            </div>

            <span class="template-badge <?= $enabled ? 'template-success' : 'template-neutral' ?>">
                <?= $enabled ? 'Enabled' : 'Disabled' ?>
            </span>
        </div>

        <form method="POST" action="/admin/notification-templates/<?= $escape($template['id']) ?>" class="form-panel">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div class="template-form-grid">
                <div class="form-group">
                    <label>Template Key</label>
                    <input type="text" value="<?= $escape($template['template_key']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="<?= $escape($template['name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <input
                        id="category"
                        type="text"
                        name="category"
                        value="<?= $escape($template['category']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="audience">Audience</label>
                    <select id="audience" name="audience">
                        <?php foreach (['customer', 'supplier', 'admin'] as $audience): ?>
                            <option value="<?= $escape($audience) ?>" <?= $template['audience'] === $audience ? 'selected' : '' ?>>
                                <?= $escape($label($audience)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= $escape($template['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="subject_template">Subject Template</label>
                <input
                    id="subject_template"
                    type="text"
                    name="subject_template"
                    value="<?= $escape($template['subject_template']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="body_text_template">Text Body Template</label>
                <textarea id="body_text_template" name="body_text_template" rows="10"><?= $escape($template['body_text_template'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="body_html_template">HTML Body Template</label>
                <textarea id="body_html_template" name="body_html_template" rows="10"><?= $escape($template['body_html_template'] ?? '') ?></textarea>
            </div>

            <div class="template-form-grid">
                <div class="form-group">
                    <label for="variables_json">Variables JSON</label>
                    <textarea id="variables_json" name="variables_json" rows="8" class="template-code"><?= $escape($variablesJson) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="sample_payload_json">Sample Payload JSON</label>
                    <textarea id="sample_payload_json" name="sample_payload_json" rows="8" class="template-code"><?= $escape($sampleJson) ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="change_note">Change Note</label>
                <input
                    id="change_note"
                    type="text"
                    name="change_note"
                    placeholder="Describe what changed"
                >
            </div>

            <label>
                <input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                Enabled
            </label>

            <br><br>

            <button type="submit" class="button-primary">
                Save Template
            </button>
        </form>
    </section>

    <aside>
        <section class="panel">
            <h2>Live Preview</h2>

            <?php if (! empty($preview['missing_variables'])): ?>
                <div class="alert-danger">
                    Missing sample values:
                    <?= $escape(implode(', ', $preview['missing_variables'])) ?>
                </div>
            <?php endif; ?>

            <h3>Subject</h3>
            <div class="template-preview-box"><?= $escape($preview['subject'] ?? '') ?></div>

            <h3>Text Body</h3>
            <div class="template-preview-box"><?= $escape($preview['body_text'] ?? '') ?></div>

            <h3>HTML Body</h3>
            <div class="template-html-preview">
                <?= $preview['body_html'] ?? '' ?>
            </div>
        </section>

        <br>

        <section class="panel">
            <h2>Custom Preview Payload</h2>

            <form method="POST" action="/admin/notification-templates/<?= $escape($template['id']) ?>/preview">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                <div class="form-group">
                    <label for="preview_payload_json">Preview JSON</label>
                    <textarea
                        id="preview_payload_json"
                        name="preview_payload_json"
                        rows="10"
                        class="template-code"
                    ><?= $escape($samplePayload ?: '{}') ?></textarea>
                </div>

                <button type="submit" class="button-muted">
                    Preview With JSON
                </button>
            </form>
        </section>

        <br>

        <section class="panel">
            <h2>Available Variables</h2>

            <?php foreach (($preview['available_variables'] ?? []) as $variable): ?>
                <span class="template-badge template-info">{{<?= $escape($variable) ?>}}</span>
            <?php endforeach; ?>

            <?php if (empty($preview['available_variables'])): ?>
                <p>No variables defined.</p>
            <?php endif; ?>
        </section>

        <br>

        <section class="panel">
            <h2>Version History</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Note</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($versions as $version): ?>
                        <tr>
                            <td>#<?= $escape($version['version_number']) ?></td>
                            <td><?= $escape($version['change_note'] ?? '') ?></td>
                            <td><?= $escape($version['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($versions)): ?>
                        <tr>
                            <td colspan="3">No versions yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </aside>
</section>

</div>
