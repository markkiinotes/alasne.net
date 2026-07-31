<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\PurchaseOrderRepository;
use App\Services\Auth\CsrfService;
use App\Services\Dropshipping\DropshipFulfillmentService;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderRepository $purchaseOrders,
        private DropshipFulfillmentService $fulfillment,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'q' => trim((string) $this->request->input('q')),
            'status' => trim((string) $this->request->input('status')),
            'store_id' => (int) $this->request->input('store_id'),
            'supplier_id' => (int) $this->request->input('supplier_id'),
            'order_id' => (int) $this->request->input('order_id'),
        ];
        return $this->view('admin.purchase-orders.index', [
            'title' => 'Purchase Orders',
            'purchaseOrders' => $this->purchaseOrders->all($filters),
            'stores' => $this->purchaseOrders->stores(),
            'suppliers' => $this->purchaseOrders->suppliers(),
            'filters' => $filters,
            'statuses' => ['pending','submitted','accepted','partially_shipped','shipped','delivered','cancelled','failed'],
            'success' => $this->flash('purchase_orders_success'),
            'error' => $this->flash('purchase_orders_error'),
        ], 'admin');
    }

    public function orderOverview(Request $request)
    {
        $orderId = (int) $request->route('order_id');
        $order = $this->purchaseOrders->findOrder(
            $orderId
        );

        if (! $order) {
            http_response_code(404);
            return '404 - Order not found';
        }

        return $this->view(
            'admin.purchase-orders.order',
            [
                'title' =>
                    'Dropshipping | '
                    . $order['order_number'],
                'order' => $order,
                'purchaseOrders' =>
                    $this->purchaseOrders->all(
                        ['order_id' => $orderId]
                    ),
                'exceptions' =>
                    $this->purchaseOrders
                        ->exceptionsForOrder($orderId),
                'csrf_token' => $this->csrf->token(),
                'success' =>
                    $this->flash('orders_success'),
                'error' =>
                    $this->flash('orders_error'),
            ],
            'admin'
        );
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('id');
        $po = $this->purchaseOrders->find($id);
        if (! $po) {
            http_response_code(404);
            return '404 - Purchase order not found';
        }
        return $this->view('admin.purchase-orders.show', [
            'title' => $po['purchase_order_number'],
            'purchaseOrder' => $po,
            'items' => $this->purchaseOrders->items($id),
            'events' => $this->purchaseOrders->events($id),
            'csrf_token' => $this->csrf->token(),
            'success' => $this->flash('purchase_orders_success'),
            'error' => $this->flash('purchase_orders_error'),
        ], 'admin');
    }

    public function routeOrder(Request $request)
    {
        $orderId = (int) $request->route('order_id');
        if (! $this->validateCsrf()) {
            return $this->redirectOrderError($orderId, 'Security token expired.');
        }
        try {
            $summary = $this->fulfillment->routePaidOrder($orderId, true);
            $this->csrf->regenerate();
            $_SESSION['orders_success'] =
                'Supplier routing complete: '
                . (int) ($summary['purchase_order_count'] ?? 0)
                . ' purchase order(s), '
                . (int) ($summary['open_exception_count'] ?? 0)
                . ' open exception(s).';
        } catch (\Throwable $e) {
            $_SESSION['orders_error'] = $e->getMessage() ?: 'Unable to route order.';
        }
        $this->response->redirect('/admin/orders/' . $orderId);
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');
        if (! $this->validateCsrf()) {
            return $this->redirectPoError($id, 'Security token expired.');
        }
        try {
            $status = trim((string) $this->request->input('status'));
            $trackingUrl = trim((string) $this->request->input('tracking_url'));
            if ($trackingUrl !== '' && ! filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Enter a valid tracking URL.');
            }
            $this->purchaseOrders->updateStatus($id, $status, [
                'external_order_id' => $this->request->input('external_order_id'),
                'supplier_reference' => $this->request->input('supplier_reference'),
                'shipping_carrier' => $this->request->input('shipping_carrier'),
                'tracking_number' => $this->request->input('tracking_number'),
                'tracking_url' => $trackingUrl,
                'notes' => $this->request->input('notes'),
                'event_note' => $this->request->input('event_note'),
            ]);
            $this->csrf->regenerate();
            $_SESSION['purchase_orders_success'] = 'Purchase order updated.';
        } catch (\Throwable $e) {
            $_SESSION['purchase_orders_error'] = $e->getMessage() ?: 'Unable to update purchase order.';
        }
        $this->response->redirect('/admin/purchase-orders/' . $id);
    }

    public function resolveException(Request $request)
    {
        $orderId = (int) $request->route('order_id');
        $exceptionId = (int) $request->route('id');
        if (! $this->validateCsrf()) {
            return $this->redirectOrderError($orderId, 'Security token expired.');
        }
        $this->fulfillment->resolveException(
            $exceptionId,
            trim((string) $this->request->input('resolution_note'))
        );
        $this->csrf->regenerate();
        $_SESSION['orders_success'] = 'Fulfillment exception resolved.';
        $this->response->redirect('/admin/orders/' . $orderId);
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate((string) $this->request->input('_csrf_token'));
    }

    private function redirectOrderError(int $orderId, string $message)
    {
        $_SESSION['orders_error'] = $message;
        $this->response->redirect('/admin/orders/' . $orderId);
        return null;
    }

    private function redirectPoError(int $id, string $message)
    {
        $_SESSION['purchase_orders_error'] = $message;
        $this->response->redirect('/admin/purchase-orders/' . $id);
        return null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }
}
