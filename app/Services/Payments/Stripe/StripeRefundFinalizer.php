<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use App\Repositories\PaymentTransactionRepository;
use App\Repositories\ReturnRepository;
use PDO;
use RuntimeException;
use Stripe\Refund;

class StripeRefundFinalizer
{
    public function __construct(
        private PDO $db,
        private PaymentTransactionRepository $paymentTransactions,
        private ReturnRepository $returns
    ) {
    }

    public function finalize(Refund $refund): array
    {
        $refundId = trim((string) $refund->id);

        if ($refundId === '') {
            throw new RuntimeException(
                'Stripe Refund ID is missing.'
            );
        }

        $status = strtolower(
            trim(
                (string) (
                    $refund->status
                    ?? ''
                )
            )
        );

        if ($status === '') {
            throw new RuntimeException(
                'Stripe Refund status is missing.'
            );
        }

        $transactionId = 0;
        $orderId = 0;
        $returnId = $this->metadataInt(
            $refund,
            'alasne_return_id'
        );
        $transitioned = false;

        $this->db->beginTransaction();

        try {
            $transaction = $this->refundTransaction(
                $refund,
                $refundId
            );

            if (! $transaction) {
                throw new RuntimeException(
                    'No Alasne refund transaction matches Stripe Refund '
                    . $refundId
                    . '.'
                );
            }

            $transactionId = (int) $transaction['id'];
            $orderId = (int) $transaction['order_id'];

            $this->assertRefundMatches(
                $refund,
                $transaction,
                $refundId
            );

            $parentId = (int) (
                $transaction[
                    'parent_transaction_id'
                ] ?? 0
            );

            $charge = $this->paymentTransactions
                ->findForUpdate($parentId);

            if (
                ! $charge
                || ($charge['type'] ?? '') !== 'charge'
                || ($charge['status'] ?? '') !== 'succeeded'
                || strtolower(
                    (string) (
                        $charge['provider']
                        ?? ''
                    )
                ) !== 'stripe'
            ) {
                throw new RuntimeException(
                    'Stripe refund parent charge is invalid.'
                );
            }

            $paymentIntentId = $this->objectId(
                $refund->payment_intent
                ?? null
            );

            if (
                $paymentIntentId !== null
                && $paymentIntentId !== trim(
                    (string) (
                        $charge[
                            'provider_transaction_id'
                        ] ?? ''
                    )
                )
            ) {
                throw new RuntimeException(
                    'Stripe Refund PaymentIntent does not match the Alasne charge.'
                );
            }

            $order = $this->lockOrder($orderId);

            if (! $order) {
                throw new RuntimeException(
                    'Stripe refund order could not be found.'
                );
            }

            $metadataOrderId = $this->metadataInt(
                $refund,
                'alasne_order_id'
            );

            if (
                $metadataOrderId > 0
                && $metadataOrderId !== $orderId
            ) {
                throw new RuntimeException(
                    'Stripe Refund metadata does not match the Alasne order.'
                );
            }

            if ($returnId > 0) {
                $this->assertReturnMatches(
                    $returnId,
                    $orderId,
                    $transactionId
                );
            }

            if ($status === 'succeeded') {
                if (
                    strtolower(
                        (string) (
                            $transaction['status']
                            ?? ''
                        )
                    ) !== 'succeeded'
                ) {
                    $amount = round(
                        (float) $transaction['amount'],
                        2
                    );

                    $this->paymentTransactions
                        ->markSucceeded(
                            $transactionId,
                            [
                                'provider_transaction_id' =>
                                    $refundId,
                                'response' =>
                                    $this->refundResponse(
                                        $refund
                                    ),
                            ]
                        );

                    $this->paymentTransactions
                        ->addRefundedAmount(
                            (int) $charge['id'],
                            $amount
                        );

                    $this->markOrderRefunded(
                        $order,
                        $transactionId,
                        $amount
                    );

                    if ($returnId > 0) {
                        $this->returns->attachRefundResult(
                            $returnId,
                            'succeeded',
                            $transactionId,
                            $amount,
                            'Stripe confirmed refund '
                            . $refundId
                            . '.'
                        );

                        $this->returns->markResolutionStatus(
                            $returnId,
                            'completed'
                        );

                        $this->returns->recordEvent(
                            $returnId,
                            'refund_succeeded',
                            'Refund completed',
                            '$'
                            . number_format(
                                $amount,
                                2
                            )
                            . ' was confirmed by Stripe.',
                            'pending',
                            'succeeded'
                        );
                    }

                    $transitioned = true;
                }
            } elseif (
                in_array(
                    $status,
                    ['failed', 'canceled'],
                    true
                )
            ) {
                $localStatus = strtolower(
                    (string) (
                        $transaction['status']
                        ?? ''
                    )
                );

                if ($localStatus !== 'failed') {
                    $failureMessage =
                        $this->failureMessage(
                            $refund,
                            $status
                        );

                    $amount = round(
                        (float) $transaction['amount'],
                        2
                    );

                    $wasSettled =
                        $localStatus === 'succeeded';

                    $this->paymentTransactions
                        ->markFailed(
                            $transactionId,
                            [
                                'provider_transaction_id' =>
                                    $refundId,
                                'response' =>
                                    $this->refundResponse(
                                        $refund
                                    ),
                                'failure_code' =>
                                    'stripe_refund_'
                                    . $status,
                                'failure_message' =>
                                    $failureMessage,
                            ]
                        );

                    if ($wasSettled) {
                        $this->paymentTransactions
                            ->subtractRefundedAmount(
                                (int) $charge['id'],
                                $amount
                            );

                        $this->rollbackOrderRefund(
                            $order,
                            (int) $charge['id'],
                            $transactionId,
                            $amount,
                            $failureMessage
                        );
                    } else {
                        $this->returns->recordOrderEvent(
                            $orderId,
                            'refund_failed',
                            'Refund failed',
                            $failureMessage,
                            null,
                            number_format(
                                $amount,
                                2,
                                '.',
                                ''
                            ),
                            false
                        );
                    }

                    if ($returnId > 0) {
                        if ($wasSettled) {
                            $this->returns
                                ->markRefundFailedAfterSettlement(
                                    $returnId,
                                    $transactionId,
                                    $failureMessage
                                );
                        } else {
                            $this->returns->attachRefundResult(
                                $returnId,
                                'failed',
                                $transactionId,
                                0,
                                $failureMessage
                            );

                            $this->returns
                                ->markResolutionStatus(
                                    $returnId,
                                    'partial_failed'
                                );
                        }

                        $this->returns->recordEvent(
                            $returnId,
                            'refund_failed',
                            'Refund failed',
                            $failureMessage,
                            $wasSettled
                                ? 'succeeded'
                                : 'pending',
                            'failed'
                        );
                    }

                    $transitioned = true;
                }
            } else {
                $this->paymentTransactions
                    ->attachProviderTransaction(
                        $transactionId,
                        $refundId,
                        $this->refundResponse(
                            $refund
                        )
                    );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'transitioned' => $transitioned,
            'state' => $status,
            'order_id' => $orderId,
            'return_id' =>
                $returnId > 0
                    ? $returnId
                    : null,
            'payment_transaction_id' =>
                $transactionId,
            'stripe_refund_id' =>
                $refundId,
        ];
    }

    private function refundTransaction(
        Refund $refund,
        string $refundId
    ): ?array {
        $metadataTransactionId =
            $this->metadataInt(
                $refund,
                'alasne_refund_transaction_id'
            );

        if ($metadataTransactionId > 0) {
            return $this->paymentTransactions
                ->findForUpdate(
                    $metadataTransactionId
                );
        }

        return $this->paymentTransactions
            ->findForUpdateByProviderTransactionId(
                'stripe',
                $refundId
            );
    }

    private function assertRefundMatches(
        Refund $refund,
        array $transaction,
        string $refundId
    ): void {
        if (
            ($transaction['type'] ?? '') !== 'refund'
            || strtolower(
                (string) (
                    $transaction['provider']
                    ?? ''
                )
            ) !== 'stripe'
        ) {
            throw new RuntimeException(
                'Stripe Refund does not match an Alasne Stripe refund transaction.'
            );
        }

        $providerId = trim(
            (string) (
                $transaction[
                    'provider_transaction_id'
                ] ?? ''
            )
        );

        if (
            $providerId !== ''
            && $providerId !== $refundId
        ) {
            throw new RuntimeException(
                'Stripe Refund ID does not match the Alasne refund transaction.'
            );
        }

        $expectedAmount = (int) round(
            (float) $transaction['amount']
            * 100
        );

        if (
            $expectedAmount <= 0
            || (int) $refund->amount
                !== $expectedAmount
        ) {
            throw new RuntimeException(
                'Stripe Refund amount does not match the Alasne refund transaction.'
            );
        }

        $expectedCurrency = strtolower(
            trim(
                (string) (
                    $transaction['currency']
                    ?? ''
                )
            )
        );

        $actualCurrency = strtolower(
            trim(
                (string) (
                    $refund->currency
                    ?? ''
                )
            )
        );

        if (
            $expectedCurrency === ''
            || $actualCurrency === ''
            || $expectedCurrency !== $actualCurrency
        ) {
            throw new RuntimeException(
                'Stripe Refund currency does not match the Alasne refund transaction.'
            );
        }
    }

    private function assertReturnMatches(
        int $returnId,
        int $orderId,
        int $transactionId
    ): void {
        $return = $this->returns->lock(
            $returnId
        );

        if (! $return) {
            throw new RuntimeException(
                'Stripe refund return could not be found.'
            );
        }

        if (
            (int) (
                $return['order_id']
                ?? 0
            ) !== $orderId
        ) {
            throw new RuntimeException(
                'Stripe refund return does not match the Alasne order.'
            );
        }

        $linkedId = (int) (
            $return[
                'refund_transaction_id'
            ] ?? 0
        );

        if (
            $linkedId > 0
            && $linkedId !== $transactionId
        ) {
            throw new RuntimeException(
                'Stripe refund return is linked to a different refund transaction.'
            );
        }
    }

    private function lockOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'id' => $orderId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function markOrderRefunded(
        array $order,
        int $transactionId,
        float $amount
    ): void {
        $amountPaid = round(
            (float) (
                $order['amount_paid']
                ?? 0
            ),
            2
        );

        $newRefundedAmount = min(
            $amountPaid,
            round(
                (float) (
                    $order['amount_refunded']
                    ?? 0
                )
                + $amount,
                2
            )
        );

        $paymentStatus =
            $newRefundedAmount >= $amountPaid
                ? 'refunded'
                : 'partially_refunded';

        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_status =
                    :payment_status,
                payment_transaction_id =
                    :payment_transaction_id,
                amount_refunded =
                    :amount_refunded,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $order['id'],
            'payment_status' => $paymentStatus,
            'payment_transaction_id' =>
                $transactionId,
            'amount_refunded' => number_format(
                $newRefundedAmount,
                2,
                '.',
                ''
            ),
        ]);

        $this->returns->recordOrderEvent(
            (int) $order['id'],
            'refund_succeeded',
            'Refund issued',
            '$'
            . number_format($amount, 2)
            . ' was refunded through Stripe.',
            (string) (
                $order['payment_status']
                ?? 'paid'
            ),
            $paymentStatus,
            true
        );
    }

    private function rollbackOrderRefund(
        array $order,
        int $chargeTransactionId,
        int $failedRefundTransactionId,
        float $amount,
        string $failureMessage
    ): void {
        $amountPaid = round(
            (float) (
                $order['amount_paid']
                ?? 0
            ),
            2
        );

        $currentRefundedAmount = round(
            (float) (
                $order['amount_refunded']
                ?? 0
            ),
            2
        );

        $newRefundedAmount = max(
            0,
            round(
                $currentRefundedAmount
                - $amount,
                2
            )
        );

        if ($newRefundedAmount <= 0) {
            $paymentStatus = 'paid';
        } elseif (
            $newRefundedAmount
            >= $amountPaid
        ) {
            $paymentStatus = 'refunded';
        } else {
            $paymentStatus =
                'partially_refunded';
        }

        $replacementTransactionId =
            $this->latestSuccessfulRefundTransactionId(
                (int) $order['id'],
                $failedRefundTransactionId
            )
            ?? $chargeTransactionId;

        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_status =
                    :payment_status,
                payment_transaction_id =
                    :payment_transaction_id,
                amount_refunded =
                    :amount_refunded,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $order['id'],
            'payment_status' =>
                $paymentStatus,
            'payment_transaction_id' =>
                $replacementTransactionId,
            'amount_refunded' =>
                number_format(
                    $newRefundedAmount,
                    2,
                    '.',
                    ''
                ),
        ]);

        $this->returns->recordOrderEvent(
            (int) $order['id'],
            'refund_failed',
            'Refund reversed after Stripe failure',
            $failureMessage
            . ' Alasne reversed the previously recorded $'
            . number_format($amount, 2)
            . ' refund.',
            (string) (
                $order['payment_status']
                ?? 'partially_refunded'
            ),
            $paymentStatus,
            true
        );
    }

    private function latestSuccessfulRefundTransactionId(
        int $orderId,
        int $excludeTransactionId
    ): ?int {
        $stmt = $this->db->prepare("
            SELECT id
            FROM payment_transactions
            WHERE order_id = :order_id
            AND type = 'refund'
            AND status = 'succeeded'
            AND id <> :exclude_transaction_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'exclude_transaction_id' =>
                $excludeTransactionId,
        ]);

        $transactionId = $stmt->fetchColumn();

        return $transactionId !== false
            ? (int) $transactionId
            : null;
    }

    private function refundResponse(
        Refund $refund
    ): array {
        return [
            'status' =>
                (string) (
                    $refund->status
                    ?? ''
                ),
            'amount' =>
                (int) (
                    $refund->amount
                    ?? 0
                ),
            'currency' =>
                (string) (
                    $refund->currency
                    ?? ''
                ),
            'payment_intent' =>
                $this->objectId(
                    $refund->payment_intent
                    ?? null
                ),
            'charge' =>
                $this->objectId(
                    $refund->charge
                    ?? null
                ),
            'failure_reason' =>
                isset($refund->failure_reason)
                    ? (string) $refund
                        ->failure_reason
                    : null,
        ];
    }

    private function failureMessage(
        Refund $refund,
        string $status
    ): string {
        $reason = trim(
            (string) (
                $refund->failure_reason
                ?? ''
            )
        );

        if ($reason !== '') {
            return 'Stripe refund '
                . $status
                . ': '
                . str_replace(
                    '_',
                    ' ',
                    $reason
                )
                . '.';
        }

        return $status === 'canceled'
            ? 'Stripe canceled the refund.'
            : 'Stripe reported that the refund failed.';
    }

    private function metadataInt(
        Refund $refund,
        string $key
    ): int {
        $metadata =
            $refund->metadata
            ?? null;

        if (
            is_object($metadata)
            || is_array($metadata)
        ) {
            return (int) (
                $metadata[$key]
                ?? 0
            );
        }

        return 0;
    }

    private function objectId(
        mixed $value
    ): ?string {
        if (is_string($value)) {
            $value = trim($value);

            return $value !== ''
                ? $value
                : null;
        }

        if (
            is_object($value)
            && isset($value->id)
        ) {
            $id = trim(
                (string) $value->id
            );

            return $id !== ''
                ? $id
                : null;
        }

        return null;
    }
}
