<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\ReturnRepository;
use App\Repositories\ReturnShippingRepository;
use PDO;
use RuntimeException;

class ReturnShippingNotificationService
{
    public function __construct(
        private PDO $db,
        private ReturnRepository $returns,
        private ReturnShippingRepository $shipments
    ) {
    }

    public function queueForEvent(
        int $returnId,
        string $event
    ): ?int {
        $return = $this->returns->find($returnId);
        $shipment = $this->shipments->findByReturn(
            $returnId
        );

        if (! $return || ! $shipment) {
            throw new RuntimeException(
                'Return shipping notification data is unavailable.'
            );
        }

        $email = trim(
            (string) ($return['customer_email'] ?? '')
        );

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        $message = $this->messageForEvent(
            $event,
            $return
        );

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

        $carrierLink = ! empty(
            $shipment['tracking_url']
        )
            ? '<p><a href="'
                . htmlspecialchars(
                    (string) $shipment['tracking_url']
                )
                . '">Open Carrier Tracking</a></p>'
            : '';

        $bodyHtml = '
            <div style="font-family:Arial,sans-serif;line-height:1.6;color:#111827;max-width:680px;margin:0 auto;">
                <h1>'
                . htmlspecialchars($message['heading'])
                . '</h1>

                <p>'
                . htmlspecialchars($message['message'])
                . '</p>

                <div style="padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;">
                    <strong>Return:</strong> '
                    . htmlspecialchars(
                        (string) $return['return_number']
                    )
                    . '<br>
                    <strong>RMA:</strong> '
                    . htmlspecialchars(
                        (string) $return['rma_number']
                    )
                    . '<br>
                    <strong>Carrier:</strong> '
                    . htmlspecialchars(
                        (string) $shipment['carrier_name']
                    )
                    . '<br>
                    <strong>Service:</strong> '
                    . htmlspecialchars(
                        (string) (
                            $shipment['service_name']
                            ?? 'Not specified'
                        )
                    )
                    . '<br>
                    <strong>Tracking:</strong> '
                    . htmlspecialchars(
                        (string) $shipment['tracking_number']
                    )
                    . '<br>
                    <strong>Status:</strong> '
                    . htmlspecialchars(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string) $shipment['status']
                            )
                        )
                    )
                    . $carrierLink
                    . '
                </div>

                <p style="margin-top:24px;">
                    <a href="'
                    . htmlspecialchars($trackingUrl)
                    . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">
                        Track Return
                    </a>
                </p>

                <p style="color:#64748b;font-size:13px;">
                    The tracking page also requires the email address used for the order.
                </p>
            </div>
        ';

        $bodyText =
            $message['heading']
            . "\n\n"
            . $message['message']
            . "\n\nReturn: "
            . $return['return_number']
            . "\nRMA: "
            . $return['rma_number']
            . "\nCarrier: "
            . $shipment['carrier_name']
            . "\nService: "
            . ($shipment['service_name'] ?? 'Not specified')
            . "\nTracking: "
            . $shipment['tracking_number']
            . "\nStatus: "
            . ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) $shipment['status']
                )
            )
            . (
                ! empty($shipment['tracking_url'])
                    ? "\nCarrier Tracking: "
                        . $shipment['tracking_url']
                    : ''
            )
            . "\n\nTrack Return: "
            . $trackingUrl
            . "\n";

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
            'to_email' => $email,
            'to_name' =>
                $return['customer_name'] ?? null,
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
        $number = (string) $return['return_number'];

        return match ($event) {
            'shipment_label_ready' => [
                'subject' =>
                    'Return shipping label ready - '
                    . $number,
                'heading' =>
                    'Your return shipping label is ready',
                'message' =>
                    'A carrier and tracking number were assigned. Open return tracking to print the package identification label.',
            ],
            'shipment_in_transit' => [
                'subject' =>
                    'Return shipment in transit - '
                    . $number,
                'heading' =>
                    'Your return shipment is in transit',
                'message' =>
                    'The return shipment is moving toward the store.',
            ],
            'shipment_delivered' => [
                'subject' =>
                    'Return shipment delivered - '
                    . $number,
                'heading' =>
                    'Your return shipment was delivered',
                'message' =>
                    'The carrier reports delivery. Store receiving and inspection may still be pending.',
            ],
            'shipment_exception' => [
                'subject' =>
                    'Return shipment exception - '
                    . $number,
                'heading' =>
                    'Your return shipment needs attention',
                'message' =>
                    'The carrier reported an exception or delay.',
            ],
            'shipment_cancelled' => [
                'subject' =>
                    'Return shipment cancelled - '
                    . $number,
                'heading' =>
                    'The return shipment was cancelled',
                'message' =>
                    'The existing shipping record was cancelled. Contact the store before sending merchandise.',
            ],
            default => throw new RuntimeException(
                'Unsupported return shipping notification event.'
            ),
        };
    }
}
