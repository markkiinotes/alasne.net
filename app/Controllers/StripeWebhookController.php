<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Payments\Stripe\StripeWebhookService;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeWebhookService $webhooks
    ) {
        parent::__construct();
    }

    public function receive(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $payload = file_get_contents('php://input');
        $signature = trim(
            (string) (
                $_SERVER['HTTP_STRIPE_SIGNATURE']
                ?? ''
            )
        );

        if ($payload === false || $signature === '') {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Invalid Stripe webhook request.',
            ]);
            exit;
        }

        try {
            $result = $this->webhooks->handle(
                $payload,
                $signature
            );

            http_response_code(200);
            echo json_encode($result);
            exit;
        } catch (
            SignatureVerificationException
            | UnexpectedValueException $exception
        ) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Stripe webhook signature verification failed.',
            ]);
            exit;
        } catch (\Throwable $exception) {
            error_log(
                '[Alasne Stripe webhook] '
                . $exception->getMessage()
            );

            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Stripe webhook processing failed.',
            ]);
            exit;
        }
    }
}
