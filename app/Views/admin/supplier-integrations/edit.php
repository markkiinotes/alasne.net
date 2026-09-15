<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$selectedProvider = (string) (
    $integration['provider_code']
    ?? 'manual_direct'
);

$providerCapabilities =
    $capabilities[$selectedProvider] ?? [];
?>

<style>
.integration-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.integration-kpis {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.integration-kpis article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.integration-kpis small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.env-state {
    display:inline-flex;
    padding:3px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.env-ok {
    background:#dcfce7;
    color:#166534;
}
.env-missing {
    background:#fef3c7;
    color:#92400e;
}
.capability-list {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.capability-list span {
    padding:5px 9px;
    border-radius:999px;
    background:#e2e8f0;
    font-size:12px;
}
@media(max-width:900px) {
    .integration-grid,
    .integration-kpis {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Supplier Integration</h1>
        <p>
            <?= $escape($supplier['name']) ?>
            ·
            <?= $escape($supplier['code']) ?>
            ·
            <?= $escape($supplier['store_name']) ?>
        </p>
    </div>

    <div class="table-actions">
        <a
            href="/admin/supplier-submissions?supplier_id=<?= $escape(
                $supplier['id']
            ) ?>"
            class="button-muted"
        >
            Submission Queue
        </a>

        <a
            href="/admin/suppliers/<?= $escape(
                $supplier['id']
            ) ?>"
            class="button-muted"
        >
            Back to Supplier
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success">
        <?= $escape($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger">
        <?= $escape($error) ?>
    </div>
<?php endif; ?>

<section class="integration-kpis">
    <article>
        <small>Provider</small>
        <strong>
            <?= $escape(
                $providerOptions[
                    $selectedProvider
                ] ?? $selectedProvider
            ) ?>
        </strong>
    </article>

    <article>
        <small>Integration Status</small>
        <strong>
            <?= $escape(
                ucwords(
                    $integration['status']
                    ?? 'inactive'
                )
            ) ?>
        </strong>
    </article>

    <article>
        <small>Last Catalog Sync</small>
        <strong>
            <?= $escape(
                $integration[
                    'last_catalog_sync_at'
                ] ?? 'Never'
            ) ?>
        </strong>
    </article>

    <article>
        <small>Last Inventory Sync</small>
        <strong>
            <?= $escape(
                $integration[
                    'last_inventory_sync_at'
                ] ?? 'Never'
            ) ?>
        </strong>
    </article>
</section>

<br>

<section class="panel form-panel">
    <h2>Adapter Configuration</h2>

    <div class="capability-list">
        <?php foreach (
            $providerCapabilities
            as $capability => $enabled
        ): ?>
            <span>
                <?= $escape(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $capability
                        )
                    )
                ) ?>
               :
                <?= $enabled ? 'Yes' : 'No' ?>
            </span>
        <?php endforeach; ?>
    </div>

    <br>

    <form
        method="POST"
        action="/admin/suppliers/<?= $escape(
            $supplier['id']
        ) ?>/integration"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="integration-grid">
            <div class="form-group">
                <label for="provider_code">
                    Provider Adapter
                </label>
                <select
                    id="provider_code"
                    name="provider_code"
                    required
                >
                    <?php foreach (
                        $providerOptions
                        as $code => $label
                    ): ?>
                        <option
                            value="<?= $escape($code) ?>"
                            <?= $selectedProvider ===
                                $code
                                    ? 'selected'
                                    : '' ?>
                        >
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">
                    Integration Status
                </label>
                <select id="status" name="status">
                    <option
                        value="active"
                        <?= (
                            $integration['status']
                            ?? ''
                        ) === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>
                    <option
                        value="inactive"
                        <?= (
                            $integration['status']
                            ?? ''
                        ) === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="mode">Mode</label>
                <select id="mode" name="mode">
                    <option
                        value="test"
                        <?= (
                            $integration['mode']
                            ?? ''
                        ) === 'test'
                            ? 'selected'
                            : '' ?>
                    >
                        Test
                    </option>
                    <option
                        value="live"
                        <?= (
                            $integration['mode']
                            ?? ''
                        ) === 'live'
                            ? 'selected'
                            : '' ?>
                    >
                        Live
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="purchase_order_email">
                    Purchase-Order Email
                </label>
                <input
                    id="purchase_order_email"
                    type="email"
                    name="purchase_order_email"
                    value="<?= $escape(
                        $integration[
                            'purchase_order_email'
                        ] ?? ''
                    ) ?>"
                >
            </div>

            <div class="form-group">
                <label for="catalog_feed_url">
                    Catalog Feed URL
                </label>
                <input
                    id="catalog_feed_url"
                    type="url"
                    name="catalog_feed_url"
                    value="<?= $escape(
                        $integration[
                            'catalog_feed_url'
                        ] ?? ''
                    ) ?>"
                    placeholder="https://supplier.example/catalog.csv"
                >
            </div>

            <div class="form-group">
                <label for="endpoint_url">
                    Future API Endpoint
                </label>
                <input
                    id="endpoint_url"
                    type="url"
                    name="endpoint_url"
                    value="<?= $escape(
                        $integration[
                            'endpoint_url'
                        ] ?? ''
                    ) ?>"
                >
            </div>

            <?php foreach ([
                'api_key_env' =>
                    'API Key Environment Variable',
                'api_secret_env' =>
                    'API Secret Environment Variable',
                'account_id_env' =>
                    'Account ID Environment Variable',
            ] as $field => $label): ?>
                <div class="form-group">
                    <label for="<?= $escape($field) ?>">
                        <?= $escape($label) ?>
                    </label>

                    <input
                        id="<?= $escape($field) ?>"
                        type="text"
                        name="<?= $escape($field) ?>"
                        value="<?= $escape(
                            $integration[$field]
                            ?? ''
                        ) ?>"
                        placeholder="SUPPLIER_API_KEY"
                    >

                    <?php
                    $state =
                        $environmentStatus[$field]
                        ?? [
                            'name' => '',
                            'configured' => false,
                        ];
                    ?>

                    <?php if (
                        $state['name'] !== ''
                    ): ?>
                        <span
                            class="env-state <?= $state[
                                'configured'
                            ]
                                ? 'env-ok'
                                : 'env-missing' ?>"
                        >
                            <?= $state['configured']
                                ? 'Environment value found'
                                : 'Environment value missing' ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label>
                <input
                    type="checkbox"
                    name="auto_prepare_orders"
                    value="1"
                    <?= ! empty(
                        $integration[
                            'auto_prepare_orders'
                        ]
                    )
                        ? 'checked'
                        : '' ?>
                >
                Automatically prepare a supplier submission
                when routing creates a purchase order
            </label>
        </div>

        <div class="form-group">
            <label>
                <input
                    type="checkbox"
                    name="auto_submit_orders"
                    value="1"
                    <?= ! empty(
                        $integration[
                            'auto_submit_orders'
                        ]
                    )
                        ? 'checked'
                        : '' ?>
                >
                Automatically transmit supplier orders
            </label>

            <small class="form-help">
                The current manual and CSV adapters do not
                transmit orders. Leave this disabled until an
                authenticated API adapter is installed.
            </small>
        </div>

        <div class="form-group">
            <label for="default_order_notes">
                Default Supplier Order Notes
            </label>
            <textarea
                id="default_order_notes"
                name="default_order_notes"
                rows="5"
            ><?= $escape(
                $integration[
                    'default_order_notes'
                ] ?? ''
            ) ?></textarea>
        </div>

        <button
            type="submit"
            class="button-primary"
        >
            Save Integration
        </button>
    </form>
</section>

<?php if (
    $selectedProvider === 'csv_feed'
): ?>
    <br>

    <section class="panel form-panel">
        <div class="table-header">
            <div>
                <h2>Catalog and Inventory Import</h2>
                <p>
                    Upload the supplier's CSV feed. Valid rows
                    are committed even when other rows contain
                    errors.
                </p>
            </div>

            <div class="table-actions">
                <a
                    href="/admin/suppliers/<?= $escape(
                        $supplier['id']
                    ) ?>/integration/template?sync_type=catalog"
                    class="button-muted"
                >
                    Catalog Template
                </a>

                <a
                    href="/admin/suppliers/<?= $escape(
                        $supplier['id']
                    ) ?>/integration/template?sync_type=inventory"
                    class="button-muted"
                >
                    Inventory Template
                </a>
            </div>
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            action="/admin/suppliers/<?= $escape(
                $supplier['id']
            ) ?>/integration/import"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= $escape($csrf_token) ?>"
            >

            <div class="integration-grid">
                <div class="form-group">
                    <label for="sync_type">
                        Import Type
                    </label>
                    <select
                        id="sync_type"
                        name="sync_type"
                    >
                        <option value="catalog">
                            Catalog and Mapping
                        </option>
                        <option value="inventory">
                            Inventory and Cost Update
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="supplier_csv">
                        Supplier CSV
                    </label>
                    <input
                        id="supplier_csv"
                        type="file"
                        name="supplier_csv"
                        accept=".csv,text/csv"
                        required
                    >
                    <small class="form-help">
                        Maximum file size: 10 MB.
                    </small>
                </div>
            </div>

            <button
                type="submit"
                class="button-primary"
            >
                Import CSV
            </button>
        </form>
    </section>
<?php endif; ?>

<br>

<section class="panel">
    <h2>Sync History</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Run</th>
                <th>Type</th>
                <th>Status</th>
                <th>Source</th>
                <th>Received</th>
                <th>Created</th>
                <th>Updated</th>
                <th>Skipped</th>
                <th>Failed</th>
                <th>Finished</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($syncRuns as $run): ?>
                <tr>
                    <td>#<?= $escape($run['id']) ?></td>
                    <td>
                        <?= $escape(
                            ucwords(
                                $run['sync_type']
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                $run['status']
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run[
                                'source_file_name'
                            ]
                            ?? $run['source_name']
                            ?? '—'
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['rows_received']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['rows_created']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['rows_updated']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['rows_skipped']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['rows_failed']
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $run['finished_at']
                            ?? 'Running'
                        ) ?>
                    </td>
                    <td>
                        <a
                            href="/admin/suppliers/<?= $escape(
                                $supplier['id']
                            ) ?>/integration/sync-runs/<?= $escape(
                                $run['id']
                            ) ?>"
                            class="table-link"
                        >
                            Details
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($syncRuns)): ?>
                <tr>
                    <td colspan="11">
                        No supplier feed syncs have run.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
