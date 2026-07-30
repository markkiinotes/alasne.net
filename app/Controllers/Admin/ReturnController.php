<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Mail\EmailOutboxSender;
use App\Services\Returns\ReturnNotificationService;
use App\Services\Returns\ReturnService;

class ReturnController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private ReturnService $returnService,
        private ReturnNotificationService $notifications,
        private EmailOutboxSender $emailSender,
        private StoreRepository $stores,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'q' => trim(
                (string) $this->request->input('q')
            ),
            'status' => trim(
                (string) $this->request->input(
                    'status'
                )
            ),
            'store_id' => (int)
                $this->request->input('store_id'),
        ];

        $success =
            $_SESSION['returns_success'] ?? null;
        $error =
            $_SESSION['returns_error'] ?? null;

        unset(
            $_SESSION['returns_success'],
            $_SESSION['returns_error']
        );

        return $this->view(
            'admin.returns.index',
            [
                'title' => 'Returns',
                'returns' =>
                    $this->returns->filtered(
                        $filters
                    ),
                'filters' => $filters,
                'stores' => $this->stores->all(),
                'statuses' => [
                    'requested',
                    'approved',
                    'received',
                    'completed',
                    'cancelled',
                ],
                'success' => $success,
                'error' => $error,
            ],
            'admin'
        );
    }

    public function create(Request $request)
    {
        $orderId = (int) $request->route(
            'order_id'
        );

        $order = $this->returns->orderForReturn(
            $orderId
        );

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $items =
            $this->returns->availableItemsForOrder(
                $orderId
            );

        $error =
            $_SESSION['returns_error'] ?? null;
        $old =
            $_SESSION['returns_old'] ?? [];

        unset(
            $_SESSION['returns_error'],
            $_SESSION['returns_old']
        );

        return $this->view(
            'admin.returns.create',
            [
                'title' =>
                    'Create Return | '
                    . $order['order_number'],
                'order' => $order,
                'items' => $items,
                'old' => $old,
                'csrf_token' =>
                    $this->csrf->token(),
                'error' => $error,
            ],
            'admin'
        );
    }

    public function store(Request $request)
    {
        $orderId = (int) $request->route(
            'order_id'
        );

        if (! $this->validateCsrf()) {
            $_SESSION['returns_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/orders/'
                . $orderId
                . '/returns/create'
            );

            return;
        }

        $data = [
            'reason_code' => trim(
                (string) $this->request->input(
                    'reason_code'
                )
            ),
            'reason_details' => trim(
                (string) $this->request->input(
                    'reason_details'
                )
            ),
            'customer_notes' => trim(
                (string) $this->request->input(
                    'customer_notes'
                )
            ),
            'internal_notes' => trim(
                (string) $this->request->input(
                    'internal_notes'
                )
            ),
        ];

        $quantities = $this->request->input(
            'quantities',
            []
        );

        if (! is_array($quantities)) {
            $quantities = [];
        }

        $_SESSION['returns_old'] = [
            ...$data,
            'quantities' => $quantities,
        ];

        try {
            $returnId = $this->returnService->create(
                $orderId,
                $data,
                $quantities
            );

            $this->csrf->regenerate();
            unset($_SESSION['returns_old']);

            $_SESSION['returns_success'] =
                'Return created successfully.';

            $this->queueNotification(
                $returnId,
                'requested'
            );

            $this->response->redirect(
                '/admin/returns/' . $returnId
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to create the return.';

            $this->response->redirect(
                '/admin/orders/'
                . $orderId
                . '/returns/create'
            );
        }
    }

    public function show(Request $request)
    {
        $returnId = (int) $request->route('id');
        $return = $this->returns->find(
            $returnId
        );

        if (! $return) {
            http_response_code(404);

            return '404 - Return not found';
        }

        $success =
            $_SESSION['returns_success'] ?? null;
        $error =
            $_SESSION['returns_error'] ?? null;

        unset(
            $_SESSION['returns_success'],
            $_SESSION['returns_error']
        );

        return $this->view(
            'admin.returns.show',
            [
                'title' =>
                    $return['return_number'],
                'return' => $return,
                'items' =>
                    $this->returns->items($returnId),
                'events' =>
                    $this->returns->events($returnId),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' => $success,
                'error' => $error,
            ],
            'admin'
        );
    }

    public function approve(Request $request)
    {
        $returnId = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            $this->redirectWithError(
                $returnId,
                'Security token expired. Please try again.'
            );

            return;
        }

        try {
            $this->returnService->approve(
                $returnId,
                trim(
                    (string) $this->request->input(
                        'notes'
                    )
                )
            );

            $this->csrf->regenerate();

            $_SESSION['returns_success'] =
                'Return approved successfully.';

            $this->queueNotification(
                $returnId,
                'approved'
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to approve the return.';
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }

    public function receive(Request $request)
    {
        $returnId = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            $this->redirectWithError(
                $returnId,
                'Security token expired. Please try again.'
            );

            return;
        }

        $items = $this->request->input(
            'items',
            []
        );

        if (! is_array($items)) {
            $items = [];
        }

        try {
            $this->returnService->receive(
                $returnId,
                $items,
                trim(
                    (string) $this->request->input(
                        'notes'
                    )
                )
            );

            $this->csrf->regenerate();

            $_SESSION['returns_success'] =
                'Returned merchandise received successfully.';

            $this->queueNotification(
                $returnId,
                'received'
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to receive the return.';
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }

    public function complete(Request $request)
    {
        $returnId = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            $this->redirectWithError(
                $returnId,
                'Security token expired. Please try again.'
            );

            return;
        }

        $processRefund =
            (string) $this->request->input(
                'process_refund'
            ) === '1';

        $refundAmount = round(
            (float) $this->request->input(
                'refund_amount',
                0
            ),
            2
        );

        $refundScenario = strtolower(
            trim(
                (string) $this->request->input(
                    'refund_scenario',
                    'approved'
                )
            )
        );

        if (! in_array(
            $refundScenario,
            ['approved', 'declined', 'error'],
            true
        )) {
            $this->redirectWithError(
                $returnId,
                'Select a valid refund test scenario.'
            );

            return;
        }

        try {
            $result = $this->returnService->complete(
                $returnId,
                $processRefund,
                $refundAmount,
                $refundScenario
            );

            $this->csrf->regenerate();

            $transaction =
                $result['refund_transaction']
                ?? null;

            if (
                is_array($transaction)
                && ($transaction['status'] ?? '')
                    !== 'succeeded'
            ) {
                $_SESSION['returns_error'] =
                    $transaction['failure_message']
                    ?? 'Return completed, but the refund failed.';

                $this->queueNotification(
                    $returnId,
                    'refund_failed'
                );
            } else {
                $_SESSION['returns_success'] =
                    $processRefund
                        ? 'Return completed and refund processed successfully.'
                        : 'Return completed without a refund.';

                $this->queueNotification(
                    $returnId,
                    'completed'
                );
            }
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to complete the return.';

            $updatedReturn = $this->returns->find(
                $returnId
            );

            if (
                $updatedReturn
                && ($updatedReturn['status'] ?? '')
                    === 'completed'
                && ($updatedReturn['refund_status'] ?? '')
                    === 'failed'
            ) {
                $this->queueNotification(
                    $returnId,
                    'refund_failed'
                );
            }
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }

    public function cancel(Request $request)
    {
        $returnId = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            $this->redirectWithError(
                $returnId,
                'Security token expired. Please try again.'
            );

            return;
        }

        try {
            $this->returnService->cancel(
                $returnId,
                trim(
                    (string) $this->request->input(
                        'notes'
                    )
                )
            );

            $this->csrf->regenerate();

            $_SESSION['returns_success'] =
                'Return cancelled successfully.';

            $this->queueNotification(
                $returnId,
                'cancelled'
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to cancel the return.';
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }


    private function queueNotification(
        int $returnId,
        string $event
    ): void {
        try {
            $outboxId =
                $this->notifications->queueForEvent(
                    $returnId,
                    $event
                );

            if ($outboxId !== null) {
                $this->emailSender->sendOne(
                    $outboxId
                );
            }
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                'The return was updated, but its customer notification could not be queued: '
                . $exception->getMessage();
        }
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
        int $returnId,
        string $message
    ): void {
        $_SESSION['returns_error'] = $message;

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }
}
