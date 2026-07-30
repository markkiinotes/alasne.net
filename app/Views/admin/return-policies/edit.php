<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$value = static function (
    string $field,
    mixed $default = ''
) use ($old, $policy): mixed {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    return $policy[$field] ?? $default;
};

$checked = static function (
    string $field
) use ($value): string {
    return (int) $value($field, 0) === 1
        ? 'checked'
        : '';
};
?>

<style>
.return-policy-page {
    display: grid;
    gap: 22px;
}

.return-policy-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        360px;
    gap: 22px;
    align-items: start;
}

.return-policy-panel {
    padding: 24px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow:
        0 10px 28px rgba(15, 23, 42, 0.06);
}

.return-policy-options {
    display: grid;
    gap: 14px;
}

.return-policy-option {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.return-policy-option input {
    margin-top: 4px;
}

.return-policy-option strong {
    display: block;
    margin-bottom: 4px;
}

.return-policy-option small {
    color: #64748b;
    line-height: 1.5;
}

.return-policy-alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-weight: 700;
}

.return-policy-alert.success {
    background: #dcfce7;
    color: #166534;
}

.return-policy-alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.return-policy-summary {
    display: grid;
    gap: 14px;
}

.return-policy-summary div {
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.return-policy-summary span {
    display: block;
    margin-bottom: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

@media (max-width: 900px) {
    .return-policy-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="return-policy-page">
    <section class="page-header">
        <h1>Return Policy</h1>

        <p>
            <?= $escape($store['name']) ?>
            · Customer return eligibility and
            storefront policy text
        </p>

        <div class="table-actions">
            <a
                href="/store/<?= $escape(
                    $store['slug']
                ) ?>/returns/policy"
                class="button-muted"
                target="_blank"
            >
                Preview Public Policy
            </a>

            <a
                href="/admin/stores/<?= $escape(
                    $store['id']
                ) ?>"
                class="button-muted"
            >
                Back to Store
            </a>
        </div>
    </section>

    <?php if (! empty($success)): ?>
        <div class="return-policy-alert success">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (! empty($error)): ?>
        <div class="return-policy-alert error">
            <?= $escape($error) ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="/admin/stores/<?= $escape(
            $store['id']
        ) ?>/return-policy"
        class="return-policy-grid"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <section class="return-policy-panel">
            <h2>Eligibility Rules</h2>

            <div class="form-group">
                <label for="return_window_days">
                    Return Request Window
                </label>

                <input
                    id="return_window_days"
                    type="number"
                    name="return_window_days"
                    min="1"
                    max="365"
                    step="1"
                    value="<?= $escape(
                        $value(
                            'return_window_days',
                            30
                        )
                    ) ?>"
                    required
                >

                <small class="form-help">
                    Number of days after the order date
                    during which a customer may submit a
                    return request.
                </small>
            </div>

            <div class="form-group">
                <label for="authorization_valid_days">
                    RMA Authorization Validity
                </label>

                <input
                    id="authorization_valid_days"
                    type="number"
                    name="authorization_valid_days"
                    min="1"
                    max="365"
                    step="1"
                    value="<?= $escape(
                        $value(
                            'authorization_valid_days',
                            30
                        )
                    ) ?>"
                    required
                >

                <small class="form-help">
                    Number of days after approval that the
                    customer has to send the authorized
                    merchandise.
                </small>
            </div>

            <div class="return-policy-options">
                <?php foreach (
                    [
                        'is_enabled' => [
                            'Accept Customer Return Requests',
                            'When disabled, Mission Control may still create returns manually.',
                        ],
                        'require_fulfilled_status' => [
                            'Require Shipped or Completed Status',
                            'Prevents customers from requesting a return before the order is fulfilled.',
                        ],
                        'allow_changed_mind' => [
                            'Allow Changed-Mind Returns',
                            'Customers may select Changed Mind as the return reason.',
                        ],
                        'customer_pays_return_shipping' => [
                            'Customer Pays Return Shipping',
                            'Shown as policy guidance. Automatic label billing is not enabled yet.',
                        ],
                        'auto_approve_customer_requests' => [
                            'Automatically Approve Eligible Requests',
                            'Eligible customer requests move directly to Approved. Leave disabled for manual review.',
                        ],
                    ]
                    as $field => $option
                ): ?>
                    <label class="return-policy-option">
                        <input
                            type="hidden"
                            name="<?= $escape($field) ?>"
                            value="0"
                        >

                        <input
                            type="checkbox"
                            name="<?= $escape($field) ?>"
                            value="1"
                            <?= $checked($field) ?>
                        >

                        <span>
                            <strong>
                                <?= $escape($option[0]) ?>
                            </strong>

                            <small>
                                <?= $escape($option[1]) ?>
                            </small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <br>

            <h2>Customer-Facing Content</h2>

            <div class="form-group">
                <label for="policy_title">
                    Policy Title
                </label>

                <input
                    id="policy_title"
                    type="text"
                    name="policy_title"
                    maxlength="191"
                    value="<?= $escape(
                        $value(
                            'policy_title',
                            '30-Day Return Policy'
                        )
                    ) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="policy_text">
                    Policy Description
                </label>

                <textarea
                    id="policy_text"
                    name="policy_text"
                    rows="7"
                    maxlength="10000"
                ><?= $escape(
                    $value('policy_text')
                ) ?></textarea>
            </div>

            <div class="form-group">
                <label for="return_instructions">
                    Return Instructions
                </label>

                <textarea
                    id="return_instructions"
                    name="return_instructions"
                    rows="7"
                    maxlength="10000"
                ><?= $escape(
                    $value(
                        'return_instructions'
                    )
                ) ?></textarea>
            </div>


            <div class="form-group">
                <label for="return_address_name">
                    Return Address Name
                </label>

                <input
                    id="return_address_name"
                    type="text"
                    name="return_address_name"
                    maxlength="191"
                    value="<?= $escape(
                        $value(
                            'return_address_name'
                        )
                    ) ?>"
                    placeholder="Returns Department"
                >
            </div>

            <div class="form-group">
                <label for="return_address_text">
                    Return Mailing Address
                </label>

                <textarea
                    id="return_address_text"
                    name="return_address_text"
                    rows="6"
                    maxlength="5000"
                    placeholder="Street address&#10;City, State ZIP&#10;Country"
                ><?= $escape(
                    $value(
                        'return_address_text'
                    )
                ) ?></textarea>

                <small class="form-help">
                    This address is hidden until a return
                    is approved and an RMA is issued.
                </small>
            </div>

            <button
                type="submit"
                class="button-primary"
            >
                Save Return Policy
            </button>
        </section>

        <aside class="return-policy-panel">
            <h2>Current Configuration</h2>

            <div class="return-policy-summary">
                <div>
                    <span>Status</span>

                    <strong>
                        <?= (int) $value(
                            'is_enabled',
                            1
                        ) === 1
                            ? 'Customer Returns Enabled'
                            : 'Customer Returns Disabled' ?>
                    </strong>
                </div>

                <div>
                    <span>Window</span>

                    <strong>
                        <?= $escape(
                            $value(
                                'return_window_days',
                                30
                            )
                        ) ?>
                        days from order date
                    </strong>
                </div>

                <div>
                    <span>RMA Validity</span>

                    <strong>
                        <?= $escape(
                            $value(
                                'authorization_valid_days',
                                30
                            )
                        ) ?>
                        days after approval
                    </strong>
                </div>

                <div>
                    <span>Review</span>

                    <strong>
                        <?= (int) $value(
                            'auto_approve_customer_requests',
                            0
                        ) === 1
                            ? 'Automatic approval'
                            : 'Manual approval' ?>
                    </strong>
                </div>

                <div>
                    <span>Return Shipping</span>

                    <strong>
                        <?= (int) $value(
                            'customer_pays_return_shipping',
                            1
                        ) === 1
                            ? 'Customer responsibility'
                            : 'Store responsibility' ?>
                    </strong>
                </div>
            </div>
        </aside>
    </form>
</div>
