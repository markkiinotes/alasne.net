<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use App\Repositories\ReturnRepository;
use App\Services\Admin\MissionControlNotificationDispatchService;
use App\Services\Admin\MissionControlNotificationEventBridgeService;
use App\Services\Admin\MissionControlNotificationTemplateService;
use PDO;
use RuntimeException;

class ReturnRequestedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;
    private PDO $db;

    public function __construct(
        private ReturnRepository $returns,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->db = $this->extractDatabase($returns);
        $this->bridge = $bridge ?? $this->buildBridge();
    }

    /**
     * Publish a real customer self-service return.requested event.
     *
     * ReturnService::create() commits the return transaction before
     * CustomerReturnController calls this publisher.
     *
     * @return array<string, mixed>
     */
    public function publish(int $returnId): array
    {
        if ($returnId <= 0) {
            throw new RuntimeException(
                'A valid return ID is required.'
            );
        }

        $payload = $this->returnPayload($returnId);

        return $this->bridge->handle(
            'return.requested',
            $payload,
            [
                'event_source' =>
                    'customer_self_service',

                'idempotency_key' =>
                    'return.requested:return_id:'
                    . $returnId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function returnPayload(int $returnId): array
    {
        $return = $this->returns->find($returnId);

        if (! $return) {
            throw new RuntimeException(
                'Unable to build return.requested payload: return not found.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $return['request_source']
                        ?? ''
                    )
                )
            ) !== 'customer'
        ) {
            throw new RuntimeException(
                'return.requested customer receipt requires a customer-submitted return.'
            );
        }

        $returnNumber = trim(
            (string) (
                $return['return_number']
                ?? ''
            )
        );

        if ($returnNumber === '') {
            throw new RuntimeException(
                'return.requested requires a return number.'
            );
        }

        $customerEmail = trim(
            (string) (
                $return['customer_email']
                ?? ''
            )
        );

        if (
            $customerEmail === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Unable to publish return.requested: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $return['customer_name']
                ?? ''
            )
        );

        $reasonCode = strtolower(
            trim(
                (string) (
                    $return['reason_code']
                    ?? 'other'
                )
            )
        );

        /*
         * The seeded Return Request Received template expects
         * {{return_reason}}. Use a normalized system-owned label,
         * not raw customer-entered notes/details.
         */
        $returnReason = match ($reasonCode) {
            'damaged' =>
                'Damaged',

            'defective' =>
                'Defective',

            'wrong_item' =>
                'Wrong item',

            'not_as_described' =>
                'Not as described',

            'changed_mind' =>
                'Changed mind',

            default =>
                'Other',
        };

        return [
            'event_id' =>
                'return-requested-' . $returnId,

            'return_id' =>
                $returnId,

            'return_number' =>
                $returnNumber,

            'return_status' =>
                (string) (
                    $return['status']
                    ?? 'requested'
                ),

            'request_source' =>
                'customer',

            'return_reason_code' =>
                $reasonCode,

            'return_reason' =>
                $returnReason,

            'requested_refund_amount' =>
                number_format(
                    (float) (
                        $return[
                            'requested_refund_amount'
                        ]
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                (string) (
                    $return['currency']
                    ?? $return['order_currency']
                    ?? 'USD'
                ),

            'order_id' =>
                (int) (
                    $return['order_id']
                    ?? 0
                ),

            'order_number' =>
                (string) (
                    $return['order_number']
                    ?? ''
                ),

            'customer_id' =>
                (int) (
                    $return['customer_id']
                    ?? 0
                ),

            'customer_email' =>
                $customerEmail,

            'customer_name' =>
                $customerName !== ''
                    ? $customerName
                    : 'Customer',

            'store_id' =>
                (int) (
                    $return['store_id']
                    ?? 0
                ),

            'store_name' =>
                (string) (
                    $return['store_name']
                    ?? 'Store'
                ),

            'store_slug' =>
                (string) (
                    $return['store_slug']
                    ?? ''
                ),

            /*
             * Auto-approved customer returns may already have an
             * RMA number. It is exposed for audit/future rules,
             * but the Return Request Received template does not
             * require it.
             */
            'rma_number' =>
                (string) (
                    $return['rma_number']
                    ?? ''
                ),

            'created_at' =>
                $return['created_at']
                ?? null,
        ];
    }

    private function buildBridge(): MissionControlNotificationEventBridgeService
    {
        $templateRepository =
            new MissionControlNotificationTemplateRepository(
                $this->db
            );

        $templateService =
            new MissionControlNotificationTemplateService(
                $templateRepository
            );

        $dispatchRepository =
            new MissionControlNotificationDispatchRepository(
                $this->db
            );

        $dispatchService =
            new MissionControlNotificationDispatchService(
                $dispatchRepository,
                $templateRepository,
                $templateService
            );

        $bridgeRepository =
            new MissionControlNotificationEventBridgeRepository(
                $this->db
            );

        return new MissionControlNotificationEventBridgeService(
            $bridgeRepository,
            $dispatchService,
            $templateRepository,
            $templateService
        );
    }

    /**
     * ReturnRepository already owns the application's PDO instance.
     * This keeps the working CustomerReturnController constructor
     * unchanged and avoids adding a new DI dependency.
     */
    private function extractDatabase(
        ReturnRepository $repository
    ): PDO {
        try {
            $reflection =
                new \ReflectionClass($repository);

            foreach (
                ['db', 'pdo', 'connection']
                as $propertyName
            ) {
                if (! $reflection->hasProperty($propertyName)) {
                    continue;
                }

                $property =
                    $reflection->getProperty(
                        $propertyName
                    );

                $property->setAccessible(true);

                $value =
                    $property->getValue(
                        $repository
                    );

                if ($value instanceof PDO) {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Fall through to the explicit error below.
        }

        throw new RuntimeException(
            'Unable to resolve the database connection from ReturnRepository.'
        );
    }
}
