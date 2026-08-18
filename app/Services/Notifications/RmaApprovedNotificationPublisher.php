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

class RmaApprovedNotificationPublisher
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
     * Publish an RMA approval after the return approval transaction
     * has committed.
     *
     * @return array<string, mixed>
     */
    public function publish(
        int $returnId,
        string $eventSource = 'returns'
    ): array {
        if ($returnId <= 0) {
            throw new RuntimeException(
                'A valid return ID is required.'
            );
        }

        $eventSource = trim($eventSource);

        if (! in_array(
            $eventSource,
            [
                'customer_policy_auto_approval',
                'mission_control_returns',
                'returns',
            ],
            true
        )) {
            $eventSource = 'returns';
        }

        $payload = $this->rmaPayload($returnId);

        return $this->bridge->handle(
            'rma.approved',
            $payload,
            [
                'event_source' => $eventSource,

                'idempotency_key' =>
                    'rma.approved:return_id:'
                    . $returnId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rmaPayload(int $returnId): array
    {
        $return = $this->returns->find($returnId);

        if (! $return) {
            throw new RuntimeException(
                'Unable to build rma.approved payload: return not found.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $return['status']
                        ?? ''
                    )
                )
            ) !== 'approved'
        ) {
            throw new RuntimeException(
                'rma.approved requires an approved return.'
            );
        }

        $rmaNumber = trim(
            (string) (
                $return['rma_number']
                ?? ''
            )
        );

        if ($rmaNumber === '') {
            throw new RuntimeException(
                'rma.approved requires an issued RMA number.'
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
                'Unable to publish rma.approved: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $return['customer_name']
                ?? ''
            )
        );

        /*
         * Return instructions are store/policy-owned content. Strip
         * markup before placing them into the notification payload so
         * the seeded text/HTML templates receive plain instructions,
         * not executable or template-owned HTML.
         */
        $returnInstructions = trim(
            strip_tags(
                (string) (
                    $return[
                        'return_instructions_snapshot'
                    ]
                    ?? ''
                )
            )
        );

        if ($returnInstructions === '') {
            $returnInstructions =
                'Package the approved merchandise securely, include your RMA number, and follow the return shipping instructions provided by the store.';
        }

        $returnAddress = trim(
            strip_tags(
                (string) (
                    $return[
                        'return_address_snapshot'
                    ]
                    ?? ''
                )
            )
        );

        $shippingResponsibility = trim(
            (string) (
                $return[
                    'return_shipping_responsibility_snapshot'
                ]
                ?? ''
            )
        );

        return [
            'event_id' =>
                'rma-approved-' . $returnId,

            'return_id' =>
                $returnId,

            'return_number' =>
                (string) (
                    $return['return_number']
                    ?? ''
                ),

            'return_status' =>
                'approved',

            'request_source' =>
                (string) (
                    $return['request_source']
                    ?? ''
                ),

            'rma_number' =>
                $rmaNumber,

            'return_instructions' =>
                $returnInstructions,

            'authorization_issued_at' =>
                $return['authorization_issued_at']
                ?? null,

            'authorization_expires_at' =>
                $return['authorization_expires_at']
                ?? null,

            'return_address' =>
                $returnAddress,

            'return_shipping_responsibility' =>
                $shippingResponsibility,

            'approved_refund_amount' =>
                number_format(
                    (float) (
                        $return[
                            'approved_refund_amount'
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

            'approved_at' =>
                $return['approved_at']
                ?? null,

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
     * This keeps both working return-controller constructors unchanged.
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
            // Fall through to explicit error below.
        }

        throw new RuntimeException(
            'Unable to resolve the database connection from ReturnRepository.'
        );
    }
}
