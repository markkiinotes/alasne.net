<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use App\Repositories\StripeWebhookEventRepository;
use RuntimeException;
use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookService
{
    private const SUPPORTED_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'charge.refunded',
    ];

    public function __construct(
        private StripeClientFactory $clients,
        private StripeWebhookEventRepository $events
    ) {
    }

    public function handle(
        string $payload,
        string $signature
    ): array {
        $secret = $this->clients->webhookSecret();

        if ($secret === '') {
            throw new RuntimeException(
                'Stripe webhook signing secret is not configured.'
            );
        }

        $event = Webhook::constructEvent(
            $payload,
            $signature,
            $secret
        );

        $eventData = $this->eventData($event);

        if (! $this->events->claim($eventData)) {
            return [
                'ok' => true,
                'duplicate' => true,
                'event_id' => $event->id,
                'event_type' => $event->type,
            ];
        }

        try {
            if (! in_array(
                $event->type,
                self::SUPPORTED_EVENTS,
                true
            )) {
                $this->events->markIgnored($event->id);

                return [
                    'ok' => true,
                    'ignored' => true,
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                ];
            }

            /*
             * Phase 6B.1 deliberately records verified Stripe events
             * without changing Alasne orders yet. Phase 6B.2 will attach
             * payment_intent events to the asynchronous order finalizer.
             */
            $this->events->markProcessed($event->id);

            return [
                'ok' => true,
                'processed' => true,
                'event_id' => $event->id,
                'event_type' => $event->type,
            ];
        } catch (\Throwable $exception) {
            $this->events->markFailed(
                $event->id,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function eventData(Event $event): array
    {
        $object = $event->data->object ?? null;
        $objectId = null;

        if (is_object($object) && isset($object->id)) {
            $objectId = trim((string) $object->id);
        } elseif (is_array($object) && isset($object['id'])) {
            $objectId = trim((string) $object['id']);
        }

        return [
            'event_id' => (string) $event->id,
            'event_type' => (string) $event->type,
            'stripe_object_id' =>
                $objectId !== '' ? $objectId : null,
            'stripe_account_id' =>
                ! empty($event->account)
                    ? (string) $event->account
                    : null,
            'livemode' => (bool) $event->livemode,
            'api_version' =>
                ! empty($event->api_version)
                    ? (string) $event->api_version
                    : null,
        ];
    }
}
