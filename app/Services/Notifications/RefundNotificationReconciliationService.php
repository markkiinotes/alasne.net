<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use PDO;

class RefundNotificationReconciliationService
{
    private RefundNotificationPublisher $publisher;

    public function __construct(
        private PDO $db,
        ?RefundNotificationPublisher $publisher = null
    ) {
        $this->publisher =
            $publisher
            ?? new RefundNotificationPublisher(
                $this->db
            );
    }

    /**
     * Re-publish finalized Stripe refund notifications that have
     * no Event Bridge run yet.
     *
     * Existing failed bridge runs are reported, not automatically
     * retried, because re-queueing after an uncertain dispatch
     * failure could duplicate customer email.
     *
     * @return array<string, mixed>
     */
    public function reconcile(
        int $limit = 250
    ): array {
        $limit = max(
            1,
            min($limit, 5000)
        );

        $stmt = $this->db->query("
            SELECT
                id,
                order_id,
                status,
                provider_transaction_id,
                processed_at
            FROM payment_transactions
            WHERE type = 'refund'
            AND provider = 'stripe'
            AND status IN (
                'succeeded',
                'failed'
            )
            AND provider_transaction_id IS NOT NULL
            AND provider_transaction_id <> ''
            ORDER BY
                processed_at ASC,
                id ASC
            LIMIT {$limit}
        ");

        $rows = $stmt->fetchAll();

        $summary = [
            'scanned' => count($rows),
            'published' => 0,
            'already_recorded' => 0,
            'failed_bridge_runs' => 0,
            'errors' => 0,
            'queued_dispatches' => 0,
            'dry_run_events' => 0,
            'results' => [],
        ];

        foreach ($rows as $row) {
            $transactionId =
                (int) $row['id'];

            $orderId =
                (int) $row['order_id'];

            $status = strtolower(
                trim(
                    (string) $row['status']
                )
            );

            $eventKey =
                $status === 'succeeded'
                    ? 'refund.succeeded'
                    : 'refund.failed';

            $idempotencyKey =
                $eventKey
                . ':payment_transaction_id:'
                . $transactionId;

            $run = $this->bridgeRun(
                'run:' . $idempotencyKey
            );

            if ($run) {
                if (
                    strtolower(
                        (string) (
                            $run['status']
                            ?? ''
                        )
                    ) === 'failed'
                ) {
                    $summary[
                        'failed_bridge_runs'
                    ]++;

                    $summary['results'][] = [
                        'refund_transaction_id' =>
                            $transactionId,
                        'order_id' => $orderId,
                        'event_key' => $eventKey,
                        'status' =>
                            'bridge_failed',
                        'bridge_run_id' =>
                            (int) $run['id'],
                        'message' =>
                            'Existing failed bridge run requires operator review; it was not auto-retried to avoid duplicate email risk.',
                    ];
                } else {
                    $summary[
                        'already_recorded'
                    ]++;

                    $summary['results'][] = [
                        'refund_transaction_id' =>
                            $transactionId,
                        'order_id' => $orderId,
                        'event_key' => $eventKey,
                        'status' =>
                            'already_recorded',
                        'bridge_run_id' =>
                            (int) $run['id'],
                    ];
                }

                continue;
            }

            try {
                $result =
                    $status === 'succeeded'
                        ? $this->publisher
                            ->publishSucceeded(
                                $orderId,
                                $transactionId
                            )
                        : $this->publisher
                            ->publishFailed(
                                $orderId,
                                $transactionId
                            );

                $summary['published']++;

                $summary[
                    'queued_dispatches'
                ] += (int) (
                    $result['stats'][
                        'queued_dispatches'
                    ] ?? 0
                );

                $summary[
                    'dry_run_events'
                ] += (int) (
                    $result['stats'][
                        'dry_run_events'
                    ] ?? 0
                );

                $summary['results'][] = [
                    'refund_transaction_id' =>
                        $transactionId,
                    'order_id' => $orderId,
                    'event_key' => $eventKey,
                    'status' =>
                        (string) (
                            $result['status']
                            ?? 'completed'
                        ),
                    'bridge_run_id' =>
                        (int) (
                            $result['run_id']
                            ?? 0
                        ),
                    'queued_dispatches' =>
                        (int) (
                            $result['stats'][
                                'queued_dispatches'
                            ] ?? 0
                        ),
                    'dry_run_events' =>
                        (int) (
                            $result['stats'][
                                'dry_run_events'
                            ] ?? 0
                        ),
                ];
            } catch (\Throwable $exception) {
                $summary['errors']++;

                $summary['results'][] = [
                    'refund_transaction_id' =>
                        $transactionId,
                    'order_id' => $orderId,
                    'event_key' => $eventKey,
                    'status' => 'error',
                    'message' =>
                        $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function bridgeRun(
        string $runKey
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                status,
                matched_rules,
                queued_dispatches,
                dry_run_events,
                skipped_rules,
                failed_rules,
                message
            FROM mission_control_notification_event_bridge_runs
            WHERE idempotency_key =
                :idempotency_key
            LIMIT 1
        ");

        $stmt->execute([
            'idempotency_key' =>
                $runKey,
        ]);

        $run = $stmt->fetch();

        return $run ?: null;
    }
}
