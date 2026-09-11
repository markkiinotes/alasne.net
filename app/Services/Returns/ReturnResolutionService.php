<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\ReturnExchangeRepository;
use App\Repositories\ReturnRepository;
use App\Repositories\StoreCreditRepository;
use App\Services\Payments\PaymentService;
use App\Services\Notifications\StoreCreditIssuedNotificationPublisher;
use PDO;
use RuntimeException;

class ReturnResolutionService
{
    public function __construct(
        private PDO $db,
        private ReturnRepository $returns,
        private ReturnExchangeRepository $exchanges,
        private StoreCreditRepository $storeCredits,
        private PaymentService $payments
    ) {
    }

    public function complete(
        int $returnId,
        float $cashRefundAmount,
        float $storeCreditAmount,
        array $exchangeSelections,
        string $refundScenario = 'approved',
        ?string $notes = null
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

        $cashRefundAmount = round(
            max(0, $cashRefundAmount),
            2
        );

        $storeCreditAmount = round(
            max(0, $storeCreditAmount),
            2
        );

        $approvedValue = round(
            (float) $return['approved_refund_amount'],
            2
        );

        $creditRestoreAmount = 0.0;
        $externalRefundAmount = $cashRefundAmount;
        $externalRefundToProcess = $cashRefundAmount;
        $creditRestoration = null;
        $reconciledRefundTransaction = null;
        $refundTransaction = null;

        $this->db->beginTransaction();

        try {
            $lockedReturn = $this->returns->lock(
                $returnId
            );

            if (
                ! $lockedReturn
                || $lockedReturn['status'] !== 'received'
            ) {
                throw new RuntimeException(
                    'Return status changed before completion.'
                );
            }

            $returnItems = $this->returns->lockItems(
                $returnId
            );

            $itemsById = [];

            foreach ($returnItems as $item) {
                $itemsById[(int) $item['id']] = $item;
            }

            $exchangeLines = [];
            $exchangeValue = 0.0;

            foreach (
                $exchangeSelections
                as $returnItemId => $selection
            ) {
                $returnItemId = (int) $returnItemId;

                if (! is_array($selection)) {
                    continue;
                }

                $productId = (int) (
                    $selection['product_id'] ?? 0
                );

                $quantity = max(
                    0,
                    (int) (
                        $selection['quantity'] ?? 0
                    )
                );

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $returnItem = $itemsById[
                    $returnItemId
                ] ?? null;

                if (! $returnItem) {
                    throw new RuntimeException(
                        'One or more exchange rows are invalid.'
                    );
                }

                if (
                    $quantity
                    > (int) $returnItem[
                        'quantity_received'
                    ]
                ) {
                    throw new RuntimeException(
                        'Exchange quantity for '
                        . $returnItem['product_name']
                        . ' cannot exceed the received quantity.'
                    );
                }

                $product = $this->exchanges
                    ->lockProduct(
                        (int) $return['store_id'],
                        $productId
                    );

                if (! $product) {
                    throw new RuntimeException(
                        'A selected replacement product is unavailable.'
                    );
                }

                if (
                    (int) $product[
                        'inventory_quantity'
                    ] < $quantity
                ) {
                    throw new RuntimeException(
                        'Not enough replacement inventory for '
                        . $product['name']
                        . '.'
                    );
                }

                $lineTotal = round(
                    (float) $product['price']
                    * $quantity,
                    2
                );

                $exchangeLines[] = [
                    'return_item_id' =>
                        $returnItemId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];

                $exchangeValue += $lineTotal;
            }

            $exchangeValue = round(
                $exchangeValue,
                2
            );

            $allocated = round(
                $cashRefundAmount
                + $storeCreditAmount
                + $exchangeValue,
                2
            );

            if (abs($allocated - $approvedValue) > 0.01) {
                throw new RuntimeException(
                    'Refund, store credit, and exchange value must total the approved merchandise value of $'
                    . number_format($approvedValue, 2)
                    . '. Current allocation is $'
                    . number_format($allocated, 2)
                    . '.'
                );
            }

            if ($allocated <= 0) {
                throw new RuntimeException(
                    'Select at least one completion resolution.'
                );
            }


            if ($cashRefundAmount > 0) {
                /*
                 * A payment refund may already have been issued from the
                 * order/payment screen before the return is completed.
                 * For a pure external-payment order, reconcile one exact,
                 * successful, unlinked refund instead of issuing a second
                 * refund. Mixed-tender returns continue through the normal
                 * tender-allocation path so redeemed store credit remains
                 * protected.
                 */
                $remainingAppliedCredit = round(
                    max(
                        0,
                        (float) (
                            $return['store_credit_applied_amount']
                            ?? 0
                        )
                        - (float) (
                            $return['store_credit_restored_amount']
                            ?? 0
                        )
                    ),
                    2
                );

                if ($remainingAppliedCredit <= 0.001) {
                    $matchingRefunds =
                        $this->returns
                            ->unlinkedSuccessfulRefundsForOrder(
                                (int) $return['order_id'],
                                $cashRefundAmount
                            );

                    if (count($matchingRefunds) > 1) {
                        throw new RuntimeException(
                            'Multiple unlinked successful payment refunds match this return amount. Reconcile the payment transaction manually before completing the return.'
                        );
                    }

                    if (count($matchingRefunds) === 1) {
                        $reconciledRefundTransaction =
                            $matchingRefunds[0];

                        $externalRefundAmount =
                            $cashRefundAmount;
                        $externalRefundToProcess = 0.0;
                    }
                }

                if (! is_array($reconciledRefundTransaction)) {
                    $refundAllocation =
                        $this->storeCredits
                            ->refundAllocationForOrder(
                                (int) $return['order_id'],
                                $cashRefundAmount
                            );

                    $creditRestoreAmount = round(
                        (float) $refundAllocation[
                            'credit_restore_amount'
                        ],
                        2
                    );

                    $externalRefundAmount = round(
                        (float) $refundAllocation[
                            'external_refund_amount'
                        ],
                        2
                    );

                    $externalRefundToProcess =
                        $externalRefundAmount;

                    if ($creditRestoreAmount > 0) {
                        $creditRestoration =
                            $this->storeCredits
                                ->restoreForReturnRefund(
                                    (int) $return['order_id'],
                                    $returnId,
                                    $creditRestoreAmount,
                                    'Restored redeemed store credit from return '
                                    . $return['return_number']
                                );
                    }
                }
            }

            $exchange = null;

            if (! empty($exchangeLines)) {
                $exchange =
                    $this->exchanges->createExchange(
                        [
                            ...$return,
                            'id' => $returnId,
                        ],
                        $exchangeLines,
                        $exchangeValue,
                        $notes
                    );
            }

            $credit = null;

            if ($storeCreditAmount > 0) {
                $credit = $this->storeCredits
                    ->creditForReturn(
                        (int) $return['store_id'],
                        (int) $return['customer_id'],
                        $returnId,
                        $storeCreditAmount,
                        (string) $return['currency'],
                        'Issued from return '
                        . $return['return_number']
                    );
            }

            $resolutionType =
                $this->resolutionType(
                    $cashRefundAmount,
                    $storeCreditAmount,
                    $exchangeValue
                );

            $resolutionStatus =
                $externalRefundToProcess > 0
                    ? 'pending_refund'
                    : 'completed';

            $exchangeOrderId = $exchange
                ? (int) $exchange[
                    'exchange_order_id'
                ]
                : null;

            $this->returns->markResolved(
                $returnId,
                $resolutionType,
                $resolutionStatus,
                $cashRefundAmount,
                $storeCreditAmount,
                $exchangeValue,
                $exchangeOrderId,
                $externalRefundToProcess > 0
                    ? 'pending'
                    : (
                        $cashRefundAmount > 0
                            ? 'succeeded'
                            : 'none'
                    ),
                $notes
            );

            $this->returns
                ->setTenderRefundAllocation(
                    $returnId,
                    $creditRestoreAmount,
                    $externalRefundAmount
                );

            $exchangeItemIds = array_column(
                $exchangeLines,
                'return_item_id'
            );

            foreach ($returnItems as $item) {
                $itemId = (int) $item['id'];

                $itemResolution = in_array(
                    $itemId,
                    $exchangeItemIds,
                    true
                )
                    ? (
                        $resolutionType === 'exchange'
                            ? 'exchange'
                            : 'mixed'
                    )
                    : $this->nonExchangeItemResolution(
                        $cashRefundAmount,
                        $storeCreditAmount
                    );

                $this->returns->updateItemResolution(
                    $itemId,
                    $itemResolution
                );
            }

            if ($exchange) {
                $this->returns->recordEvent(
                    $returnId,
                    'exchange_order_created',
                    'Exchange order created',
                    'Replacement order '
                    . $exchange[
                        'exchange_order_number'
                    ]
                    . ' was created for $'
                    . number_format($exchangeValue, 2)
                    . '.',
                    null,
                    $exchange[
                        'exchange_order_number'
                    ]
                );

                $this->returns->recordOrderEvent(
                    (int) $return['order_id'],
                    'exchange_order_created',
                    'Replacement order created',
                    $exchange[
                        'exchange_order_number'
                    ]
                    . ' was created from return '
                    . $return['return_number']
                    . '.',
                    null,
                    $exchange[
                        'exchange_order_number'
                    ],
                    true
                );
            }

            if ($credit) {
                $balance = (float) (
                    $credit['account']['balance']
                    ?? 0
                );

                $this->returns->recordEvent(
                    $returnId,
                    'store_credit_issued',
                    'Store credit issued',
                    '$'
                    . number_format(
                        $storeCreditAmount,
                        2
                    )
                    . ' in store credit was issued. New balance: $'
                    . number_format($balance, 2)
                    . '.',
                    null,
                    number_format(
                        $storeCreditAmount,
                        2,
                        '.',
                        ''
                    )
                );
            }


            if ($creditRestoration) {
                $balance = (float) (
                    $creditRestoration[
                        'account'
                    ]['balance'] ?? 0
                );

                $this->returns->recordEvent(
                    $returnId,
                    'redeemed_credit_restored',
                    'Redeemed store credit restored',
                    '$'
                    . number_format(
                        $creditRestoreAmount,
                        2
                    )
                    . ' was restored to the customer’s store credit balance. New balance: $'
                    . number_format($balance, 2)
                    . '.',
                    null,
                    number_format(
                        $creditRestoreAmount,
                        2,
                        '.',
                        ''
                    )
                );

                $this->returns->recordOrderEvent(
                    (int) $return['order_id'],
                    'store_credit_restored',
                    'Store credit restored',
                    '$'
                    . number_format(
                        $creditRestoreAmount,
                        2
                    )
                    . ' in redeemed store credit was restored from return '
                    . $return['return_number']
                    . '.',
                    null,
                    number_format(
                        $creditRestoreAmount,
                        2,
                        '.',
                        ''
                    ),
                    true
                );
            }

            if (is_array($reconciledRefundTransaction)) {
                $reconciledTransactionId = (int) (
                    $reconciledRefundTransaction['id']
                    ?? 0
                );

                $this->returns->attachRefundResult(
                    $returnId,
                    'succeeded',
                    $reconciledTransactionId > 0
                        ? $reconciledTransactionId
                        : null,
                    $externalRefundAmount,
                    'Existing successful payment refund transaction #'
                    . $reconciledTransactionId
                    . ' was reconciled to this return.'
                );

                $this->returns->recordEvent(
                    $returnId,
                    'refund_reconciled',
                    'Existing refund reconciled',
                    '$'
                    . number_format(
                        $externalRefundAmount,
                        2
                    )
                    . ' from successful payment refund transaction #'
                    . $reconciledTransactionId
                    . ' was linked to this return.',
                    'unlinked',
                    'succeeded'
                );

                $this->returns->recordOrderEvent(
                    (int) $return['order_id'],
                    'return_refund_reconciled',
                    'Return refund reconciled',
                    'Payment refund transaction #'
                    . $reconciledTransactionId
                    . ' was linked to return '
                    . $return['return_number']
                    . ' for $'
                    . number_format(
                        $externalRefundAmount,
                        2
                    )
                    . '.',
                    null,
                    number_format(
                        $externalRefundAmount,
                        2,
                        '.',
                        ''
                    ),
                    true
                );
            }

            $this->returns->recordEvent(
                $returnId,
                'resolution_completed',
                'Return resolution recorded',
                $this->resolutionDescription(
                    $cashRefundAmount,
                    $storeCreditAmount,
                    $exchangeValue
                ),
                'received',
                $resolutionType
            );

            $this->returns->recordOrderEvent(
                (int) $return['order_id'],
                'return_completed',
                'Return completed',
                $return['return_number']
                . ' was resolved as '
                . str_replace(
                    '_',
                    ' ',
                    $resolutionType
                )
                . '.',
                'received',
                'completed',
                true
            );

            $this->db->commit();

            /*
             * Store credit is now durable. Publish the customer
             * account notice only after the return-resolution
             * transaction commits.
             *
             * This runs before any separate external refund
             * attempt. A later card-refund failure must not hide
             * a store credit that was already successfully issued.
             */
            if (
                is_array($credit)
                && isset(
                    $credit['transaction']['id']
                )
                && (int) $credit[
                    'transaction'
                ]['id'] > 0
            ) {
                try {
                    $publisher =
                        new StoreCreditIssuedNotificationPublisher(
                            $this->db
                        );

                    $publisher->publish(
                        $returnId,
                        (int) $credit[
                            'transaction'
                        ]['id']
                    );
                } catch (\Throwable $notificationException) {
                    error_log(
                        '[Alasne store_credit.issued notification] '
                        . $notificationException->getMessage()
                    );
                }
            }
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        if (is_array($reconciledRefundTransaction)) {
            $refundTransaction =
                $reconciledRefundTransaction;
        }

        if ($externalRefundToProcess > 0) {
            try {
                $refundTransaction =
                    $this->payments->refundOrder(
                        (int) $return['order_id'],
                        $externalRefundToProcess,
                        [
                            'refund_scenario' =>
                                $refundScenario,
                            'return_id' => $returnId,
                            'return_number' =>
                                $return['return_number'],
                            'resolution_type' =>
                                $resolutionType,
                        ],
                        'return-resolution-refund-'
                        . $returnId
                    );

                $succeeded =
                    ($refundTransaction['status'] ?? '')
                    === 'succeeded';

                $this->returns->attachRefundResult(
                    $returnId,
                    $succeeded
                        ? 'succeeded'
                        : 'failed',
                    isset($refundTransaction['id'])
                        ? (int) $refundTransaction['id']
                        : null,
                    $succeeded
                        ? $externalRefundToProcess
                        : 0,
                    $succeeded
                        ? 'Payment refund completed.'
                        : (
                            $refundTransaction[
                                'failure_message'
                            ]
                            ?? 'Payment refund failed.'
                        )
                );

                $this->returns->markResolutionStatus(
                    $returnId,
                    $succeeded
                        ? 'completed'
                        : 'partial_failed'
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
                                $externalRefundToProcess,
                                2
                            )
                            . ' refunded to the original payment method.'
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

                $this->returns->markResolutionStatus(
                    $returnId,
                    'partial_failed'
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
                    'The exchange and store-credit portions were completed, but the payment refund failed: '
                    . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }


        if (
            $cashRefundAmount > 0
            && $externalRefundToProcess <= 0
            && $creditRestoreAmount > 0
            && ! is_array($reconciledRefundTransaction)
        ) {
            $this->returns->attachRefundResult(
                $returnId,
                'succeeded',
                null,
                0,
                'The original store-credit portion was restored.'
            );

            $this->returns->markResolutionStatus(
                $returnId,
                'completed'
            );
        }

        return [
            'return' => $this->returns->find(
                $returnId
            ),
            'exchange' =>
                $this->exchanges->findByReturn(
                    $returnId
                ),
            'credit' =>
                $this->storeCredits
                    ->transactionForReturn(
                        $returnId
                    ),
            'refund_transaction' =>
                $refundTransaction,
        ];
    }

    private function resolutionType(
        float $refund,
        float $credit,
        float $exchange
    ): string {
        $parts = array_filter(
            [
                'refund' => $refund,
                'store_credit' => $credit,
                'exchange' => $exchange,
            ],
            static fn (float $amount): bool =>
                $amount > 0
        );

        if (count($parts) > 1) {
            return 'mixed';
        }

        return (string) array_key_first($parts);
    }

    private function nonExchangeItemResolution(
        float $refund,
        float $credit
    ): string {
        if ($refund > 0 && $credit > 0) {
            return 'mixed';
        }

        if ($credit > 0) {
            return 'store_credit';
        }

        return 'refund';
    }

    private function resolutionDescription(
        float $refund,
        float $credit,
        float $exchange
    ): string {
        $parts = [];

        if ($refund > 0) {
            $parts[] = '$'
                . number_format($refund, 2)
                . ' original-payment refund';
        }

        if ($credit > 0) {
            $parts[] = '$'
                . number_format($credit, 2)
                . ' store credit';
        }

        if ($exchange > 0) {
            $parts[] = '$'
                . number_format($exchange, 2)
                . ' replacement merchandise';
        }

        return implode('; ', $parts) . '.';
    }
}
