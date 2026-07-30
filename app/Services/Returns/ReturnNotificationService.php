<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\ReturnRepository;
use PDO;
use RuntimeException;

class ReturnNotificationService
{
    public function __construct(
        private PDO $db,
        private ReturnRepository $returns
    ) {
    }

    public function queueForEvent(
        int $returnId,
        string $event
    ): ?int {
        $return = $this->returns->find($returnId);

        if (! $return) {
            throw new RuntimeException(
                'Return notification could not find the return.'
            );
        }

        $customerEmail = trim(
            (string) (
                $return['customer_email'] ?? ''
            )
        );

        if (
            $customerEmail === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        $message = $this->messageForEvent(
            $event,
            $return
        );

        $items = $this->returns->items($returnId);

        $trackingUrl = app_url(
            '/store/'
            . rawurlencode(
                (string) $return['store_slug']
            )
            . '/returns/track?return_number='
            . rawurlencode(
                (string) $return['return_number']
            )
        );

        $bodyHtml = $this->buildHtml(
            $return,
            $items,
            $message,
            $trackingUrl
        );

        $bodyText = $this->buildText(
            $return,
            $items,
            $message,
            $trackingUrl
        );

        $stmt = $this->db->prepare("
            INSERT INTO email_outbox (
                store_id,
                order_id,
                to_email,
                to_name,
                subject,
                body_html,
                body_text,
                status,
                attempts,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :order_id,
                :to_email,
                :to_name,
                :subject,
                :body_html,
                :body_text,
                'pending',
                0,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int) $return['store_id'],
            'order_id' => (int) $return['order_id'],
            'to_email' => $customerEmail,
            'to_name' => trim(
                (string) (
                    $return['customer_name'] ?? ''
                )
            ) ?: null,
            'subject' => $message['subject'],
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function messageForEvent(
        string $event,
        array $return
    ): array {
        $returnNumber = (string)
            $return['return_number'];

        $approvedAmount = number_format(
            (float) (
                $return['approved_refund_amount']
                ?? 0
            ),
            2
        );

        return match ($event) {
            'requested' => [
                'subject' =>
                    'Return request received - '
                    . $returnNumber,
                'heading' => 'Return request received',
                'message' =>
                    'We received your return request and will review it.',
            ],

            'approved' => [
                'subject' =>
                    'Return approved - '
                    . $returnNumber,
                'heading' => 'Your return was approved',
                'message' =>
                    'Your return was approved. Send or bring back only the approved merchandise.',
            ],

            'received' => [
                'subject' =>
                    'Returned merchandise received - '
                    . $returnNumber,
                'heading' =>
                    'We received your returned merchandise',
                'message' =>
                    'The returned items were received and inspected. The approved merchandise value is $'
                    . $approvedAmount
                    . '.',
            ],

            'completed' => [
                'subject' =>
                    'Return completed - '
                    . $returnNumber,
                'heading' => 'Your return is complete',
                'message' => (
                    ($return['refund_status'] ?? 'none')
                    === 'succeeded'
                )
                    ? 'Your return is complete and the approved refund was processed.'
                    : 'Your physical return is complete. No successful payment refund is recorded for this return.',
            ],

            'cancelled' => [
                'subject' =>
                    'Return cancelled - '
                    . $returnNumber,
                'heading' => 'Your return was cancelled',
                'message' =>
                    'This return request is no longer active.',
            ],

            'refund_failed' => [
                'subject' =>
                    'Refund requires attention - '
                    . $returnNumber,
                'heading' =>
                    'Your return is complete, but the refund failed',
                'message' =>
                    'The merchandise return is complete, but the payment refund was not approved. The store will review the payment issue.',
            ],

            default => throw new RuntimeException(
                'Unsupported return notification event.'
            ),
        };
    }

    private function buildHtml(
        array $return,
        array $items,
        array $message,
        string $trackingUrl
    ): string {
        $itemRows = '';

        foreach ($items as $item) {
            $itemRows .= '
                <tr>
                    <td style="padding:10px;border-bottom:1px solid #e5e7eb;">
                        '
                        . htmlspecialchars(
                            (string) (
                                $item['product_name']
                                ?? 'Product'
                            )
                        )
                        . '
                    </td>
                    <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">
                        '
                        . (int) (
                            $item['quantity_requested']
                            ?? 0
                        )
                        . '
                    </td>
                    <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">
                        $'
                        . number_format(
                            (float) (
                                $item[
                                    'approved_refund_amount'
                                ]
                                ?? $item[
                                    'requested_refund_amount'
                                ]
                                ?? 0
                            ),
                            2
                        )
                        . '
                    </td>
                </tr>
            ';
        }

        $customerName = trim(
            (string) (
                $return['customer_name'] ?? ''
            )
        );

        return '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.6;max-width:680px;margin:0 auto;">
                <h1 style="margin-bottom:8px;">
                    '
                    . htmlspecialchars($message['heading'])
                    . '
                </h1>

                <p>
                    Hi '
                    . htmlspecialchars(
                        $customerName ?: 'there'
                    )
                    . ',
                </p>

                <p>
                    '
                    . htmlspecialchars($message['message'])
                    . '
                </p>

                <div style="padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:22px 0;">
                    <strong>Return:</strong>
                    '
                    . htmlspecialchars(
                        (string) $return['return_number']
                    )
                    . '
                    <br>

                    <strong>Order:</strong>
                    '
                    . htmlspecialchars(
                        (string) $return['order_number']
                    )
                    . '
                    <br>

                    <strong>Status:</strong>
                    '
                    . htmlspecialchars(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string) $return['status']
                            )
                        )
                    )
                    . '
                    <br>

                    <strong>Refund Status:</strong>
                    '
                    . htmlspecialchars(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string) (
                                    $return['refund_status']
                                    ?? 'none'
                                )
                            )
                        )
                    )
                    . '
                </div>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:10px;border-bottom:2px solid #111827;text-align:left;">
                                Item
                            </th>
                            <th style="padding:10px;border-bottom:2px solid #111827;text-align:right;">
                                Qty
                            </th>
                            <th style="padding:10px;border-bottom:2px solid #111827;text-align:right;">
                                Approved
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        '
                        . $itemRows
                        . '
                    </tbody>
                </table>

                <p style="margin-top:24px;">
                    <a
                        href="'
                        . htmlspecialchars($trackingUrl)
                        . '"
                        style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;"
                    >
                        Track Return
                    </a>
                </p>

                <p style="color:#64748b;font-size:13px;">
                    For privacy, the tracking page will also require the email address used for the order.
                </p>

                <p>
                    Thank you,<br>
                    '
                    . htmlspecialchars(
                        (string) $return['store_name']
                    )
                    . '
                </p>
            </div>
        ';
    }

    private function buildText(
        array $return,
        array $items,
        array $message,
        string $trackingUrl
    ): string {
        $lines = [];

        foreach ($items as $item) {
            $lines[] =
                '- '
                . (
                    $item['product_name']
                    ?? 'Product'
                )
                . ' | Qty '
                . (int) (
                    $item['quantity_requested']
                    ?? 0
                )
                . ' | Approved $'
                . number_format(
                    (float) (
                        $item['approved_refund_amount']
                        ?? $item[
                            'requested_refund_amount'
                        ]
                        ?? 0
                    ),
                    2
                );
        }

        return $message['heading']
            . "\n\n"
            . $message['message']
            . "\n\n"
            . 'Return: '
            . $return['return_number']
            . "\n"
            . 'Order: '
            . $return['order_number']
            . "\n"
            . 'Status: '
            . ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) $return['status']
                )
            )
            . "\n"
            . 'Refund Status: '
            . ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) (
                        $return['refund_status']
                        ?? 'none'
                    )
                )
            )
            . "\n\n"
            . "Items:\n"
            . implode("\n", $lines)
            . "\n\n"
            . 'Track your return: '
            . $trackingUrl
            . "\n\n"
            . 'The tracking page also requires the email address used for the order.'
            . "\n";
    }
}
