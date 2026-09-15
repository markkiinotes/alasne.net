<?php

declare(strict_types=1);

use App\Controllers\StripeWebhookController;

$router = $app->router;

/*
 * Stripe webhooks are authenticated with Stripe's signing secret.
 * Do not put session auth or CSRF middleware on this endpoint.
 */
$router->post(
    '/webhooks/stripe',
    [StripeWebhookController::class, 'receive']
);
