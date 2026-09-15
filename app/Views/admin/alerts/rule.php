<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$isEdit = ! empty($rule['id']);
?>

<section class="page-header">
    <div>
        <h1><?= $isEdit ? 'Edit Alert Rule' : 'Create Alert Rule' ?></h1>
        <p>
            Define a Mission Control metric threshold and severity.
        </p>
    </div>

    <a href="/admin/alerts" class="button-muted">
        Back to Alerts
    </a>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <form method="POST" action="<?= $isEdit ? '/admin/alerts/rules/' . $escape($rule['id']) : '/admin/alerts/rules' ?>">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="rule_key">Rule Key</label>
                <input
                    id="rule_key"
                    type="text"
                    name="rule_key"
                    value="<?= $escape($rule['rule_key'] ?? '') ?>"
                    placeholder="custom_metric_rule"
                    <?= $isEdit ? 'readonly' : '' ?>
                >
                <small class="form-help">
                    Leave blank for a generated key on new custom rules.
                </small>
            </div>

            <div class="form-group">
                <label for="name">Rule Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    required
                    value="<?= $escape($rule['name'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="metric_key">Metric</label>
                <select id="metric_key" name="metric_key" required>
                    <?php foreach ($metricKeys as $metric): ?>
                        <option value="<?= $escape($metric) ?>" <?= ($rule['metric_key'] ?? '') === $metric ? 'selected' : '' ?>>
                            <?= $escape($label($metric)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="operator">Operator</label>
                <select id="operator" name="operator">
                    <?php foreach (['greater_than','greater_than_or_equal','less_than','less_than_or_equal','equal'] as $operator): ?>
                        <option value="<?= $escape($operator) ?>" <?= ($rule['operator'] ?? 'greater_than') === $operator ? 'selected' : '' ?>>
                            <?= $escape($label($operator)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="threshold_value">Threshold</label>
                <input
                    id="threshold_value"
                    type="number"
                    step="0.0001"
                    name="threshold_value"
                    value="<?= $escape($rule['threshold_value'] ?? '0.0000') ?>"
                >
            </div>

            <div class="form-group">
                <label for="severity">Severity</label>
                <select id="severity" name="severity">
                    <?php foreach (['critical','warning','info'] as $severity): ?>
                        <option value="<?= $escape($severity) ?>" <?= ($rule['severity'] ?? 'warning') === $severity ? 'selected' : '' ?>>
                            <?= $escape($label($severity)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="store_id">Store Scope</label>
                <select id="store_id" name="store_id">
                    <option value="">All stores</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= $escape($store['id']) ?>" <?= (int) ($rule['store_id'] ?? 0) === (int) $store['id'] ? 'selected' : '' ?>>
                            <?= $escape($store['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="supplier_id">Supplier Scope</label>
                <select id="supplier_id" name="supplier_id">
                    <option value="">All suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $escape($supplier['id']) ?>" <?= (int) ($rule['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                            <?= $escape($supplier['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cooldown_minutes">Cooldown Minutes</label>
                <input
                    id="cooldown_minutes"
                    type="number"
                    min="0"
                    name="cooldown_minutes"
                    value="<?= $escape($rule['cooldown_minutes'] ?? 60) ?>"
                >
            </div>

            <div class="form-group">
                <label for="action_url">Action URL</label>
                <input
                    id="action_url"
                    type="text"
                    name="action_url"
                    value="<?= $escape($rule['action_url'] ?? '') ?>"
                    placeholder="/admin/tracking-reconciliation"
                >
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_enabled" value="1" <?= ! isset($rule['is_enabled']) || ! empty($rule['is_enabled']) ? 'checked' : '' ?>>
                    Enabled
                </label>
            </div>

            <div class="form-group form-group-wide">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= $escape($rule['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit" class="button-primary">
            Save Rule
        </button>
    </form>

    <?php if ($isEdit): ?>
        <br>
        <form method="POST" action="/admin/alerts/rules/<?= $escape($rule['id']) ?>/delete" onsubmit="return confirm('Delete this alert rule?');">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
            <button type="submit" class="button-danger">
                Delete Rule
            </button>
        </form>
    <?php endif; ?>
</section>
