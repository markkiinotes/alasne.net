<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use App\Repositories\StripeWebhookEventRepository;
use PDO;
use RuntimeException;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;

class StripeOperationsService
{
    public function __construct(
        private PDO $db,
        private StripeClientFactory $clients,
        private StripeWebhookEventRepository $events,
        private StripeWebhookService $webhooks,
        private StripePaymentFinalizer $paymentFinalizer,
        private StripeRefundFinalizer $refundFinalizer
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $summary = $this->events->summary();

        $summary['stale_transactions'] =
            $this->staleTransactionCount();

        $summary['attention_total'] =
            (int) $summary['failed_events']
            + (int) $summary['stale_processing']
            + (int) $summary['stale_transactions'];

        return [
            'summary' => $summary,
            'mode' => $this->clients->mode(),
            'configured' =>
                $this->clients->isConfigured(),
            'publishable_key_configured' =>
                $this->clients
                    ->isPublishableKeyConfigured(),
            'webhook_secret_configured' =>
                $this->clients
                    ->isWebhookConfigured(),
            'attention_events' =>
                $this->events->attention(50),
            'recent_events' =>
                $this->events->recent(100),
            'stale_transactions' =>
                $this->staleTransactions(50),
        ];
    }

    /**
     * Retry one failed or stale-processing event by
     * retrieving the canonical Event from Stripe.
     *
     * @return array<string, mixed>
     */
    public function retryEvent(
        string $eventId
    ): array {
        $eventId = trim($eventId);

        if ($eventId === '') {
            throw new RuntimeException(
                'Stripe event ID is required.'
            );
        }

        $row = $this->events
            ->findByEventId($eventId);

        if (! $row) {
            throw new RuntimeException(
                'Stripe webhook event was not found in the Alasne ledger.'
            );
        }

        if (! $this->isRetryableEvent($row)) {
            throw new RuntimeException(
                'Only failed events or processing events stale for at least five minutes can be retried.'
            );
        }

        $event = $this->clients
            ->client()
            ->events
            ->retrieve(
                $eventId,
                []
            );

        if (! $event instanceof Event) {
            throw new RuntimeException(
                'Stripe did not return a valid Event object.'
            );
        }

        return $this->webhooks
            ->reprocess($event);
    }

    /**
     * Reconcile stale local pending Stripe transactions
     * against Stripe's current canonical state.
     *
     * @return array<string, mixed>
     */
    public function reconcile(
        int $limit = 100
    ): array {
        $limit = max(1, min(500, $limit));

        $rows = $this->staleTransactions(
            $limit
        );

        $summary = [
            'scanned' => count($rows),
            'transitioned' => 0,
            'already_finalized' => 0,
            'waiting' => 0,
            'errors' => 0,
            'results' => [],
        ];

        $client = $this->clients->client();

        foreach ($rows as $row) {
            $transactionId =
                (int) ($row['id'] ?? 0);

            $type = strtolower(
                trim(
                    (string) (
                        $row['type']
                        ?? ''
                    )
                )
            );

            $providerId = trim(
                (string) (
                    $row[
                        'provider_transaction_id'
                    ] ?? ''
                )
            );

            $result = [
                'payment_transaction_id' =>
                    $transactionId,
                'order_id' =>
                    (int) ($row['order_id'] ?? 0),
                'order_number' =>
                    $row['order_number'] ?? null,
                'type' => $type,
                'provider_transaction_id' =>
                    $providerId,
                'stripe_status' => null,
                'result' => null,
                'message' => null,
            ];

            try {
                if (
                    $transactionId <= 0
                    || $providerId === ''
                ) {
                    throw new RuntimeException(
                        'Pending Stripe transaction is missing its provider identifier.'
                    );
                }

                if ($type === 'charge') {
                    $paymentIntent =
                        $client->paymentIntents
                            ->retrieve(
                                $providerId,
                                []
                            );

                    if (
                        ! $paymentIntent
                            instanceof PaymentIntent
                    ) {
                        throw new RuntimeException(
                            'Stripe did not return a valid PaymentIntent.'
                        );
                    }

                    $stripeStatus = strtolower(
                        trim(
                            (string) (
                                $paymentIntent
                                    ->status
                                ?? ''
                            )
                        )
                    );

                    $result['stripe_status'] =
                        $stripeStatus;

                    if (
                        $stripeStatus ===
                        'succeeded'
                    ) {
                        $finalized =
                            $this->paymentFinalizer
                                ->succeeded(
                                    $paymentIntent
                                );

                        $transitioned =
                            ! empty(
                                $finalized[
                                    'transitioned'
                                ]
                            );

                        $result['result'] =
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized';

                        $summary[
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized'
                        ]++;
                    } elseif (
                        $stripeStatus ===
                        'canceled'
                    ) {
                        $finalized =
                            $this->paymentFinalizer
                                ->canceled(
                                    $paymentIntent
                                );

                        $transitioned =
                            ! empty(
                                $finalized[
                                    'transitioned'
                                ]
                            );

                        $result['result'] =
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized';

                        $summary[
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized'
                        ]++;
                    } elseif (
                        $stripeStatus ===
                            'requires_payment_method'
                        && ! empty(
                            $paymentIntent
                                ->last_payment_error
                        )
                    ) {
                        $finalized =
                            $this->paymentFinalizer
                                ->failed(
                                    $paymentIntent
                                );

                        $transitioned =
                            ! empty(
                                $finalized[
                                    'transitioned'
                                ]
                            );

                        $result['result'] =
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized';

                        $summary[
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized'
                        ]++;
                    } else {
                        $result['result'] =
                            'waiting';

                        $result['message'] =
                            'Stripe PaymentIntent is not in a finalizable state.';

                        $summary['waiting']++;
                    }
                } elseif ($type === 'refund') {
                    $refund =
                        $client->refunds
                            ->retrieve(
                                $providerId,
                                []
                            );

                    if (! $refund instanceof Refund) {
                        throw new RuntimeException(
                            'Stripe did not return a valid Refund.'
                        );
                    }

                    $stripeStatus = strtolower(
                        trim(
                            (string) (
                                $refund->status
                                ?? ''
                            )
                        )
                    );

                    $result['stripe_status'] =
                        $stripeStatus;

                    if (
                        in_array(
                            $stripeStatus,
                            [
                                'succeeded',
                                'failed',
                                'canceled',
                            ],
                            true
                        )
                    ) {
                        $finalized =
                            $this->refundFinalizer
                                ->finalize($refund);

                        $transitioned =
                            ! empty(
                                $finalized[
                                    'transitioned'
                                ]
                            );

                        $result['result'] =
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized';

                        $summary[
                            $transitioned
                                ? 'transitioned'
                                : 'already_finalized'
                        ]++;
                    } else {
                        $result['result'] =
                            'waiting';

                        $result['message'] =
                            'Stripe Refund is not in a terminal state.';

                        $summary['waiting']++;
                    }
                } else {
                    $result['result'] =
                        'waiting';

                    $result['message'] =
                        'Unsupported pending transaction type.';

                    $summary['waiting']++;
                }
            } catch (\Throwable $exception) {
                $result['result'] = 'error';
                $result['message'] =
                    $exception->getMessage()
                    ?: 'Stripe reconciliation failed.';

                $summary['errors']++;
            }

            $summary['results'][] = $result;
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staleTransactions(
        int $limit = 50
    ): array {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db->query("
            SELECT
                pt.id,
                pt.order_id,
                pt.type,
                pt.status,
                pt.provider,
                pt.provider_transaction_id,
                pt.currency,
                pt.amount,
                pt.created_at,
                pt.updated_at,
                o.order_number,
                o.status AS order_status,
                o.payment_status
            FROM payment_transactions pt
            INNER JOIN orders o
                ON o.id = pt.order_id
            WHERE pt.provider = 'stripe'
            AND pt.status = 'pending'
            AND pt.type IN ('charge', 'refund')
            AND pt.provider_transaction_id IS NOT NULL
            AND pt.provider_transaction_id <> ''
            AND pt.updated_at <= DATE_SUB(
                NOW(),
                INTERVAL 5 MINUTE
            )
            ORDER BY
                pt.updated_at ASC,
                pt.id ASC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll();
    }

    public function staleTransactionCount(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM payment_transactions
            WHERE provider = 'stripe'
            AND status = 'pending'
            AND type IN ('charge', 'refund')
            AND provider_transaction_id IS NOT NULL
            AND provider_transaction_id <> ''
            AND updated_at <= DATE_SUB(
                NOW(),
                INTERVAL 5 MINUTE
            )
        ");

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isRetryableEvent(
        array $row
    ): bool {
        $status = strtolower(
            trim(
                (string) (
                    $row['status']
                    ?? ''
                )
            )
        );

        if ($status === 'failed') {
            return true;
        }

        if ($status !== 'processing') {
            return false;
        }

        $updatedAt = strtotime(
            (string) (
                $row['updated_at']
                ?? ''
            )
        );

        return $updatedAt !== false
            && $updatedAt <= time() - 300;
    }
}
