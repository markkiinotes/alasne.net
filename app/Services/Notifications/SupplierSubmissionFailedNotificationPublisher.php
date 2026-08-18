<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use App\Services\Admin\MissionControlNotificationDispatchService;
use App\Services\Admin\MissionControlNotificationEventBridgeService;
use App\Services\Admin\MissionControlNotificationTemplateService;
use PDO;
use RuntimeException;

class SupplierSubmissionFailedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;

    public function __construct(
        private PDO $db,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->bridge =
            $bridge ?? $this->buildBridge();
    }

    /**
     * Publish an internal supplier-submission failure alert after
     * both the submission record and purchase-order mirror have
     * been updated.
     *
     * @return array<string, mixed>
     */
    public function publish(
        int $submissionId,
        string $previousStatus = ''
    ): array {
        if ($submissionId <= 0) {
            throw new RuntimeException(
                'A valid supplier submission ID is required.'
            );
        }

        $payload = $this->failurePayload(
            $submissionId,
            $previousStatus
        );

        $attempt = max(
            0,
            (int) (
                $payload['attempt_count']
                ?? 0
            )
        );

        return $this->bridge->handle(
            'supplier_submission.failed',
            $payload,
            [
                'event_source' =>
                    'supplier_submission_workflow',

                /*
                 * One supplier submission record may fail on more
                 * than one legitimate retry. Attempt-level
                 * idempotency alerts once per recorded attempt.
                 */
                'idempotency_key' =>
                    'supplier_submission.failed:submission_id:'
                    . $submissionId
                    . ':attempt:'
                    . $attempt,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function failurePayload(
        int $submissionId,
        string $previousStatus
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                sos.id AS supplier_submission_id,
                sos.purchase_order_id,
                sos.supplier_id,
                sos.supplier_integration_id,
                sos.provider_code,
                sos.channel,
                sos.status AS submission_status,
                sos.attempt_count,
                sos.external_order_id,
                sos.error_message,
                sos.updated_at AS failed_at,

                po.purchase_order_number,
                po.submission_status AS
                    purchase_order_submission_status,

                o.id AS order_id,
                o.order_number,

                sup.name AS supplier_name,
                sup.code AS supplier_code,

                s.id AS store_id,
                s.name AS store_name
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN suppliers sup
                ON sup.id = sos.supplier_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE sos.id = :submission_id
            LIMIT 1
        ");

        $stmt->execute([
            'submission_id' => $submissionId,
        ]);

        $submission = $stmt->fetch();

        if (! $submission) {
            throw new RuntimeException(
                'Unable to build supplier_submission.failed payload: submission not found.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $submission[
                            'submission_status'
                        ]
                        ?? ''
                    )
                )
            ) !== 'failed'
        ) {
            throw new RuntimeException(
                'supplier_submission.failed requires a failed submission record.'
            );
        }

        $errorMessage = $this->plainText(
            (string) (
                $submission['error_message']
                ?? ''
            ),
            1000
        );

        if ($errorMessage === '') {
            $errorMessage =
                'Supplier submission was marked failed without a recorded error message.';
        }

        $workflowPath =
            '/admin/supplier-submissions/'
            . $submissionId;

        /*
         * app_url() is the existing Alasne helper used by checkout
         * and return emails to create absolute links.
         */
        $workflowUrl = function_exists('app_url')
            ? app_url($workflowPath)
            : $workflowPath;

        return [
            'event_id' =>
                'supplier-submission-failed-'
                . $submissionId
                . '-attempt-'
                . max(
                    0,
                    (int) (
                        $submission[
                            'attempt_count'
                        ]
                        ?? 0
                    )
                ),

            'supplier_submission_id' =>
                $submissionId,

            'attempt_count' =>
                max(
                    0,
                    (int) (
                        $submission[
                            'attempt_count'
                        ]
                        ?? 0
                    )
                ),

            'submission_status' =>
                'failed',

            'previous_submission_status' =>
                $this->plainText(
                    $previousStatus,
                    80
                ),

            'provider_code' =>
                $this->plainText(
                    (string) (
                        $submission[
                            'provider_code'
                        ]
                        ?? ''
                    ),
                    100
                ),

            'channel' =>
                $this->plainText(
                    (string) (
                        $submission['channel']
                        ?? ''
                    ),
                    80
                ),

            'external_order_id' =>
                $this->plainText(
                    (string) (
                        $submission[
                            'external_order_id'
                        ]
                        ?? ''
                    ),
                    180
                ),

            'error_message' =>
                $errorMessage,

            'workflow_url' =>
                $workflowUrl,

            'purchase_order_id' =>
                (int) (
                    $submission[
                        'purchase_order_id'
                    ]
                    ?? 0
                ),

            'purchase_order_number' =>
                (string) (
                    $submission[
                        'purchase_order_number'
                    ]
                    ?? ''
                ),

            'purchase_order_submission_status' =>
                (string) (
                    $submission[
                        'purchase_order_submission_status'
                    ]
                    ?? ''
                ),

            'order_id' =>
                (int) (
                    $submission['order_id']
                    ?? 0
                ),

            'order_number' =>
                (string) (
                    $submission[
                        'order_number'
                    ]
                    ?? ''
                ),

            'supplier_id' =>
                (int) (
                    $submission[
                        'supplier_id'
                    ]
                    ?? 0
                ),

            'supplier_name' =>
                $this->plainText(
                    (string) (
                        $submission[
                            'supplier_name'
                        ]
                        ?? 'Supplier'
                    ),
                    180
                ),

            'supplier_code' =>
                $this->plainText(
                    (string) (
                        $submission[
                            'supplier_code'
                        ]
                        ?? ''
                    ),
                    100
                ),

            'store_id' =>
                (int) (
                    $submission['store_id']
                    ?? 0
                ),

            'store_name' =>
                $this->plainText(
                    (string) (
                        $submission[
                            'store_name'
                        ]
                        ?? 'Store'
                    ),
                    180
                ),

            'failed_at' =>
                $submission['failed_at']
                ?? null,
        ];
    }

    private function plainText(
        string $value,
        int $maxLength
    ): string {
        $value = strip_tags($value);

        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return mb_substr(
            trim($value),
            0,
            max(1, $maxLength)
        );
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
}
