<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Repositories\EmailOutboxRepository;
use PDO;

class OrderNotificationService
{
    public function __construct(
        private PDO $db,
        private EmailOutboxRepository $outbox
    ) {
    }

    public function queueStatusUpdate(
        int $orderId,
        string $status
    ): ?int {
        $order = $this->findOrder($orderId);

        if (! $order || empty($order['customer_email'])) {
            return null;
        }

        $notification = $this->messageForStatus(
            $order,
            $status
        );

        if ($notification === null) {
            return null;
        }

        return $this->outbox->create([
            'store_id' => (int) $order['store_id'],
            'order_id' => $orderId,
            'to_email' => $order['customer_email'],
            'to_name' => $order['customer_name'],
            'subject' => $notification['subject'],
            'body_html' => $notification['body_html'],
            'body_text' => $notification['body_text'],
        ]);
    }

    private function findOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.*,

                s.name AS store_name,
                s.slug AS store_slug,

                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email,

                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name
            FROM orders o
            INNER JOIN stores s
                ON s.id = o.store_id
            INNER JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function messageForStatus(
        array $order,
        string $status
    ): ?array {
        $status = strtolower(trim($status));

        $allowedStatuses = [
            'processing',
            'paid',
            'shipped',
            'completed',
            'cancelled',
            'refunded',
        ];

        if (! in_array(
            $status,
            $allowedStatuses,
            true
        )) {
            return null;
        }

        $orderNumber = (string) $order['order_number'];
        $storeName = (string) $order['store_name'];
        $storeSlug = (string) $order['store_slug'];

        $customerName = trim(
            (string) ($order['customer_name'] ?? '')
        );

        $trackingPage = app_url('/store/' . $storeSlug . '/track');

        $statusLabel = ucwords(
            str_replace('_', ' ', $status)
        );

        $headline = match ($status) {
            'processing' =>
                'Your order is being processed',

            'paid' =>
                'Payment confirmed',

            'shipped' =>
                'Your order has shipped',

            'completed' =>
                'Your order is complete',

            'cancelled' =>
                'Your order has been cancelled',

            'refunded' =>
                'Your order has been refunded',

            default =>
                'Your order was updated',
        };

        $message = match ($status) {
            'processing' =>
                'We are preparing your order for fulfillment.',

            'paid' =>
                'Your payment has been confirmed and your order is moving forward.',

            'shipped' =>
                'Your order is on the way.',

            'completed' =>
                'Your order has been marked complete. Thank you for shopping with us.',

            'cancelled' =>
                'Your order has been cancelled. Please contact us if you have any questions.',

            'refunded' =>
                'Your order has been marked as refunded.',

            default =>
                'There has been an update to your order.',
        };

        $subject = $headline
            . ' - '
            . $orderNumber;

        $trackingHtml = '';

        if (
            $status === 'shipped' &&
            ! empty($order['tracking_number'])
        ) {
            $trackingHtml .= '
                <p>
                    <strong>Tracking Number:</strong>
                    '
                . htmlspecialchars(
                    (string) $order['tracking_number']
                )
                . '
                </p>
            ';

            if (! empty($order['shipping_carrier'])) {
                $trackingHtml .= '
                    <p>
                        <strong>Carrier:</strong>
                        '
                    . htmlspecialchars(
                        (string) $order['shipping_carrier']
                    )
                    . '
                    </p>
                ';
            }

            if (! empty($order['tracking_url'])) {
                $safeTrackingUrl = htmlspecialchars(
                    (string) $order['tracking_url']
                );

                $trackingHtml .= '
                    <p>
                        <a href="'
                    . $safeTrackingUrl
                    . '">
                            Track Shipment
                        </a>
                    </p>
                ';
            }
        }

        $bodyHtml = '
            <div style="
                font-family:Arial,sans-serif;
                color:#111827;
                line-height:1.5;
            ">
                <h1>'
            . htmlspecialchars($headline)
            . '</h1>

                <p>
                    Hi '
            . htmlspecialchars(
                $customerName ?: 'there'
            )
            . ',
                </p>

                <p>'
            . htmlspecialchars($message)
            . '</p>

                <p>
                    <strong>Order Number:</strong>
                    '
            . htmlspecialchars($orderNumber)
            . '<br>

                    <strong>Status:</strong>
                    '
            . htmlspecialchars($statusLabel)
            . '
                </p>

                '
            . $trackingHtml
            . '

                <p>
                    You can view your latest order
                    information here:<br>

                    <a href="'
            . htmlspecialchars($trackingPage)
            . '">'
            . htmlspecialchars($trackingPage)
            . '</a>
                </p>

                <p>
                    Thank you,<br>
                    '
            . htmlspecialchars($storeName)
            . '
                </p>
            </div>
        ';

        $bodyText =
            $headline . "\n\n"
            . "Order Number: "
            . $orderNumber . "\n"
            . "Status: "
            . $statusLabel . "\n\n"
            . $message . "\n";

        if (
            $status === 'shipped' &&
            ! empty($order['tracking_number'])
        ) {
            $bodyText .= "\nTracking Number: "
                . $order['tracking_number']
                . "\n";

            if (! empty($order['shipping_carrier'])) {
                $bodyText .= "Carrier: "
                    . $order['shipping_carrier']
                    . "\n";
            }

            if (! empty($order['tracking_url'])) {
                $bodyText .= "Tracking Link: "
                    . $order['tracking_url']
                    . "\n";
            }
        }

        $bodyText .= "\nTrack your order: "
            . $trackingPage
            . "\n";

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }
	
	public function queueFulfillmentUpdate(
		int $orderId
	): ?int {
		$order = $this->findOrder($orderId);

		if (! $order || empty($order['customer_email'])) {
			return null;
		}

		$orderNumber = (string) $order['order_number'];
		$storeName = (string) $order['store_name'];
		$storeSlug = (string) $order['store_slug'];

		$customerName = trim(
			(string) ($order['customer_name'] ?? '')
		);

		$subject =
			'Shipping information updated - '
			. $orderNumber;

		$trackingPage = app_url('/store/' . $storeSlug	. '/track');

		$shippingDetailsHtml = '';

		if (! empty($order['shipping_carrier'])) {
			$shippingDetailsHtml .= '
				<p>
					<strong>Carrier:</strong>
					' . htmlspecialchars(
						(string) $order['shipping_carrier']
					) . '
				</p>
			';
		}

		if (! empty($order['tracking_number'])) {
			$shippingDetailsHtml .= '
				<p>
					<strong>Tracking Number:</strong>
					' . htmlspecialchars(
						(string) $order['tracking_number']
					) . '
				</p>
			';
		}

		if (! empty($order['shipped_at'])) {
			$shippingDetailsHtml .= '
				<p>
					<strong>Shipped At:</strong>
					' . htmlspecialchars(
						(string) $order['shipped_at']
					) . '
				</p>
			';
		}

		if (! empty($order['tracking_url'])) {
			$safeTrackingUrl = htmlspecialchars(
				(string) $order['tracking_url']
			);

			$shippingDetailsHtml .= '
				<p>
					<a href="' . $safeTrackingUrl . '">
						Track Shipment
					</a>
				</p>
			';
		}

		$bodyHtml = '
			<div style="
				font-family:Arial,sans-serif;
				color:#111827;
				line-height:1.5;
			">
				<h1>Shipping information updated</h1>

				<p>
					Hi '
			. htmlspecialchars(
				$customerName ?: 'there'
			)
			. ',
				</p>

				<p>
					Shipping information for your order
					has been updated.
				</p>

				<p>
					<strong>Order Number:</strong>
					'
			. htmlspecialchars($orderNumber)
			. '
				</p>

				'
			. $shippingDetailsHtml
			. '

				<p>
					View the latest order information here:
					<br>

					<a href="'
			. htmlspecialchars($trackingPage)
			. '">'
			. htmlspecialchars($trackingPage)
			. '</a>
				</p>

				<p>
					Thank you,<br>
					'
			. htmlspecialchars($storeName)
			. '
				</p>
			</div>
		';

		$bodyText =
			"Shipping information updated\n\n"
			. "Order Number: "
			. $orderNumber
			. "\n";

		if (! empty($order['shipping_carrier'])) {
			$bodyText .=
				"Carrier: "
				. $order['shipping_carrier']
				. "\n";
		}

		if (! empty($order['tracking_number'])) {
			$bodyText .=
				"Tracking Number: "
				. $order['tracking_number']
				. "\n";
		}

		if (! empty($order['shipped_at'])) {
			$bodyText .=
				"Shipped At: "
				. $order['shipped_at']
				. "\n";
		}

		if (! empty($order['tracking_url'])) {
			$bodyText .=
				"Tracking Link: "
				. $order['tracking_url']
				. "\n";
		}

		$bodyText .=
			"\nTrack your order: "
			. $trackingPage
			. "\n";

		return $this->outbox->create([
			'store_id' => (int) $order['store_id'],
			'order_id' => $orderId,
			'to_email' => $order['customer_email'],
			'to_name' => $customerName ?: null,
			'subject' => $subject,
			'body_html' => $bodyHtml,
			'body_text' => $bodyText,
		]);
	}
}