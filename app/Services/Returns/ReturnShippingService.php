<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\ReturnPolicyRepository;
use App\Repositories\ReturnRepository;
use App\Repositories\ReturnShippingRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class ReturnShippingService
{
    private const CARRIERS = [
        'usps' => 'USPS',
        'ups' => 'UPS',
        'fedex' => 'FedEx',
        'dhl' => 'DHL',
        'other' => 'Other',
    ];

    public function __construct(
        private PDO $db,
        private ReturnRepository $returns,
        private ReturnShippingRepository $shipments,
        private ReturnPolicyRepository $policies
    ) {
    }

    public function configure(
        int $returnId,
        array $data
    ): array {
        $return = $this->returns->find($returnId);

        if (! $return) {
            throw new RuntimeException('Return not found.');
        }

        if (empty($return['rma_number'])) {
            throw new RuntimeException(
                'Approve the return before creating its shipping label.'
            );
        }

        if (in_array(
            (string) $return['status'],
            ['requested', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'A shipping label cannot be created for this return status.'
            );
        }

        $carrierCode = strtolower(
            trim((string) ($data['carrier_code'] ?? ''))
        );

        if (! array_key_exists($carrierCode, self::CARRIERS)) {
            throw new RuntimeException('Select a valid carrier.');
        }

        $carrierName = $carrierCode === 'other'
            ? trim((string) ($data['carrier_name'] ?? ''))
            : self::CARRIERS[$carrierCode];

        if ($carrierName === '') {
            throw new RuntimeException(
                'Carrier name is required.'
            );
        }

        $trackingNumber = trim(
            (string) ($data['tracking_number'] ?? '')
        );

        if ($trackingNumber === '') {
            throw new RuntimeException(
                'Tracking number is required.'
            );
        }

        $trackingUrl = trim(
            (string) ($data['tracking_url'] ?? '')
        );

        if (
            $trackingUrl !== ''
            && ! filter_var(
                $trackingUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new RuntimeException(
                'Enter a valid tracking URL.'
            );
        }

        $currency = strtoupper(
            trim((string) ($data['currency'] ?? 'USD'))
        );

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException(
                'Currency must be a three-letter code.'
            );
        }

        $toAddress = trim(
            (string) (
                $return['return_address_snapshot'] ?? ''
            )
        );

        /*
         * Legacy RMAs may have been approved before the
         * store return address was configured. Preserve
         * immutable snapshots when present, but safely fill
         * a missing snapshot from the current store policy.
         */
        if ($toAddress === '') {
            $policy = $this->policies->forStore(
                (int) $return['store_id']
            );

            $addressParts = array_filter(
                [
                    trim(
                        (string) (
                            $policy[
                                'return_address_name'
                            ]
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $policy[
                                'return_address_text'
                            ]
                            ?? ''
                        )
                    ),
                ],
                static fn (string $value): bool =>
                    $value !== ''
            );

            $policyAddress = ! empty($addressParts)
                ? implode(
                    "\n",
                    $addressParts
                )
                : '';

            if ($policyAddress !== '') {
                $this->returns
                    ->fillMissingAuthorizationAddress(
                        $returnId,
                        $policyAddress
                    );

                $return = $this->returns->find(
                    $returnId
                );

                $toAddress = trim(
                    (string) (
                        $return[
                            'return_address_snapshot'
                        ]
                        ?? ''
                    )
                );
            }
        }

        if ($toAddress === '') {
            throw new RuntimeException(
                'The RMA still has no return address. Open Mission Control → Stores → Manage Return Policy, enter the return address, save it, and try again.'
            );
        }

        $existing = $this->shipments->findByReturn(
            $returnId
        );

        $status = $existing['status'] ?? 'label_ready';

        if ($status === 'cancelled') {
            $status = 'label_ready';
        }

        $this->db->beginTransaction();

        try {
            $shipmentId = $this->shipments->save(
                $returnId,
                [
                    'store_id' => (int) $return['store_id'],
                    'order_id' => (int) $return['order_id'],
                    'rma_number' => $return['rma_number'],
                    'status' => $status,
                    'carrier_code' => $carrierCode,
                    'carrier_name' => $carrierName,
                    'service_name' => trim(
                        (string) ($data['service_name'] ?? '')
                    ),
                    'tracking_number' => $trackingNumber,
                    'tracking_url' => $trackingUrl,
                    'label_cost' => max(
                        0,
                        (float) ($data['label_cost'] ?? 0)
                    ),
                    'currency' => $currency,
                    'from_name' =>
                        $return['customer_name'] ?? null,
                    'from_address_snapshot' =>
                        $this->customerAddress($return),
                    'to_name' => $return['store_name'] ?? null,
                    'to_address_snapshot' => $toAddress,
                    'public_note' => trim(
                        (string) ($data['public_note'] ?? '')
                    ),
                ]
            );

            $notificationEvent = null;

            if (! $existing || $existing['status'] === 'cancelled') {
                $eventAt = (new DateTimeImmutable('now'))
                    ->format('Y-m-d H:i:s');

                $this->shipments->recordEvent(
                    $shipmentId,
                    'label_ready',
                    'Return shipping label ready',
                    'A package identification label and tracking record were created.',
                    null,
                    $eventAt,
                    true
                );

                $this->returns->recordEvent(
                    $returnId,
                    'shipment_label_ready',
                    'Return shipping label ready',
                    $carrierName
                    . ' tracking '
                    . $trackingNumber
                    . ' was assigned.',
                    null,
                    'label_ready'
                );

                $this->returns->recordOrderEvent(
                    (int) $return['order_id'],
                    'shipment_label_ready',
                    'Return shipping label ready',
                    $return['rma_number']
                    . ' has '
                    . $carrierName
                    . ' tracking '
                    . $trackingNumber
                    . '.',
                    null,
                    'label_ready',
                    true
                );

                $notificationEvent =
                    'shipment_label_ready';
            }

            $this->db->commit();

            return [
                'shipment' =>
                    $this->shipments->findByReturn($returnId),
                'notification_event' =>
                    $notificationEvent,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function updateStatus(
        int $returnId,
        string $newStatus,
        ?string $description,
        ?string $location,
        ?string $eventAtValue
    ): array {
        $newStatus = strtolower(trim($newStatus));

        if (! in_array(
            $newStatus,
            [
                'label_ready',
                'in_transit',
                'delivered',
                'exception',
                'cancelled',
            ],
            true
        )) {
            throw new RuntimeException(
                'Select a valid shipment status.'
            );
        }

        try {
            $eventAt = $eventAtValue
                ? new DateTimeImmutable($eventAtValue)
                : new DateTimeImmutable('now');
        } catch (\Throwable) {
            throw new RuntimeException(
                'Enter a valid shipment event time.'
            );
        }

        $eventAtSql = $eventAt->format('Y-m-d H:i:s');

        $this->db->beginTransaction();

        try {
            $return = $this->returns->lock($returnId);
            $shipment = $this->shipments->lockByReturn(
                $returnId
            );

            if (! $return || ! $shipment) {
                throw new RuntimeException(
                    'Create the return shipping label before recording tracking events.'
                );
            }

            $oldStatus = (string) $shipment['status'];

            $this->validateTransition(
                $oldStatus,
                $newStatus
            );

            $title = $this->titleForStatus($newStatus);

            $this->shipments->updateStatus(
                (int) $shipment['id'],
                $newStatus,
                $eventAtSql
            );

            $this->shipments->recordEvent(
                (int) $shipment['id'],
                $newStatus,
                $title,
                $description,
                $location,
                $eventAtSql,
                true
            );

            $this->returns->recordEvent(
                $returnId,
                'shipment_' . $newStatus,
                $title,
                $description,
                $oldStatus,
                $newStatus
            );

            $this->returns->recordOrderEvent(
                (int) $return['order_id'],
                'shipment_' . $newStatus,
                $title,
                $return['rma_number']
                . ': '
                . ($description ?: $title),
                $oldStatus,
                $newStatus,
                true
            );

            $this->db->commit();

            return [
                'shipment' =>
                    $this->shipments->findByReturn($returnId),
                'notification_event' =>
                    'shipment_' . $newStatus,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function validateTransition(
        string $oldStatus,
        string $newStatus
    ): void {
        if ($oldStatus === $newStatus) {
            return;
        }

        $allowed = [
            'label_ready' => [
                'in_transit',
                'exception',
                'cancelled',
            ],
            'in_transit' => [
                'delivered',
                'exception',
                'cancelled',
            ],
            'exception' => [
                'in_transit',
                'delivered',
                'cancelled',
            ],
            'delivered' => [],
            'cancelled' => ['label_ready'],
        ];

        if (! in_array(
            $newStatus,
            $allowed[$oldStatus] ?? [],
            true
        )) {
            throw new RuntimeException(
                'Shipment cannot move from '
                . str_replace('_', ' ', $oldStatus)
                . ' to '
                . str_replace('_', ' ', $newStatus)
                . '.'
            );
        }
    }

    private function titleForStatus(
        string $status
    ): string {
        return match ($status) {
            'label_ready' =>
                'Return shipping label ready',
            'in_transit' =>
                'Return shipment in transit',
            'delivered' =>
                'Return shipment delivered',
            'exception' =>
                'Return shipment exception',
            'cancelled' =>
                'Return shipment cancelled',
            default => 'Return shipment updated',
        };
    }

    private function customerAddress(
        array $return
    ): ?string {
        $lines = [];

        foreach (
            [
                trim((string) (
                    $return['customer_address_line_1'] ?? ''
                )),
                trim((string) (
                    $return['customer_address_line_2'] ?? ''
                )),
                trim(
                    implode(
                        ', ',
                        array_filter(
                            [
                                trim((string) (
                                    $return['customer_city'] ?? ''
                                )),
                                trim(
                                    implode(
                                        ' ',
                                        array_filter(
                                            [
                                                trim((string) (
                                                    $return['customer_state'] ?? ''
                                                )),
                                                trim((string) (
                                                    $return['customer_postal_code'] ?? ''
                                                )),
                                            ],
                                            static fn (string $value): bool =>
                                                $value !== ''
                                        )
                                    )
                                ),
                            ],
                            static fn (string $value): bool =>
                                $value !== ''
                        )
                    ),
                    ', '
                ),
                trim((string) (
                    $return['customer_country'] ?? ''
                )),
            ]
            as $line
        ) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return ! empty($lines)
            ? implode("\n", $lines)
            : null;
    }
}
