<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Mail\EmailOutboxSender;
use App\Services\Mail\OrderNotificationService;
use App\Services\Payments\PaymentService;
use App\Services\Notifications\OrderShippedNotificationPublisher;
use App\Services\Notifications\TrackingUpdatedNotificationPublisher;

class OrderController extends Controller
{
    public function __construct(
        private OrderRepository $orders,
        private CustomerRepository $customers,
        private ProductRepository $products,
        private StoreRepository $stores,
        private PaymentTransactionRepository $paymentTransactions,
        private PaymentService $payments,
        private OrderNotificationService $notifications,
        private EmailOutboxSender $emailSender,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'q' => trim((string) $this->request->input('q')),
            'status' => trim((string) $this->request->input('status')),
            'store_id' => trim((string) $this->request->input('store_id')),
            'date_from' => trim((string) $this->request->input('date_from')),
            'date_to' => trim((string) $this->request->input('date_to')),
        ];

        $success = $_SESSION['orders_success'] ?? null;
        $error = $_SESSION['orders_error'] ?? null;

        unset(
            $_SESSION['orders_success'],
            $_SESSION['orders_error']
        );

        return $this->view('admin.orders.index', [
            'title' => 'Orders',
            'orders' => $this->orders->filtered($filters),
            'filters' => $filters,
            'statuses' => $this->orders->availableStatuses(),
            'stores' => $this->stores->all(),
            'success' => $success,
            'error' => $error,
        ], 'admin');
    }

    public function create()
    {
        $error = $_SESSION['orders_error'] ?? null;

        unset($_SESSION['orders_error']);

        return $this->view('admin.orders.create', [
            'title' => 'Create Order',
            'customers' => $this->customers->all(),
            'products' => $this->products->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $error,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input(
            '_csrf_token'
        );

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['orders_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        $customerId = (int) $this->request->input(
            'customer_id'
        );

        $productId = (int) $this->request->input(
            'product_id'
        );

        $quantity = (int) $this->request->input(
            'quantity'
        );

        $status = trim(
            (string) $this->request->input('status')
        );

        $shippingTotal = max(
            0,
            (float) $this->request->input(
                'shipping_total',
                0
            )
        );

        $taxTotal = max(
            0,
            (float) $this->request->input(
                'tax_total',
                0
            )
        );

        $discountTotal = max(
            0,
            (float) $this->request->input(
                'discount_total',
                0
            )
        );

        if (
            $customerId <= 0
            || $productId <= 0
            || $quantity <= 0
        ) {
            $_SESSION['orders_error'] =
                'Customer, product, and quantity are required.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        $customer = $this->customers->find($customerId);

        if (! $customer) {
            $_SESSION['orders_error'] =
                'Selected customer does not exist.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        $product = $this->products->find($productId);

        if (! $product) {
            $_SESSION['orders_error'] =
                'Selected product does not exist.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        if (
            $quantity
            > (int) $product['inventory_quantity']
        ) {
            $_SESSION['orders_error'] =
                'Not enough inventory is available for this product.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        if (
            (int) $customer['store_id']
            !== (int) $product['store_id']
        ) {
            $_SESSION['orders_error'] =
                'The selected customer and product must belong to the same store.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        $unitPrice = round(
            (float) $product['price'],
            2
        );

        $subtotal = round(
            $unitPrice * $quantity,
            2
        );

        $grandTotal = round(
            $subtotal
            + $taxTotal
            + $shippingTotal
            - $discountTotal,
            2
        );

        if ($grandTotal < 0) {
            $grandTotal = 0.0;
        }

        $orderNumber = 'ORD-'
            . date('Ymd-His')
            . '-'
            . random_int(100, 999);

        try {
            $this->orders->create([
                'order_number' => $orderNumber,
                'store_id' => (int) $customer['store_id'],
                'customer_id' => $customerId,
                'status' => $status ?: 'pending',
                'subtotal' => number_format(
                    $subtotal,
                    2,
                    '.',
                    ''
                ),
                'tax_total' => number_format(
                    $taxTotal,
                    2,
                    '.',
                    ''
                ),
                'shipping_total' => number_format(
                    $shippingTotal,
                    2,
                    '.',
                    ''
                ),
                'discount_total' => number_format(
                    $discountTotal,
                    2,
                    '.',
                    ''
                ),
                'grand_total' => number_format(
                    $grandTotal,
                    2,
                    '.',
                    ''
                ),
            ], [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'] ?? null,
                'quantity' => $quantity,
                'unit_price' => number_format(
                    $unitPrice,
                    2,
                    '.',
                    ''
                ),
                'line_total' => number_format(
                    $subtotal,
                    2,
                    '.',
                    ''
                ),
            ]);
        } catch (\Throwable $exception) {
            $_SESSION['orders_error'] =
                $exception->getMessage()
                ?: 'Unable to create order.';

            $this->response->redirect(
                '/admin/orders/create'
            );

            return;
        }

        $this->csrf->regenerate();

        $_SESSION['orders_success'] =
            'Order created successfully.';

        $this->response->redirect('/admin/orders');
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('id');

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $success = $_SESSION['orders_success'] ?? null;
        $error = $_SESSION['orders_error'] ?? null;

        unset(
            $_SESSION['orders_success'],
            $_SESSION['orders_error']
        );

        $transactions =
            $this->paymentTransactions->allForOrder(
                $id
            );

        $latestCharge =
            $this->paymentTransactions
                ->latestSuccessfulCharge($id);

        $remainingRefundable = 0.0;

        if ($latestCharge) {
            $remainingRefundable = round(
                max(
                    0,
                    (float) $latestCharge['amount']
                    - (float) (
                        $latestCharge[
                            'refunded_amount'
                        ] ?? 0
                    )
                ),
                2
            );
        }

        $refundSessionKey =
            'order_refund_token_' . $id;

        if (
            empty($_SESSION[$refundSessionKey])
            || ! is_string(
                $_SESSION[$refundSessionKey]
            )
        ) {
            $_SESSION[$refundSessionKey] =
                bin2hex(random_bytes(32));
        }

        return $this->view('admin.orders.show', [
            'title' => 'Order Details',
            'order' => $order,
            'items' => $this->orders->itemsForOrder($id),
            'events' => $this->orders->eventsForOrder($id),
            'shippingAddress' =>
                $this->shippingAddressForOrder($id),
            'paymentTransactions' => $transactions,
            'latestSuccessfulCharge' =>
                $latestCharge,
            'remainingRefundable' =>
                $remainingRefundable,
            'refund_request_token' =>
                $_SESSION[$refundSessionKey],
            'csrf_token' => $this->csrf->token(),
            'success' => $success,
            'error' => $error,
        ], 'admin');
    }


    public function refund(Request $request)
    {
        $id = (int) $request->route('id');

        if (! $this->validateCsrfForOrder($id)) {
            return;
        }

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $refundSessionKey =
            'order_refund_token_' . $id;

        $submittedToken = trim(
            (string) $this->request->input(
                'refund_request_token'
            )
        );

        $sessionToken =
            $_SESSION[$refundSessionKey]
            ?? null;

        if (
            ! is_string($sessionToken)
            || $sessionToken === ''
            || $submittedToken === ''
            || ! hash_equals(
                $sessionToken,
                $submittedToken
            )
        ) {
            $_SESSION['orders_error'] =
                'This refund request has expired. Please review the order and try again.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $amount = round(
            (float) $this->request->input(
                'refund_amount',
                0
            ),
            2
        );

        $scenario = strtolower(
            trim(
                (string) $this->request->input(
                    'refund_scenario',
                    'approved'
                )
            )
        );

        if (! in_array(
            $scenario,
            ['approved', 'declined', 'error'],
            true
        )) {
            $_SESSION['orders_error'] =
                'Select a valid refund test scenario.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $latestCharge =
            $this->paymentTransactions
                ->latestSuccessfulCharge($id);

        if (! $latestCharge) {
            $_SESSION['orders_error'] =
                'No successful payment is available to refund.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $remainingRefundable = round(
            max(
                0,
                (float) $latestCharge['amount']
                - (float) (
                    $latestCharge[
                        'refunded_amount'
                    ] ?? 0
                )
            ),
            2
        );

        if ($amount <= 0) {
            $_SESSION['orders_error'] =
                'Refund amount must be greater than zero.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        if ($amount > $remainingRefundable) {
            $_SESSION['orders_error'] =
                'Refund amount cannot exceed $'
                . number_format(
                    $remainingRefundable,
                    2
                )
                . '.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $idempotencyKey =
            'admin-refund-order-'
            . $id
            . '-'
            . $submittedToken;

        try {
            $transaction =
                $this->payments->refundOrder(
                    $id,
                    $amount,
                    [
                        'refund_scenario' =>
                            $scenario,
                    ],
                    $idempotencyKey
                );

            unset($_SESSION[$refundSessionKey]);

            $this->csrf->regenerate();

            if (
                ($transaction['status'] ?? '')
                === 'succeeded'
            ) {
                $_SESSION['orders_success'] =
                    '$'
                    . number_format($amount, 2)
                    . ' refund completed successfully. Inventory was not changed.';
            } else {
                $_SESSION['orders_error'] =
                    $transaction['failure_message']
                    ?? 'The refund was not approved.';
            }
        } catch (\Throwable $exception) {
            unset($_SESSION[$refundSessionKey]);

            $_SESSION['orders_error'] =
                $exception->getMessage()
                ?: 'Unable to process the refund.';
        }

        $this->response->redirect(
            '/admin/orders/' . $id
        );
    }

    public function updateStatus(Request $request)
    {
        $id = (int) $request->route('id');

        if (! $this->validateCsrfForOrder($id)) {
            return;
        }

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $status = trim(
            (string) $this->request->input('status')
        );

        if ($status === '') {
            $_SESSION['orders_error'] =
                'Order status is required.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $oldStatus = (string) (
            $order['status'] ?? ''
        );

        $this->orders->updateStatus(
            $id,
            $status
        );

        if ($oldStatus !== $status) {
            $emailId = $this->notifications
                ->queueStatusUpdate(
                    $id,
                    $status
                );

            if ($emailId !== null) {
                $this->emailSender->sendOne(
                    $emailId
                );
            }
        }

        $this->csrf->regenerate();

        $_SESSION['orders_success'] =
            'Order status updated successfully.';

        $this->response->redirect(
            '/admin/orders/' . $id
        );
    }

    public function updateFulfillment(
        Request $request
    ) {
        $id = (int) $request->route('id');

        if (! $this->validateCsrfForOrder($id)) {
            return;
        }

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $data = [
            'shipping_carrier' => trim(
                (string) $this->request->input(
                    'shipping_carrier'
                )
            ),

            'tracking_number' => trim(
                (string) $this->request->input(
                    'tracking_number'
                )
            ),

            'tracking_url' => trim(
                (string) $this->request->input(
                    'tracking_url'
                )
            ),

            'shipped_at' => trim(
                (string) $this->request->input(
                    'shipped_at'
                )
            ),

            'fulfillment_notes' => trim(
                (string) $this->request->input(
                    'fulfillment_notes'
                )
            ),
        ];

        $wasShipped =
            trim(
                (string) (
                    $order['shipped_at']
                    ?? ''
                )
            ) !== '';

        $willBeShipped =
            $data['shipped_at'] !== '';

        $oldCarrier = trim(
            (string) (
                $order['shipping_carrier']
                ?? ''
            )
        );

        $oldTrackingNumber = trim(
            (string) (
                $order['tracking_number']
                ?? ''
            )
        );

        $oldTrackingUrl = trim(
            (string) (
                $order['tracking_url']
                ?? ''
            )
        );

        $trackingChanged =
            $oldCarrier !== $data['shipping_carrier']
            || $oldTrackingNumber !== $data['tracking_number']
            || $oldTrackingUrl !== $data['tracking_url'];

        if (
            $data['tracking_url'] !== ''
            && ! filter_var(
                $data['tracking_url'],
                FILTER_VALIDATE_URL
            )
        ) {
            $_SESSION['orders_error'] =
                'Please enter a valid tracking URL.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $changed = $this->orders->updateFulfillment(
            $id,
            $data
        );

        if ($changed) {
            /*
             * First transition into a shipped state now uses the
             * Mission Control Notification Event Bridge instead
             * of the legacy hard-coded fulfillment email.
             *
             * Existing fulfillment-only edits continue using the
             * legacy path until tracking.updated is wired next.
             */
            if (! $wasShipped && $willBeShipped) {
                try {
                    $publisher =
                        new OrderShippedNotificationPublisher(
                            $this->orders
                        );

                    $publisher->publish($id);
                } catch (\Throwable $notificationException) {
                    error_log(
                        '[Alasne order.shipped notification] '
                        . $notificationException->getMessage()
                    );
                }
            } elseif (
                $wasShipped
                && $willBeShipped
                && $trackingChanged
            ) {
                try {
                    $trackingPublisher =
                        new TrackingUpdatedNotificationPublisher(
                            $this->orders
                        );

                    $trackingPublisher->publish(
                        $id,
                        [
                            'shipping_carrier' =>
                                $oldCarrier,
                            'tracking_number' =>
                                $oldTrackingNumber,
                            'tracking_url' =>
                                $oldTrackingUrl,
                        ]
                    );
                } catch (\Throwable $notificationException) {
                    error_log(
                        '[Alasne tracking.updated notification] '
                        . $notificationException->getMessage()
                    );
                }
            } else {
                /*
                 * Non-tracking fulfillment edits retain the
                 * existing notification path. The Event Bridge
                 * now owns first shipment and subsequent tracking
                 * changes.
                 */
                $emailId = $this->notifications
                    ->queueFulfillmentUpdate($id);

                if ($emailId !== null) {
                    $this->emailSender->sendOne(
                        $emailId
                    );
                }
            }

            $_SESSION['orders_success'] =
                'Fulfillment details updated successfully.';
        } else {
            $_SESSION['orders_success'] =
                'No fulfillment changes were detected.';
        }

        $this->csrf->regenerate();

        $this->response->redirect(
            '/admin/orders/' . $id
        );
    }

    public function addEvent(Request $request)
    {
        $id = (int) $request->route('id');

        if (! $this->validateCsrfForOrder($id)) {
            return;
        }

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        $title = trim(
            (string) $this->request->input('title')
        );

        $description = trim(
            (string) $this->request->input(
                'description'
            )
        );

        $isPublic =
            (string) $this->request->input(
                'is_public'
            ) === '1';

        if ($title === '') {
            $_SESSION['orders_error'] =
                'Timeline note title is required.';

            $this->response->redirect(
                '/admin/orders/' . $id
            );

            return;
        }

        $this->orders->recordEvent(
            $id,
            $isPublic
                ? 'customer_update'
                : 'admin_note',
            $title,
            $description ?: null,
            null,
            null,
            $isPublic
        );

        $this->csrf->regenerate();

        $_SESSION['orders_success'] =
            'Timeline note added successfully.';

        $this->response->redirect(
            '/admin/orders/' . $id
        );
    }

    public function export()
    {
        $filters = [
            'q' => trim((string) $this->request->input('q')),
            'status' => trim((string) $this->request->input('status')),
            'store_id' => trim((string) $this->request->input('store_id')),
            'date_from' => trim((string) $this->request->input('date_from')),
            'date_to' => trim((string) $this->request->input('date_to')),
        ];

        $orders = $this->orders->filtered(
            $filters,
            5000
        );

        $filename = 'orders-export-'
            . date('Y-m-d-His')
            . '.csv';

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="'
            . $filename
            . '"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Order ID',
            'Order Number',
            'Store',
            'Customer Name',
            'Customer Email',
            'Status',
            'Payment Status',
            'Payment Method',
            'Payment Provider',
            'Currency',
            'Amount Paid',
            'Amount Refunded',
            'Subtotal',
            'Tax',
            'Shipping Method',
            'Shipping Method Code',
            'Shipping',
            'Estimated Days Minimum',
            'Estimated Days Maximum',
            'Discount',
            'Total',
            'Shipping Carrier',
            'Tracking Number',
            'Tracking URL',
            'Shipped At',
            'Placed At',
            'Created At',
        ]);

        foreach ($orders as $order) {
            fputcsv($output, [
                $order['id'] ?? '',
                $order['order_number'] ?? '',
                $order['store_name'] ?? '',
                $order['customer_name'] ?? '',
                $order['customer_email'] ?? '',
                $order['status'] ?? '',
                $order['payment_status'] ?? '',
                $order['payment_method_name'] ?? '',
                $order['payment_provider'] ?? '',
                $order['currency'] ?? 'USD',
                $order['amount_paid'] ?? '',
                $order['amount_refunded'] ?? '',
                $order['subtotal'] ?? '',
                $order['tax_total'] ?? '',
                $order['shipping_method_name'] ?? '',
                $order['shipping_method_code'] ?? '',
                $order['shipping_total'] ?? '',
                $order['shipping_estimated_days_min'] ?? '',
                $order['shipping_estimated_days_max'] ?? '',
                $order['discount_total'] ?? '',
                $order['grand_total']
                    ?? $order['total']
                    ?? '',
                $order['shipping_carrier'] ?? '',
                $order['tracking_number'] ?? '',
                $order['tracking_url'] ?? '',
                $order['shipped_at'] ?? '',
                $order['placed_at'] ?? '',
                $order['created_at'] ?? '',
            ]);
        }

        fclose($output);

        exit;
    }

    public function packingSlip(Request $request)
    {
        $id = (int) $request->route('id');

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        return $this->view(
            'admin.orders.packing-slip',
            [
                'title' =>
                    'Packing Slip '
                    . $order['order_number'],
                'order' => $order,
                'items' =>
                    $this->orders->itemsForOrder($id),
                'shippingAddress' =>
                    $this->shippingAddressForOrder($id),
            ]
        );
    }

    public function invoice(Request $request)
    {
        $id = (int) $request->route('id');

        $order = $this->orders->find($id);

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        return $this->view(
            'admin.orders.invoice',
            [
                'title' =>
                    'Invoice '
                    . $order['order_number'],
                'order' => $order,
                'items' =>
                    $this->orders->itemsForOrder($id),
                'shippingAddress' =>
                    $this->shippingAddressForOrder($id),
            ]
        );
    }

    private function validateCsrfForOrder(
        int $orderId
    ): bool {
        $csrfToken = (string) $this->request->input(
            '_csrf_token'
        );

        if ($this->csrf->validate($csrfToken)) {
            return true;
        }

        $_SESSION['orders_error'] =
            'Security token expired. Please try again.';

        $this->response->redirect(
            '/admin/orders/' . $orderId
        );

        return false;
    }

    private function shippingAddressForOrder(
        int $orderId
    ): ?array {
        if (
            ! method_exists(
                $this->orders,
                'addressForOrder'
            )
        ) {
            return null;
        }

        $address = $this->orders->addressForOrder(
            $orderId,
            'shipping'
        );

        return is_array($address)
            ? $address
            : null;
    }
}
