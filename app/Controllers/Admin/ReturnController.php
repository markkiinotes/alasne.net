<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CarrierIntegrationRepository;
use App\Repositories\ReturnRepository;
use App\Repositories\ReturnExchangeRepository;
use App\Repositories\StoreCreditRepository;
use App\Repositories\ReturnShippingQuoteRepository;
use App\Repositories\ReturnShippingRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Mail\EmailOutboxSender;
use App\Services\Returns\ReturnCarrierService;
use App\Services\Returns\ReturnNotificationService;
use App\Services\Returns\ReturnResolutionService;
use App\Services\Returns\ReturnService;
use App\Services\Returns\ReturnShippingNotificationService;
use App\Services\Returns\ReturnShippingService;

class ReturnController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private ReturnCarrierService $returnCarrierService,
        private ReturnShippingQuoteRepository $carrierQuotes,
        private CarrierIntegrationRepository $carrierIntegrations,
        private ReturnService $returnService,
        private ReturnResolutionService $returnResolutionService,
        private ReturnExchangeRepository $returnExchanges,
        private StoreCreditRepository $storeCredits,
        private ReturnShippingService $returnShippingService,
        private ReturnShippingRepository $returnShipments,
        private ReturnShippingNotificationService $shippingNotifications,
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
            'request_source' => trim(
                (string) $this->request->input(
                    'request_source'
                )
            ),
            'shipment_status' => trim(
                (string) $this->request->input(
                    'shipment_status'
                )
            ),
            'resolution_type' => trim(
                (string) $this->request->input(
                    'resolution_type'
                )
            ),
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
                'sources' => [
                    'admin',
                    'customer',
                ],
                'shipmentStatuses' => [
                    'label_ready',
                    'in_transit',
                    'delivered',
                    'exception',
                    'cancelled',
                ],
                'resolutionTypes' => [
                    'refund',
                    'store_credit',
                    'exchange',
                    'mixed',
                    'none',
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
                'shipment' =>
                    $this->returnShipments
                        ->findByReturn($returnId),
                'carrierIntegration' => $this->carrierIntegrations->forStore((int) $return['store_id']),
                'carrierQuotes' => $this->carrierQuotes->availableForReturn($returnId),
                'replacementProducts' =>
                    $this->returnExchanges
                        ->productsForStore(
                            (int) $return['store_id']
                        ),
                'exchange' =>
                    $this->returnExchanges
                        ->findByReturn($returnId),
                'exchangeItems' =>
                    $this->returnExchanges
                        ->itemsForReturn($returnId),
                'storeCreditAccount' =>
                    $this->storeCredits
                        ->accountForCustomer(
                            (int) $return['store_id'],
                            (int) $return['customer_id'],
                            (string) $return['currency']
                        ),
                'storeCreditTransaction' =>
                    $this->storeCredits
                        ->transactionForReturn(
                            $returnId
                        ),
                'shipmentEvents' => (
                    $shipment = $this->returnShipments
                        ->findByReturn($returnId)
                )
                    ? $this->returnShipments->events(
                        (int) $shipment['id']
                    )
                    : [],
                'csrf_token' =>
                    $this->csrf->token(),
                'success' => $success,
                'error' => $error,
            ],
            'admin'
        );
    }




    public function requestCarrierRates(Request $request)
    {
        $returnId=(int)$request->route('id');
        if(!$this->validateCsrf()){ $this->redirectWithError($returnId,'Security token expired. Please try again.'); return; }
        try{$rates=$this->returnCarrierService->quote($returnId,['length'=>$this->request->input('length'),'width'=>$this->request->input('width'),'height'=>$this->request->input('height'),'weight_oz'=>$this->request->input('weight_oz')]);
            $this->csrf->regenerate();$_SESSION['returns_success']=count($rates).' live carrier rates retrieved.';}
        catch(\Throwable $e){$_SESSION['returns_error']=$e->getMessage()?:'Unable to retrieve live carrier rates.';}
        $this->response->redirect('/admin/returns/'.$returnId);
    }

    public function purchaseCarrierRate(Request $request)
    {
        $returnId=(int)$request->route('id');
        if(!$this->validateCsrf()){ $this->redirectWithError($returnId,'Security token expired. Please try again.'); return; }
        try{$this->returnCarrierService->purchase($returnId,(int)$this->request->input('quote_id'));$this->csrf->regenerate();$_SESSION['returns_success']='Live carrier postage and label purchased successfully.';$this->queueShippingNotification($returnId,'shipment_label_ready');}
        catch(\Throwable $e){$_SESSION['returns_error']=$e->getMessage()?:'Unable to purchase the selected carrier rate.';}
        $this->response->redirect('/admin/returns/'.$returnId);
    }

    public function saveShipping(Request $request)
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
            $result = $this->returnShippingService
                ->configure(
                    $returnId,
                    [
                        'carrier_code' =>
                            $this->request->input(
                                'carrier_code'
                            ),
                        'carrier_name' =>
                            $this->request->input(
                                'carrier_name'
                            ),
                        'service_name' =>
                            $this->request->input(
                                'service_name'
                            ),
                        'tracking_number' =>
                            $this->request->input(
                                'tracking_number'
                            ),
                        'tracking_url' =>
                            $this->request->input(
                                'tracking_url'
                            ),
                        'label_cost' =>
                            $this->request->input(
                                'label_cost',
                                0
                            ),
                        'currency' =>
                            $this->request->input(
                                'currency',
                                'USD'
                            ),
                        'public_note' =>
                            $this->request->input(
                                'public_note'
                            ),
                    ]
                );

            $this->csrf->regenerate();

            $_SESSION['returns_success'] =
                'Return shipping details saved.';

            $this->queueShippingNotification(
                $returnId,
                $result['notification_event']
                    ?? null
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to save return shipping details.';
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }

    public function updateShippingStatus(
        Request $request
    ) {
        $returnId = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            $this->redirectWithError(
                $returnId,
                'Security token expired. Please try again.'
            );

            return;
        }

        try {
            $result = $this->returnShippingService
                ->updateStatus(
                    $returnId,
                    (string) $this->request->input(
                        'status'
                    ),
                    trim(
                        (string) $this->request->input(
                            'description'
                        )
                    ) ?: null,
                    trim(
                        (string) $this->request->input(
                            'location'
                        )
                    ) ?: null,
                    trim(
                        (string) $this->request->input(
                            'event_at'
                        )
                    ) ?: null
                );

            $this->csrf->regenerate();

            $_SESSION['returns_success'] =
                'Return shipment status updated.';

            $this->queueShippingNotification(
                $returnId,
                $result['notification_event']
                    ?? null
            );
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to update return shipment status.';
        }

        $this->response->redirect(
            '/admin/returns/' . $returnId
        );
    }

    public function shippingLabel(Request $request)
    {
        $returnId = (int) $request->route('id');
        $return = $this->returns->find($returnId);
        $shipment = $this->returnShipments
            ->findByReturn($returnId);

        if (! $return || ! $shipment) {
            http_response_code(404);

            return '404 - Return shipping label not found';
        }

        $providerLabel = $shipment['provider_label_pdf_url']
            ?? $shipment['provider_label_url']
            ?? null;

        if (! empty($providerLabel)) {
            $this->response->redirect((string) $providerLabel);
            return;
        }

        return $this->view(
            'admin.returns.shipping-label',
            [
                'title' =>
                    'Return Shipping Label '
                    . $return['rma_number'],
                'return' => $return,
                'shipment' => $shipment,
            ]
        );
    }

    public function authorization(Request $request)
    {
        $returnId = (int) $request->route('id');

        $return = $this->returns->find(
            $returnId
        );

        if (! $return) {
            http_response_code(404);

            return '404 - Return not found';
        }

        if (empty($return['rma_number'])) {
            $_SESSION['returns_error'] =
                'Approve the return before printing its authorization.';

            $this->response->redirect(
                '/admin/returns/' . $returnId
            );

            return;
        }

        return $this->view(
            'admin.returns.authorization',
            [
                'title' =>
                    'Return Authorization '
                    . $return['rma_number'],
                'return' => $return,
                'items' =>
                    $this->returns->items($returnId),
            ]
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

        $cashRefundAmount = round(
            (float) $this->request->input(
                'cash_refund_amount',
                0
            ),
            2
        );

        $storeCreditAmount = round(
            (float) $this->request->input(
                'store_credit_amount',
                0
            ),
            2
        );

        $exchangeSelections =
            $this->request->input(
                'exchange_items',
                []
            );

        if (! is_array($exchangeSelections)) {
            $exchangeSelections = [];
        }

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
            $result =
                $this->returnResolutionService
                    ->complete(
                        $returnId,
                        $cashRefundAmount,
                        $storeCreditAmount,
                        $exchangeSelections,
                        $refundScenario,
                        trim(
                            (string) $this->request->input(
                                'resolution_notes'
                            )
                        )
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
                    ?? 'The non-cash resolution completed, but the payment refund failed.';

                $this->queueNotification(
                    $returnId,
                    'refund_failed'
                );
            } else {
                $_SESSION['returns_success'] =
                    'Return resolution completed successfully.';

                $this->queueNotification(
                    $returnId,
                    'completed'
                );
            }
        } catch (\Throwable $exception) {
            $_SESSION['returns_error'] =
                $exception->getMessage()
                ?: 'Unable to complete the return resolution.';

            $updatedReturn = $this->returns->find(
                $returnId
            );

            if (
                $updatedReturn
                && (
                    $updatedReturn[
                        'resolution_status'
                    ]
                    ?? ''
                ) === 'partial_failed'
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



    private function queueShippingNotification(
        int $returnId,
        ?string $event
    ): void {
        if ($event === null || $event === '') {
            return;
        }

        try {
            $outboxId =
                $this->shippingNotifications
                    ->queueForEvent(
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
                'The shipment was updated, but its customer notification could not be queued: '
                . $exception->getMessage();
        }
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
