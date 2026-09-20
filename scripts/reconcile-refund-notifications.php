<?php

declare(strict_types=1);

use App\Services\Notifications\RefundNotificationReconciliationService;

if (PHP_SAPI !== 'cli') {
    fwrite(
        STDERR,
        "This script may only be executed from the command line.\n"
    );
    exit(1);
}

$root = dirname(__DIR__);

$app = require $root
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'app.php';

try {
    $service = $app->container->make(
        RefundNotificationReconciliationService::class
    );

    $result = $service->reconcile();

    echo "Refund notification reconciliation\n";
    echo "Scanned: "
        . (int) $result['scanned']
        . "\n";
    echo "Published: "
        . (int) $result['published']
        . "\n";
    echo "Already recorded: "
        . (int) $result['already_recorded']
        . "\n";
    echo "Queued dispatches: "
        . (int) $result['queued_dispatches']
        . "\n";
    echo "Dry-run events: "
        . (int) $result['dry_run_events']
        . "\n";
    echo "Failed bridge runs: "
        . (int) $result['failed_bridge_runs']
        . "\n";
    echo "Errors: "
        . (int) $result['errors']
        . "\n";

    foreach ($result['results'] as $row) {
        echo "- Refund transaction #"
            . (int) $row['refund_transaction_id']
            . " / order #"
            . (int) $row['order_id']
            . " / "
            . (string) $row['event_key']
            . " => "
            . (string) $row['status'];

        if (! empty($row['message'])) {
            echo " ("
                . (string) $row['message']
                . ")";
        }

        echo "\n";
    }

    exit(
        (int) $result['errors'] > 0
        || (int) $result['failed_bridge_runs'] > 0
            ? 2
            : 0
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Refund notification reconciliation failed:\n"
        . $exception->getMessage()
        . "\n"
    );

    exit(1);
}
