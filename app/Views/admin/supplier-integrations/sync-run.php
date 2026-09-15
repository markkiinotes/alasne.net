<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
?>

<section class="page-header">
    <div>
        <h1>Supplier Sync Run #<?= $escape(
            $run['id']
        ) ?></h1>

        <p>
            <?= $escape($supplier['name']) ?>
            ·
            <?= $escape(
                ucwords($run['sync_type'])
            ) ?>
            ·
            <?= $escape(
                ucwords($run['status'])
            ) ?>
        </p>
    </div>

    <a
        href="/admin/suppliers/<?= $escape(
            $supplier['id']
        ) ?>/integration"
        class="button-muted"
    >
        Back to Integration
    </a>
</section>

<section class="panel">
    <table class="detail-table">
        <tr>
            <th>Source</th>
            <td>
                <?= $escape(
                    $run['source_file_name']
                    ?? $run['source_name']
                    ?? '—'
                ) ?>
            </td>
        </tr>
        <tr>
            <th>Started</th>
            <td><?= $escape($run['started_at']) ?></td>
        </tr>
        <tr>
            <th>Finished</th>
            <td>
                <?= $escape(
                    $run['finished_at']
                    ?? 'Running'
                ) ?>
            </td>
        </tr>
        <tr>
            <th>Received</th>
            <td><?= $escape($run['rows_received']) ?></td>
        </tr>
        <tr>
            <th>Processed</th>
            <td><?= $escape($run['rows_processed']) ?></td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= $escape($run['rows_created']) ?></td>
        </tr>
        <tr>
            <th>Updated</th>
            <td><?= $escape($run['rows_updated']) ?></td>
        </tr>
        <tr>
            <th>Skipped</th>
            <td><?= $escape($run['rows_skipped']) ?></td>
        </tr>
        <tr>
            <th>Failed</th>
            <td><?= $escape($run['rows_failed']) ?></td>
        </tr>
        <?php if (! empty(
            $run['error_message']
        )): ?>
            <tr>
                <th>Fatal Error</th>
                <td><?= $escape(
                    $run['error_message']
                ) ?></td>
            </tr>
        <?php endif; ?>
    </table>
</section>

<br>

<section class="panel">
    <h2>Row Errors</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Row</th>
                <th>Supplier SKU</th>
                <th>Error</th>
                <th>Message</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($errors as $error): ?>
                <tr>
                    <td>
                        <?= $escape(
                            $error['row_number']
                            ?? '—'
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $error['supplier_sku']
                            ?? '—'
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $error[
                                        'error_code'
                                    ]
                                )
                            )
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $error['message']
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($errors)): ?>
                <tr>
                    <td colspan="4">
                        No row-level errors were recorded.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
