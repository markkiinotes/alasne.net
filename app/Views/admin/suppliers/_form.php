<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$value = static fn (
    string $key,
    mixed $default = ''
): mixed => $supplier[$key] ?? $default;
?>

<div class="supplier-form-grid">
    <div class="form-group">
        <label for="store_id">Store</label>
        <select
            id="store_id"
            name="store_id"
            required
        >
            <option value="">Select store</option>
            <?php foreach ($stores as $store): ?>
                <option
                    value="<?= $escape($store['id']) ?>"
                    <?= (int) $value('store_id') ===
                        (int) $store['id']
                            ? 'selected'
                            : '' ?>
                >
                    <?= $escape($store['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="name">Supplier Name</label>
        <input
            id="name"
            type="text"
            name="name"
            maxlength="191"
            value="<?= $escape($value('name')) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="code">Supplier Code</label>
        <input
            id="code"
            type="text"
            name="code"
            maxlength="80"
            value="<?= $escape($value('code')) ?>"
            placeholder="CJ-US, PRINTFUL, DIRECT-01"
            required
        >
    </div>

    <div class="form-group">
        <label for="supplier_type">
            Supplier Type
        </label>
        <select
            id="supplier_type"
            name="supplier_type"
        >
            <?php foreach ([
                'manual' => 'Manual / Direct',
                'aliexpress' => 'AliExpress',
                'cjdropshipping' => 'CJdropshipping',
                'printful' => 'Printful',
                'spocket' => 'Spocket',
                'wholesaler' => 'Wholesaler',
                'other' => 'Other',
            ] as $type => $label): ?>
                <option
                    value="<?= $escape($type) ?>"
                    <?= $value(
                        'supplier_type',
                        'manual'
                    ) === $type
                        ? 'selected'
                        : '' ?>
                >
                    <?= $escape($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option
                value="active"
                <?= $value(
                    'status',
                    'active'
                ) === 'active'
                    ? 'selected'
                    : '' ?>
            >
                Active
            </option>
            <option
                value="inactive"
                <?= $value('status') === 'inactive'
                    ? 'selected'
                    : '' ?>
            >
                Inactive
            </option>
        </select>
    </div>

    <div class="form-group">
        <label for="priority">
            Supplier Priority
        </label>
        <input
            id="priority"
            type="number"
            name="priority"
            min="1"
            max="65535"
            value="<?= $escape(
                $value('priority', 100)
            ) ?>"
            required
        >
        <small class="form-help">
            Lower numbers are preferred.
        </small>
    </div>

    <div class="form-group">
        <label for="contact_name">
            Contact Name
        </label>
        <input
            id="contact_name"
            type="text"
            name="contact_name"
            maxlength="191"
            value="<?= $escape(
                $value('contact_name')
            ) ?>"
        >
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input
            id="email"
            type="email"
            name="email"
            maxlength="191"
            value="<?= $escape($value('email')) ?>"
        >
    </div>

    <div class="form-group">
        <label for="phone">Phone</label>
        <input
            id="phone"
            type="text"
            name="phone"
            maxlength="80"
            value="<?= $escape($value('phone')) ?>"
        >
    </div>

    <div class="form-group">
        <label for="website">Website</label>
        <input
            id="website"
            type="url"
            name="website"
            maxlength="1000"
            value="<?= $escape($value('website')) ?>"
        >
    </div>

    <div class="form-group">
        <label for="account_reference">
            Account Reference
        </label>
        <input
            id="account_reference"
            type="text"
            name="account_reference"
            maxlength="191"
            value="<?= $escape(
                $value('account_reference')
            ) ?>"
        >
    </div>

    <div class="form-group">
        <label for="currency">Currency</label>
        <input
            id="currency"
            type="text"
            name="currency"
            maxlength="3"
            value="<?= $escape(
                $value('currency', 'USD')
            ) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="default_lead_time_min">
            Minimum Lead Days
        </label>
        <input
            id="default_lead_time_min"
            type="number"
            name="default_lead_time_min"
            min="0"
            value="<?= $escape(
                $value('default_lead_time_min')
            ) ?>"
        >
    </div>

    <div class="form-group">
        <label for="default_lead_time_max">
            Maximum Lead Days
        </label>
        <input
            id="default_lead_time_max"
            type="number"
            name="default_lead_time_max"
            min="0"
            value="<?= $escape(
                $value('default_lead_time_max')
            ) ?>"
        >
    </div>
</div>

<div class="form-group">
    <label>
        <input
            type="checkbox"
            name="auto_submit"
            value="1"
            <?= ! empty($value('auto_submit'))
                ? 'checked'
                : '' ?>
        >
        Automatically submit when a provider
        adapter becomes available
    </label>
</div>

<div class="form-group">
    <label for="notes">Internal Notes</label>
    <textarea
        id="notes"
        name="notes"
        rows="5"
    ><?= $escape($value('notes')) ?></textarea>
</div>
