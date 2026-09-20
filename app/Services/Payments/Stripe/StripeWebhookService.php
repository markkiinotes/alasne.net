<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use App\Repositories\StripeWebhookEventRepository;
use RuntimeException;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Webhook;

class StripeWebhookService
{
    private const SUPPORTED_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'refund.created',
        'refund.updated',
        'refund.failed',
        'charge.refunded',
    ];

    public function __construct(
        private StripeClientFactory $clients,
        private StripeWebhookEventRepository $events,
        private StripePaymentFinalizer $finalizer,
        private StripeRefundFinalizer $refundFinalizer
    ) {
    }

    public function handle(
        string $payload,
        string $signature
    ): array {
        $secret =
            $this->clients->webhookSecret();

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

        return $this->processEvent($event);
    }

    /**
     * Reprocess an Event fetched directly from Stripe's
     * authenticated API. This intentionally bypasses signature
     * verification because the object did not arrive over the
     * public webhook endpoint; all normal environment checks,
     * claim/retry rules, finalizers, and idempotency protections
     * still apply.
     *
     * @return array<string, mixed>
     */
    public function reprocess(Event $event): array
    {
        return $this->processEvent($event);
    }

    /**
     * @return array<string, mixed>
     */
    private function processEvent(
        Event $event
    ): array {
        $this->assertEnvironmentMatches(
            $event
        );

        $eventData =
            $this->eventData($event);

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
                $this->events->markIgnored(
                    $event->id
                );

                return [
                    'ok' => true,
                    'ignored' => true,
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                ];
            }

            $result = $this->processSupportedEvent(
                $event
            );

            $this->events->markProcessed(
                $event->id
            );

            return [
                'ok' => true,
                'processed' => true,
                'event_id' => $event->id,
                'event_type' => $event->type,
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            $this->events->markFailed(
                $event->id,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function processSupportedEvent(
        Event $event
    ): array {
        return match ($event->type) {
            'payment_intent.succeeded' =>
                $this->finalizer->succeeded(
                    $this->paymentIntent($event)
                ),

            'payment_intent.payment_failed' =>
                $this->finalizer->failed(
                    $this->paymentIntent($event)
                ),

            'payment_intent.canceled' =>
                $this->finalizer->canceled(
                    $this->paymentIntent($event)
                ),

            'refund.created',
            'refund.updated',
            'refund.failed' =>
                $this->refundFinalizer->finalize(
                    $this->refund($event)
                ),

            /*
             * charge.refunded is an aggregate Charge event and is
             * retained for audit visibility. Refund object events
             * own Alasne's refund state transition.
             */
            'charge.refunded' => [
                'recorded' => true,
                'refund_finalizer' => 'refund_object_events',
            ],

            default => [
                'recorded' => true,
            ],
        };
    }

    private function paymentIntent(
        Event $event
    ): PaymentIntent {
        $object = $event->data->object
            ?? null;

        if (! $object instanceof PaymentIntent) {
            throw new RuntimeException(
                'Stripe webhook does not contain a PaymentIntent.'
            );
        }

        return $object;
    }

    private function refund(
        Event $event
    ): Refund {
        $object = $event->data->object
            ?? null;

        if (! $object instanceof Refund) {
            throw new RuntimeException(
                'Stripe webhook does not contain a Refund.'
            );
        }

        return $object;
    }

    private function assertEnvironmentMatches(
        Event $event
    ): void {
        $mode = $this->clients->mode();

        if (
            $mode === 'test'
            && (bool) $event->livemode
        ) {
            throw new RuntimeException(
                'Live Stripe webhook rejected by test-mode Alasne configuration.'
            );
        }

        if (
            $mode === 'live'
            && ! (bool) $event->livemode
        ) {
            throw new RuntimeException(
                'Test Stripe webhook rejected by live-mode Alasne configuration.'
            );
        }
    }

    private function eventData(
        Event $event
    ): array {
        $object = $event->data->object
            ?? null;

        $objectId = null;

        if (
            is_object($object)
            && isset($object->id)
        ) {
            $objectId = trim(
                (string) $object->id
            );
        } elseif (
            is_array($object)
            && isset($object['id'])
        ) {
            $objectId = trim(
                (string) $object['id']
            );
        }

        return [
            'event_id' =>
                (string) $event->id,
            'event_type' =>
                (string) $event->type,
            'stripe_object_id' =>
                $objectId !== ''
                    ? $objectId
                    : null,
            'stripe_account_id' =>
                ! empty($event->account)
                    ? (string) $event->account
                    : null,
            'livemode' =>
                (bool) $event->livemode,
            'api_version' =>
                ! empty($event->api_version)
                    ? (string) $event
                        ->api_version
                    : null,
        ];
    }
}
