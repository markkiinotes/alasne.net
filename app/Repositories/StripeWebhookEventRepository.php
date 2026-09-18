<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class StripeWebhookEventRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function claim(array $event): bool
    {
        $insert = $this->db->prepare("
            INSERT IGNORE INTO stripe_webhook_events (
                event_id,
                event_type,
                stripe_object_id,
                stripe_account_id,
                livemode,
                api_version,
                status,
                attempts,
                received_at,
                updated_at
            ) VALUES (
                :event_id,
                :event_type,
                :stripe_object_id,
                :stripe_account_id,
                :livemode,
                :api_version,
                'processing',
                1,
                NOW(),
                NOW()
            )
        ");

        $insert->execute([
            'event_id' => $event['event_id'],
            'event_type' => $event['event_type'],
            'stripe_object_id' =>
                $event['stripe_object_id'],
            'stripe_account_id' =>
                $event['stripe_account_id'],
            'livemode' =>
                $event['livemode'] ? 1 : 0,
            'api_version' =>
                $event['api_version'],
        ]);

        if ($insert->rowCount() === 1) {
            return true;
        }

        /*
         * Failed events can retry immediately.
         *
         * A processing event can also be reclaimed after five
         * minutes. This protects the webhook pipeline from a PHP
         * process dying after the business transaction committed
         * but before the event row was marked processed.
         *
         * Fresh processing rows remain duplicates so simultaneous
         * Stripe deliveries do not execute the finalizer twice.
         */
        $retry = $this->db->prepare("
            UPDATE stripe_webhook_events
            SET
                status = 'processing',
                attempts = attempts + 1,
                last_error = NULL,
                updated_at = NOW()
            WHERE event_id = :event_id
            AND (
                status = 'failed'
                OR (
                    status = 'processing'
                    AND updated_at
                        <= DATE_SUB(
                            NOW(),
                            INTERVAL 5 MINUTE
                        )
                )
            )
        ");

        $retry->execute([
            'event_id' => $event['event_id'],
        ]);

        return $retry->rowCount() === 1;
    }

    public function markProcessed(
        string $eventId
    ): void {
        $this->finish(
            $eventId,
            'processed',
            null
        );
    }

    public function markIgnored(
        string $eventId
    ): void {
        $this->finish(
            $eventId,
            'ignored',
            null
        );
    }

    public function markFailed(
        string $eventId,
        string $error
    ): void {
        $this->finish(
            $eventId,
            'failed',
            $error
        );
    }

    private function finish(
        string $eventId,
        string $status,
        ?string $error
    ): void {
        $stmt = $this->db->prepare("
            UPDATE stripe_webhook_events
            SET
                status = :status,
                last_error = :last_error,
                processed_at = CASE
                    WHEN :processed = 1
                    THEN NOW()
                    ELSE processed_at
                END,
                updated_at = NOW()
            WHERE event_id = :event_id
        ");

        $stmt->execute([
            'event_id' => $eventId,
            'status' => $status,
            'last_error' => $error,
            'processed' => in_array(
                $status,
                ['processed', 'ignored'],
                true
            ) ? 1 : 0,
        ]);
    }
}
