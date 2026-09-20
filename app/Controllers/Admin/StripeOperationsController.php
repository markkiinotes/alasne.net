<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Auth\CsrfService;
use App\Services\Payments\Stripe\StripeOperationsService;
use RuntimeException;

class StripeOperationsController extends Controller
{
    public function __construct(
        private StripeOperationsService $operations,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.stripe-operations.index',
            [
                'title' => 'Stripe Operations',
                'dashboard' =>
                    $this->operations->dashboard(),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' =>
                    $this->flash(
                        'stripe_operations_success'
                    ),
                'error' =>
                    $this->flash(
                        'stripe_operations_error'
                    ),
                'reconciliation' =>
                    $this->flashArray(
                        'stripe_operations_reconciliation'
                    ),
            ],
            'admin'
        );
    }

    public function retryEvent()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                'Security token expired. Please try again.'
            );
        }

        try {
            $eventId = trim(
                (string) $this->request->input(
                    'event_id'
                )
            );

            if ($eventId === '') {
                throw new RuntimeException(
                    'Stripe event ID is required.'
                );
            }

            $result =
                $this->operations->retryEvent(
                    $eventId
                );

            $this->csrf->regenerate();

            $_SESSION[
                'stripe_operations_success'
            ] =
                ! empty($result['processed'])
                    ? 'Stripe event '
                        . $eventId
                        . ' was reprocessed successfully.'
                    : 'Stripe event '
                        . $eventId
                        . ' required no additional transition.';

            $this->response->redirect(
                '/admin/stripe-operations'
            );
        } catch (\Throwable $exception) {
            return $this->redirectWithError(
                $exception->getMessage()
                ?: 'Unable to retry Stripe event.'
            );
        }

        return null;
    }

    public function reconcile()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                'Security token expired. Please try again.'
            );
        }

        try {
            $limit = max(
                1,
                min(
                    500,
                    (int) $this->request->input(
                        'limit',
                        100
                    )
                )
            );

            $result =
                $this->operations->reconcile(
                    $limit
                );

            $_SESSION[
                'stripe_operations_reconciliation'
            ] = $result;

            $_SESSION[
                'stripe_operations_success'
            ] =
                'Stripe reconciliation scanned '
                . (int) $result['scanned']
                . ' transaction(s): '
                . (int) $result['transitioned']
                . ' transitioned, '
                . (int) $result['already_finalized']
                . ' already finalized, '
                . (int) $result['waiting']
                . ' still waiting, '
                . (int) $result['errors']
                . ' error(s).';

            $this->csrf->regenerate();

            $this->response->redirect(
                '/admin/stripe-operations'
            );
        } catch (\Throwable $exception) {
            return $this->redirectWithError(
                $exception->getMessage()
                ?: 'Unable to reconcile Stripe transactions.'
            );
        }

        return null;
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function redirectWithError(
        string $message
    ) {
        $_SESSION[
            'stripe_operations_error'
        ] = $message;

        $this->response->redirect(
            '/admin/stripe-operations'
        );

        return null;
    }

    private function flash(
        string $key
    ): ?string {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return is_string($value)
            ? $value
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function flashArray(
        string $key
    ): ?array {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return is_array($value)
            ? $value
            : null;
    }
}
