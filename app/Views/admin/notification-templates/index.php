<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$summary = $dashboard['summary'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
));

$statusClass = static fn (bool $enabled): string =>
    $enabled
        ? 'template-badge template-success'
        : 'template-badge template-neutral';
?>

<style>
.template-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.template-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
}
.template-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.template-summary strong {
    display:block;
    margin-top:6px;
    font-size:24px;
}
.template-grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
}
.template-filter-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.template-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.template-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
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
.template-info { background:#e0f2fe; color:#075985; }
.template-warning { background:#fef3c7; color:#92400e; }
.template-neutral { background:#e2e8f0; color:#334155; }
.template-table-wrap {
    overflow-x:auto;
}
@media(max-width:1150px) {
    .template-summary,
    .template-grid,
    .template-filter-grid,
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
        <h1>Notification Template Center</h1>
        <p>
            Manage, preview, and version customer, supplier, and admin email templates.
        </p>
    </div>

    <div class="template-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
        <a href="/admin/email-delivery" class="button-muted">Email Delivery</a>
        <a href="/admin/notification-templates/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">Export Templates</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="template-summary">
    <article>
        <small>Total</small>
        <strong><?= $number($summary['total_templates']) ?></strong>
    </article>
    <article>
        <small>Enabled</small>
        <strong><?= $number($summary['enabled_templates']) ?></strong>
    </article>
    <article>
        <small>Disabled</small>
        <strong><?= $number($summary['disabled_templates']) ?></strong>
    </article>
    <article>
        <small>Customer</small>
        <strong><?= $number($summary['customer_templates']) ?></strong>
    </article>
    <article>
        <small>Supplier</small>
        <strong><?= $number($summary['supplier_templates']) ?></strong>
    </article>
    <article>
        <small>Admin</small>
        <strong><?= $number($summary['admin_templates']) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <form method="GET" class="template-filter-grid">
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($dashboard['categories'] as $category): ?>
                    <option value="<?= $escape($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>>
                        <?= $escape($label((string) $category)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="audience">Audience</label>
            <select id="audience" name="audience">
                <option value="">All audiences</option>
                <?php foreach ($dashboard['audiences'] as $audience): ?>
                    <option value="<?= $escape($audience) ?>" <?= ($filters['audience'] ?? '') === $audience ? 'selected' : '' ?>>
                        <?= $escape($label((string) $audience)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="search">Search</label>
            <input
                id="search"
                type="search"
                name="search"
                value="<?= $escape($filters['search'] ?? '') ?>"
                placeholder="Template name, key, or description"
            >
        </div>

        <button type="submit" class="button-primary">Filter</button>
    </form>
</section>

<br>

<section class="template-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Templates</h2>
                <p>Seeded system templates and custom templates for Mission Control notifications.</p>
            </div>
        </div>

        <div class="template-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Category</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Last Preview</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dashboard['templates'] as $template): ?>
                        <tr>
                            <td>
                                <strong><?= $escape($template['name']) ?></strong>
                                <br>
                                <small><?= $escape($template['template_key']) ?></small>
                                <br>
                                <small><?= $escape($template['description'] ?? '') ?></small>
                            </td>
                            <td><?= $escape($label((string) $template['category'])) ?></td>
                            <td><?= $escape($label((string) $template['audience'])) ?></td>
                            <td>
                                <span class="<?= $escape($statusClass(! empty($template['is_enabled']))) ?>">
                                    <?= ! empty($template['is_enabled']) ? 'Enabled' : 'Disabled' ?>
                                </span>
                                <?php if (! empty($template['is_system'])): ?>
                                    <br>
                                    <span class="template-badge template-info">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $escape($template['last_previewed_at'] ?? '—') ?>
                                <br>
                                <small><?= $number($template['preview_count'] ?? 0) ?> preview(s)</small>
                            </td>
                            <td>
                                <a href="/admin/notification-templates/<?= $escape($template['id']) ?>" class="table-link">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dashboard['templates'])): ?>
                        <tr>
                            <td colspan="6">No templates found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside>
        <section class="panel">
            <h2>Create Custom Template</h2>

            <form method="POST" action="/admin/notification-templates" class="form-panel">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                <div class="template-form-grid">
                    <div class="form-group">
                        <label for="template_key">Template Key</label>
                        <input
                            id="template_key"
                            type="text"
                            name="template_key"
                            required
                            placeholder="custom_follow_up"
                        >
                    </div>

                    <div class="form-group">
                        <label for="name">Name</label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            required
                            placeholder="Custom Follow-Up"
                        >
                    </div>

                    <div class="form-group">
                        <label for="category_new">Category</label>
                        <input
                            id="category_new"
                            type="text"
                            name="category"
                            value="general"
                        >
                    </div>

                    <div class="form-group">
                        <label for="audience_new">Audience</label>
                        <select id="audience_new" name="audience">
                            <option value="customer">Customer</option>
                            <option value="supplier">Supplier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject_template">Subject</label>
                    <input
                        id="subject_template"
                        type="text"
                        name="subject_template"
                        required
                        placeholder="Update for {{customer_name}}"
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="body_text_template">Text Body</label>
                    <textarea
                        id="body_text_template"
                        name="body_text_template"
                        rows="6"
                        placeholder="Hi {{customer_name}}, ..."
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="variables_json">Variables JSON</label>
                    <textarea id="variables_json" name="variables_json" rows="4">[
  "customer_name"
]</textarea>
                </div>

                <div class="form-group">
                    <label for="sample_payload_json">Sample Payload JSON</label>
                    <textarea id="sample_payload_json" name="sample_payload_json" rows="4">{
  "customer_name": "Jordan Customer"
}</textarea>
                </div>

                <label>
                    <input type="checkbox" name="is_enabled" value="1" checked>
                    Enabled
                </label>

                <br><br>

                <button type="submit" class="button-primary">
                    Create Template
                </button>
            </form>
        </section>

        <br>

        <section class="panel">
            <h2>Recent Template Events</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Template</th>
                        <th>When</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dashboard['events'] as $event): ?>
                        <tr>
                            <td><?= $escape($label((string) $event['event_type'])) ?></td>
                            <td>
                                <?= $escape($event['template_key'] ?? '') ?>
                                <br>
                                <small><?= $escape($event['message'] ?? '') ?></small>
                            </td>
                            <td><?= $escape($event['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dashboard['events'])): ?>
                        <tr>
                            <td colspan="3">No template events yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </aside>
</section>

</div>
