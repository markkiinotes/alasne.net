<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\ReturnRepository;
use App\Services\Payments\PaymentService;
use PDO;
use RuntimeException;

class ReturnService
{
    public function __construct(
        private PDO $db,
        private ReturnRepository $returns,
        private PaymentService $payments
    ) {
    }

    public function create(
        int $orderId,
        array $data,
        array $quantities
    ): int {
        $order = $this->returns->orderForReturn(
            $orderId
        );

        if (! $order) {
            throw new RuntimeException(
                'Order not found.'
            );
        }

        if (
            (string) ($order['status'] ?? '')
            === 'cancelled'
        ) {
            throw new RuntimeException(
                'A cancelled order cannot receive a return.'
            );
        }

        if (
            (float) ($order['amount_paid'] ?? 0)
            <= 0
        ) {
            throw new RuntimeException(
                'Only paid orders can receive a return.'
            );
        }

        $availableItems =
            $this->returns->availableItemsForOrder(
                $orderId
            );

        $itemsById = [];

        foreach ($availableItems as $item) {
            $itemsById[(int) $item['id']] = $item;
        }

        $selectedItems = [];
        $requestedRefundAmount = 0.0;

        foreach ($quantities as $orderItemId => $quantity) {
            $orderItemId = (int) $orderItemId;
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $item = $itemsById[$orderItemId]
                ?? null;

            if (! $item) {
                throw new RuntimeException(
                    'One or more selected order items are invalid.'
                );
            }

            $available = (int) $item[
                'quantity_available_to_return'
            ];

            if ($quantity > $available) {
                throw new RuntimeException(
                    'Return quantity for '
                    . $item['product_name']
                    . ' exceeds the available quantity.'
                );
            }

            $lineRefund = round(
                (float) $item['unit_price']
                * $quantity,
                2
            );

            $selectedItems[] = [
                'item' => $item,
                'quantity' => $quantity,
                'line_refund' => $lineRefund,
            ];

            $requestedRefundAmount += $lineRefund;
        }

        if (empty($selectedItems)) {
            throw new RuntimeException(
                'Select at least one item to return.'
            );
        }

        $reasonCode = strtolower(
            trim((string) (
                $data['reason_code'] ?? 'other'
            ))
        );

        if (! in_array(
            $reasonCode,
            [
                'damaged',
                'defective',
                'wrong_item',
                'not_as_described',
                'changed_mind',
                'other',
            ],
            true
        )) {
            throw new RuntimeException(
                'Select a valid return reason.'
            );
        }

        $this->db->beginTransaction();

        try {
            $returnNumber = $this->generateReturnNumber();

            $returnId = $this->returns->createHeader([
                'return_number' => $returnNumber,
                'store_id' => (int) $order['store_id'],
                'order_id' => $orderId,
                'status' => 'requested',
                'reason_code' => $reasonCode,
                'reason_details' =>
                    $data['reason_details'] ?? null,
                'customer_notes' =>
                    $data['customer_notes'] ?? null,
                'internal_notes' =>
                    $data['internal_notes'] ?? null,
                'currency' =>
                    $order['currency'] ?? 'USD',
                'requested_refund_amount' =>
                    $requestedRefundAmount,
                'approved_refund_amount' => 0,
                'refunded_amount' => 0,
                'refund_status' => 'none',
            ]);

            foreach ($selectedItems as $selected) {
                $item = $selected['item'];

                $this->returns->createItem(
                    $returnId,
                    [
                        'order_item_id' =>
                            (int) $item['id'],
                        'product_id' =>
                            $item['product_id'] ?? null,
                        'product_name' =>
                            $item['product_name'],
                        'product_sku' =>
                            $item['product_sku'] ?? null,
                        'quantity_ordered' =>
                            (int) $item['quantity'],
                        'quantity_requested' =>
                            (int) $selected['quantity'],
                        'unit_price' =>
                            (float) $item['unit_price'],
                        'requested_refund_amount' =>
                            (float) $selected[
                                'line_refund'
                            ],
                        'reason_code' => $reasonCode,
                        'resolution_code' => 'refund',
                    ]
                );
            }

            $this->returns->recordEvent(
                $returnId,
                'return_requested',
                'Return requested',
                'The return was created for order '
                . $order['order_number']
                . '.',
                null,
                'requested'
            );

            $this->returns->recordOrderEvent(
                $orderId,
                'return_requested',
                'Return requested',
                $returnNumber
                . ' was created for $'
                . number_format(
                    $requestedRefundAmount,
                    2
                )
                . ' in merchandise.',
                null,
                $returnNumber,
                false
            );

            $this->db->commit();

            return $returnId;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function approve(
        int $returnId,
        ?string $notes = null
    ): void {
        $this->db->beginTransaction();

        try {
            $return = $this->returns->lock(
                $returnId
            );

            if (! $return) {
                throw new RuntimeException(
                    'Return not found.'
                );
            }

            if ($return['status'] !== 'requested') {
                throw new RuntimeException(
                    'Only requested returns can be approved.'
                );
            }

            $this->returns->approve(
                $returnId,
                $notes
            );

            $this->returns->recordEvent(
                $returnId,
                'return_approved',
                'Return approved',
                $notes,
                'requested',
                'approved'
            );

            $this->returns->recordOrderEvent(
                (int) $return['order_id'],
                'return_approved',
                'Return approved',
                $return['return_number']
                . ' was approved for receipt.',
                'requested',
                'approved',
                false
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function receive(
        int $returnId,
        array $itemData,
        ?string $notes = null
    ): void {
        $this->db->beginTransaction();

        try {
            $return = $this->returns->lock(
                $returnId
            );

            if (! $return) {
                throw new RuntimeException(
                    'Return not found.'
                );
            }

            if ($return['status'] !== 'approved') {
                throw new RuntimeException(
                    'Only approved returns can be received.'
                );
            }

            $items = $this->returns->lockItems(
                $returnId
            );

            if (empty($items)) {
                throw new RuntimeException(
                    'This return has no items.'
                );
            }

            $approvedRefundAmount = 0.0;
            $totalReceived = 0;
            $totalRestocked = 0;
            $totalDiscarded = 0;

            foreach ($items as $item) {
                $returnItemId = (int) $item['id'];
                $input = $itemData[$returnItemId]
                    ?? [];

                $received = max(
                    0,
                    (int) ($input['received'] ?? 0)
                );

                $restocked = max(
                    0,
                    (int) ($input['restocked'] ?? 0)
                );

                $discarded = max(
                    0,
                    (int) ($input['discarded'] ?? 0)
                );

                $requested = (int) $item[
                    'quantity_requested'
                ];

                if ($received > $requested) {
                    throw new RuntimeException(
                        'Received quantity for '
                        . $item['product_name']
                        . ' exceeds the approved quantity.'
                    );
                }

                if ($restocked + $discarded > $received) {
                    throw new RuntimeException(
                        'Restocked and discarded quantities for '
                        . $item['product_name']
                        . ' cannot exceed the received quantity.'
                    );
                }

                if ($restocked > 0) {
                    $productId = (int) (
                        $item['product_id'] ?? 0
                    );

                    if ($productId <= 0) {
                        throw new RuntimeException(
                            'The returned product cannot be restocked because it no longer exists.'
                        );
                    }

                    $balanceAfter =
                        $this->returns->restockProduct(
                            $productId,
                            $restocked
                        );

                    $this->returns
                        ->recordInventoryRestock(
                            $productId,
                            (int) $return['order_id'],
                            $returnId,
                            $restocked,
                            $balanceAfter,
                            'Restocked from return '
                            . $return['return_number']
                        );
                }

                $lineApprovedRefund = round(
                    (float) $item['unit_price']
                    * $received,
                    2
                );

                $this->returns->updateReceivedItem(
                    $returnItemId,
                    $received,
                    $restocked,
                    $discarded,
                    $lineApprovedRefund,
                    $input['condition_code'] ?? null,
                    $input['notes'] ?? null
                );

                $approvedRefundAmount +=
                    $lineApprovedRefund;
                $totalReceived += $received;
                $totalRestocked += $restocked;
                $totalDiscarded += $discarded;
            }

            if ($totalReceived <= 0) {
                throw new RuntimeException(
                    'Record at least one received item.'
                );
            }

            $this->returns->markReceived(
                $returnId,
                $approvedRefundAmount,
                $notes
            );

            $description =
                $totalReceived
                . ' item(s) received; '
                . $totalRestocked
                . ' restocked; '
                . $totalDiscarded
                . ' discarded.';

            $this->returns->recordEvent(
                $returnId,
                'return_received',
                'Return received',
                $description,
                'approved',
                'received'
            );

            $this->returns->recordOrderEvent(
                (int) $return['order_id'],
                'return_received',
                'Returned merchandise received',
                $return['return_number']
                . ': '
                . $description,
                'approved',
                'received',
                false
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function complete(
        int $returnId,
        bool $processRefund,
        float $refundAmount,
        string $refundScenario = 'approved'
    ): array {
        $return = $this->returns->find($returnId);

        if (! $return) {
            throw new RuntimeException(
                'Return not found.'
            );
        }

        if ($return['status'] !== 'received') {
            throw new RuntimeException(
                'Only received returns can be completed.'
            );
        }

        $maximumRefund = round(
            (float) $return[
                'approved_refund_amount'
            ],
            2
        );

        if ($processRefund) {
            $refundAmount = round(
                $refundAmount > 0
                    ? $refundAmount
                    : $maximumRefund,
                2
            );

            if ($refundAmount <= 0) {
                throw new RuntimeException(
                    'Refund amount must be greater than zero.'
                );
            }

            if ($refundAmount > $maximumRefund) {
                throw new RuntimeException(
                    'Refund amount cannot exceed the approved merchandise amount.'
                );
            }
        } else {
            $refundAmount = 0.0;
        }

        $this->db->beginTransaction();

        try {
            $locked = $this->returns->lock(
                $returnId
            );

            if (! $locked || $locked['status'] !== 'received') {
                throw new RuntimeException(
                    'Return status changed before completion.'
                );
            }

            $this->returns->markCompleted(
                $returnId,
                $processRefund ? 'pending' : 'none'
            );

            $this->returns->recordEvent(
                $returnId,
                'return_completed',
                'Return completed',
                $processRefund
                    ? 'Return completed; refund processing started.'
                    : 'Return completed without a payment refund.',
                'received',
                'completed'
            );

            $this->returns->recordOrderEvent(
                (int) $locked['order_id'],
                'return_completed',
                'Return completed',
                $locked['return_number']
                . ' was completed.',
                'received',
                'completed',
                false
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $refundTransaction = null;

        if ($processRefund) {
            try {
                $refundTransaction =
                    $this->payments->refundOrder(
                        (int) $return['order_id'],
                        $refundAmount,
                        [
                            'refund_scenario' =>
                                $refundScenario,
                            'return_id' => $returnId,
                            'return_number' =>
                                $return['return_number'],
                        ],
                        'return-refund-'
                        . $returnId
                    );

                $succeeded =
                    ($refundTransaction['status'] ?? '')
                    === 'succeeded';

                $this->returns->attachRefundResult(
                    $returnId,
                    $succeeded ? 'succeeded' : 'failed',
                    isset($refundTransaction['id'])
                        ? (int) $refundTransaction['id']
                        : null,
                    $succeeded ? $refundAmount : 0,
                    $succeeded
                        ? 'Payment refund completed.'
                        : (
                            $refundTransaction[
                                'failure_message'
                            ]
                            ?? 'Payment refund failed.'
                        )
                );

                $this->returns->recordEvent(
                    $returnId,
                    $succeeded
                        ? 'refund_succeeded'
                        : 'refund_failed',
                    $succeeded
                        ? 'Refund completed'
                        : 'Refund failed',
                    $succeeded
                        ? '$'
                            . number_format(
                                $refundAmount,
                                2
                            )
                            . ' refunded.'
                        : (
                            $refundTransaction[
                                'failure_message'
                            ]
                            ?? 'The refund was not approved.'
                        ),
                    'pending',
                    $succeeded
                        ? 'succeeded'
                        : 'failed'
                );
            } catch (\Throwable $exception) {
                $this->returns->attachRefundResult(
                    $returnId,
                    'failed',
                    null,
                    0,
                    $exception->getMessage()
                );

                $this->returns->recordEvent(
                    $returnId,
                    'refund_failed',
                    'Refund failed',
                    $exception->getMessage(),
                    'pending',
                    'failed'
                );

                throw new RuntimeException(
                    'The return was completed, but the refund failed: '
                    . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        return [
            'return' => $this->returns->find(
                $returnId
            ),
            'refund_transaction' =>
                $refundTransaction,
        ];
    }

    public function cancel(
        int $returnId,
        ?string $notes = null
    ): void {
        $this->db->beginTransaction();

        try {
            $return = $this->returns->lock(
                $returnId
            );

            if (! $return) {
                throw new RuntimeException(
                    'Return not found.'
                );
            }

            if (! in_array(
                $return['status'],
                ['requested', 'approved'],
                true
            )) {
                throw new RuntimeException(
                    'Only requested or approved returns can be cancelled.'
                );
            }

            $oldStatus = $return['status'];

            $this->returns->cancel(
                $returnId,
                $notes
            );

            $this->returns->recordEvent(
                $returnId,
                'return_cancelled',
                'Return cancelled',
                $notes,
                $oldStatus,
                'cancelled'
            );

            $this->returns->recordOrderEvent(
                (int) $return['order_id'],
                'return_cancelled',
                'Return cancelled',
                $return['return_number']
                . ' was cancelled.',
                $oldStatus,
                'cancelled',
                false
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function generateReturnNumber(): string
    {
        return 'RET-'
            . date('Ymd-His')
            . '-'
            . random_int(1000, 9999);
    }
}
