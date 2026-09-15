<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$nextRun = '';
if (! empty($task['next_run_at'])) {
    $nextRun = str_replace(' ', 'T', substr((string) $task['next_run_at'], 0, 16));
}
?>

<section class="page-header">
    <div>
        <h1>Edit Scheduled Operation</h1>
        <p><?= $escape($task['name']) ?></p>
    </div>

    <a href="/admin/scheduled-operations" class="button-muted">
        Back to Scheduled Operations
    </a>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <form method="POST" action="/admin/scheduled-operations/<?= $escape($task['id']) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Task Key</label>
                <input type="text" value="<?= $escape($task['task_key']) ?>" readonly>
            </div>

            <div class="form-group">
                <label>Task Type</label>
                <input type="text" value="<?= $escape($label((string) $task['task_type'])) ?>" readonly>
            </div>

            <div class="form-group">
                <label for="name">Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    required
                    value="<?= $escape($task['name']) ?>"
                >
            </div>

            <div class="form-group">
                <label for="schedule_label">Schedule Label</label>
                <input
                    id="schedule_label"
                    type="text"
                    name="schedule_label"
                    value="<?= $escape($task['schedule_label'] ?? '') ?>"
                    placeholder="Hourly, Daily, Weekly"
                >
            </div>

            <div class="form-group">
                <label for="frequency_minutes">Frequency Minutes</label>
                <input
                    id="frequency_minutes"
                    type="number"
                    min="5"
                    name="frequency_minutes"
                    value="<?= $escape($task['frequency_minutes']) ?>"
                >
            </div>

            <div class="form-group">
                <label for="next_run_at">Next Run At</label>
                <input
                    id="next_run_at"
                    type="datetime-local"
                    name="next_run_at"
                    value="<?= $escape($nextRun) ?>"
                >
            </div>

            <div class="form-group">
                <label for="store_id">Store Scope</label>
                <select id="store_id" name="store_id">
                    <option value="">All stores</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= $escape($store['id']) ?>" <?= (int) ($task['store_id'] ?? 0) === (int) $store['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= $escape($supplier['id']) ?>" <?= (int) ($task['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                            <?= $escape($supplier['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_enabled" value="1" <?= ! empty($task['is_enabled']) ? 'checked' : '' ?>>
                    Enabled
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="run_if_due" value="1" <?= ! empty($task['run_if_due']) ? 'checked' : '' ?>>
                    Run when due
                </label>
            </div>

            <div class="form-group form-group-wide">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= $escape($task['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit" class="button-primary">
            Save Scheduled Operation
        </button>
    </form>

    <br>

    <form method="POST" action="/admin/scheduled-operations/<?= $escape($task['id']) ?>/run">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
        <button type="submit" class="button-muted">
            Run Now
        </button>
    </form>
</section>
