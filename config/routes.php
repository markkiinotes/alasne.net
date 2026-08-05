<?php

declare(strict_types=1);

use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DropshippingOperationsController;
use App\Controllers\Admin\EmailOutboxController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\PaymentMethodController;
use App\Controllers\Admin\CarrierIntegrationController;
use App\Controllers\Admin\ReturnController;
use App\Controllers\Admin\ReturnPolicyController;
use App\Controllers\Admin\PermissionController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\ProductionReadinessController;
use App\Controllers\Admin\MultiStoreAutomationController;
use App\Controllers\Admin\MissionControlNavigationController;
use App\Controllers\Admin\ReportsKpiController;
use App\Controllers\Admin\ProductSourcingController;
use App\Controllers\Admin\ProductSupplierController;
use App\Controllers\Admin\PurchaseOrderController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\ShippingMethodController;
use App\Controllers\Admin\TaxRuleController;
use App\Controllers\Admin\StoreController;
use App\Controllers\Admin\StoreCreditController;
use App\Controllers\Admin\SupplierController;
use App\Controllers\Admin\SupplierPerformanceController;
use App\Controllers\Admin\TrackingReconciliationController;
use App\Controllers\Admin\SupplierIntegrationController;
use App\Controllers\Admin\SupplierSubmissionController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\CarrierWebhookController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\CustomerReturnController;
use App\Controllers\CustomerAccountController;
use App\Controllers\HomeController;
use App\Controllers\OrderTrackingController;
use App\Controllers\ReturnPolicyPageController;
use App\Controllers\ReturnTrackingController;
use App\Controllers\StorefrontController;

$router = $app->router;

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);


$router->post(
    '/webhooks/carriers/easypost/{store_id}',
    [CarrierWebhookController::class, 'easyPost']
);

$router->get('/', [HomeController::class, 'index']);

$router
    ->get('/admin', [MissionControlNavigationController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:mission_control.view');

$router
    ->get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:mission_control.view');

$router
    ->get('/admin/legacy-dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:mission_control.view');

$router
    ->get(
        '/admin/dropshipping',
        [DropshippingOperationsController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/dropshipping/export',
        [DropshippingOperationsController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/production-readiness',
        [ProductionReadinessController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/production-readiness/run',
        [ProductionReadinessController::class, 'run']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/production-readiness/export',
        [ProductionReadinessController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/production-readiness/runs/{run_id}',
        [ProductionReadinessController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/tracking-reconciliation',
        [TrackingReconciliationController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/tracking-reconciliation/upload',
        [TrackingReconciliationController::class, 'upload']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/tracking-reconciliation/template',
        [TrackingReconciliationController::class, 'template']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/tracking-reconciliation/queue/export',
        [TrackingReconciliationController::class, 'exportQueue']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/tracking-reconciliation/runs/{run_id}',
        [TrackingReconciliationController::class, 'run']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-performance',
        [SupplierPerformanceController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-performance/export',
        [SupplierPerformanceController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/supplier-performance/{supplier_id}/review',
        [SupplierPerformanceController::class, 'review']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-performance/{supplier_id}',
        [SupplierPerformanceController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/product-sourcing',
        [ProductSourcingController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get(
        '/admin/product-sourcing/export',
        [ProductSourcingController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/product-sourcing/rules',
        [ProductSourcingController::class, 'saveRules']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/product-sourcing/reviews/{supplier_product_id}',
        [ProductSourcingController::class, 'review']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/users/{id}/edit', [UserController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:users.manage');

$router
    ->post('/admin/users/{id}', [UserController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:users.manage');

$router
    ->get('/admin/users', [UserController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:users.manage');

$router
    ->get('/admin/users/create', [UserController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:users.manage');

$router
    ->post('/admin/users', [UserController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:users.manage');

$router
    ->get('/admin/roles', [RoleController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:roles.manage');

$router
    ->get('/admin/roles/create', [RoleController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:roles.manage');

$router
    ->post('/admin/roles', [RoleController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:roles.manage');

$router
    ->get('/admin/roles/{id}/edit', [RoleController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:roles.manage');

$router
    ->post('/admin/roles/{id}', [RoleController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:roles.manage');

$router
    ->get('/admin/permissions', [PermissionController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:permissions.manage');

$router
    ->get('/admin/stores', [StoreController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get('/admin/stores/create', [StoreController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post('/admin/stores', [StoreController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');


$router
    ->get(
        '/admin/stores/{store_id}/payment-methods',
        [PaymentMethodController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/payment-methods/create',
        [PaymentMethodController::class, 'create']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/payment-methods',
        [PaymentMethodController::class, 'store']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/payment-methods/{id}/edit',
        [PaymentMethodController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/payment-methods/{id}',
        [PaymentMethodController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/payment-methods/{id}/toggle',
        [PaymentMethodController::class, 'toggle']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/shipping-methods',
        [ShippingMethodController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/shipping-methods/create',
        [ShippingMethodController::class, 'create']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/shipping-methods',
        [ShippingMethodController::class, 'store']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/shipping-methods/{id}/edit',
        [ShippingMethodController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/shipping-methods/{id}',
        [ShippingMethodController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/shipping-methods/{id}/toggle',
        [ShippingMethodController::class, 'toggle']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/shipping-methods/{id}/delete',
        [ShippingMethodController::class, 'destroy']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');


$router
    ->get(
        '/admin/stores/{store_id}/tax-rules',
        [TaxRuleController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/tax-rules/create',
        [TaxRuleController::class, 'create']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/tax-rules',
        [TaxRuleController::class, 'store']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/tax-rules/{id}/edit',
        [TaxRuleController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/tax-rules/{id}',
        [TaxRuleController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/tax-rules/{id}/toggle',
        [TaxRuleController::class, 'toggle']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/tax-rules/{id}/delete',
        [TaxRuleController::class, 'destroy']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');



$router
    ->get(
        '/admin/stores/{store_id}/carrier-integration',
        [CarrierIntegrationController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/carrier-integration',
        [CarrierIntegrationController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/stores/{store_id}/return-policy',
        [ReturnPolicyController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/stores/{store_id}/return-policy',
        [ReturnPolicyController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get('/admin/suppliers', [SupplierController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/suppliers/create', [SupplierController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/suppliers', [SupplierController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get(
        '/admin/suppliers/{supplier_id}/integration',
        [SupplierIntegrationController::class, 'edit']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/suppliers/{supplier_id}/integration',
        [SupplierIntegrationController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/suppliers/{supplier_id}/integration/import',
        [SupplierIntegrationController::class, 'importCsv']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get(
        '/admin/suppliers/{supplier_id}/integration/template',
        [SupplierIntegrationController::class, 'template']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get(
        '/admin/suppliers/{supplier_id}/integration/sync-runs/{run_id}',
        [SupplierIntegrationController::class, 'syncRun']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/suppliers/{id}/edit', [SupplierController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/suppliers/{id}', [SupplierController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/suppliers/{id}', [SupplierController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/stores/{id}/edit', [StoreController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post('/admin/stores/{id}', [StoreController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get('/admin/stores/{id}', [StoreController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get('/admin/products', [ProductController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/products/create', [ProductController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/products', [ProductController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get(
        '/admin/products/{product_id}/suppliers',
        [ProductSupplierController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/products/{product_id}/suppliers',
        [ProductSupplierController::class, 'store']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post(
        '/admin/products/{product_id}/suppliers/{id}/delete',
        [ProductSupplierController::class, 'destroy']
    )
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/products/{id}/edit', [ProductController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/products/{id}', [ProductController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/products/{id}', [ProductController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/customers', [CustomerController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->get('/admin/customers/create', [CustomerController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->post('/admin/customers', [CustomerController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->get('/admin/customers/{id}/edit', [CustomerController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->post('/admin/customers/{id}', [CustomerController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->get('/admin/customers/{id}', [CustomerController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:customers.manage');

$router
    ->get(
        '/admin/customers/{customer_id}/store-credit',
        [StoreCreditController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:customers.manage');


$router
    ->get('/admin/returns', [ReturnController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/orders/{order_id}/returns/create',
        [ReturnController::class, 'create']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{order_id}/returns',
        [ReturnController::class, 'store']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');


$router
    ->post(
        '/admin/returns/{id}/carrier-rates',
        [ReturnController::class, 'requestCarrierRates']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/carrier-rates/purchase',
        [ReturnController::class, 'purchaseCarrierRate']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/shipping',
        [ReturnController::class, 'saveShipping']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/shipping/status',
        [ReturnController::class, 'updateShippingStatus']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/returns/{id}/shipping-label',
        [ReturnController::class, 'shippingLabel']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/returns/{id}/authorization',
        [ReturnController::class, 'authorization']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/returns/{id}', [ReturnController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/approve',
        [ReturnController::class, 'approve']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/receive',
        [ReturnController::class, 'receive']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/complete',
        [ReturnController::class, 'complete']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/returns/{id}/cancel',
        [ReturnController::class, 'cancel']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/orders', [OrderController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/orders/create', [OrderController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post('/admin/orders', [OrderController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/orders/export', [OrderController::class, 'export'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/orders/{id}/packing-slip',
        [OrderController::class, 'packingSlip']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/orders/{id}/invoice',
        [OrderController::class, 'invoice']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/orders/{order_id}/dropship',
        [PurchaseOrderController::class, 'orderOverview']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{order_id}/dropship/route',
        [PurchaseOrderController::class, 'routeOrder']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{order_id}/dropship/exceptions/{id}/resolve',
        [PurchaseOrderController::class, 'resolveException']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-submissions',
        [SupplierSubmissionController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/purchase-orders/{purchase_order_id}/submission/prepare',
        [SupplierSubmissionController::class, 'prepare']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-submissions/{id}/export',
        [SupplierSubmissionController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/supplier-submissions/{id}/status',
        [SupplierSubmissionController::class, 'updateStatus']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/supplier-submissions/{id}',
        [SupplierSubmissionController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/purchase-orders',
        [PurchaseOrderController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/purchase-orders/{id}',
        [PurchaseOrderController::class, 'update']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get(
        '/admin/purchase-orders/{id}',
        [PurchaseOrderController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/orders/{id}', [OrderController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{id}/refund',
        [OrderController::class, 'refund']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{id}/status',
        [OrderController::class, 'updateStatus']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{id}/fulfillment',
        [OrderController::class, 'updateFulfillment']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/orders/{id}/events',
        [OrderController::class, 'addEvent']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/inventory', [InventoryController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/inventory/adjust', [InventoryController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/inventory/adjust', [InventoryController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/categories', [CategoryController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/categories/create', [CategoryController::class, 'create'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/categories', [CategoryController::class, 'store'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/categories/{id}/edit', [CategoryController::class, 'edit'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->post('/admin/categories/{id}', [CategoryController::class, 'update'])
    ->middleware('auth')
    ->middleware('permission:products.manage');

$router
    ->get('/admin/email-outbox', [EmailOutboxController::class, 'index'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->post(
        '/admin/email-outbox/send-pending',
        [EmailOutboxController::class, 'sendPending']
    )
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router
    ->get('/admin/email-outbox/{id}', [EmailOutboxController::class, 'show'])
    ->middleware('auth')
    ->middleware('permission:orders.manage');

$router->get(
    '/store/{store_slug}/account',
    [CustomerAccountController::class, 'show']
);

$router->post(
    '/store/{store_slug}/account/link',
    [CustomerAccountController::class, 'requestLink']
);

$router->get(
    '/store/{store_slug}/account/session/{token}',
    [CustomerAccountController::class, 'session']
);

$router->get(
    '/store/{store_slug}/account/dashboard',
    [CustomerAccountController::class, 'dashboard']
);

$router->get(
    '/store/{store_slug}/account/orders/{order_id}',
    [CustomerAccountController::class, 'order']
);

$router->get(
    '/store/{store_slug}/account/store-credit',
    [CustomerAccountController::class, 'storeCredit']
);

$router->post(
    '/store/{store_slug}/account/profile',
    [CustomerAccountController::class, 'updateProfile']
);

$router->post(
    '/store/{store_slug}/account/logout',
    [CustomerAccountController::class, 'logout']
);

$router->get(
    '/store/{store_slug}/product/{product_slug}',
    [StorefrontController::class, 'product']
);

$router->get(
    '/store/{store_slug}/category/{category_slug}',
    [StorefrontController::class, 'category']
);

$router->get(
    '/store/{store_slug}/cart',
    [CartController::class, 'show']
);

$router->post(
    '/store/{store_slug}/cart/add',
    [CartController::class, 'add']
);

$router->post(
    '/store/{store_slug}/cart/update',
    [CartController::class, 'update']
);

$router->post(
    '/store/{store_slug}/cart/remove',
    [CartController::class, 'remove']
);

$router->get(
    '/store/{store_slug}/checkout',
    [CheckoutController::class, 'show']
);

$router->post(
    '/store/{store_slug}/checkout/store-credit',
    [CheckoutController::class, 'storeCreditBalance']
);

$router->post(
    '/store/{store_slug}/checkout',
    [CheckoutController::class, 'store']
);

$router->get(
    '/store/{store_slug}/checkout/success',
    [CheckoutController::class, 'success']
);




$router->get(
    '/store/{store_slug}/returns/policy',
    [ReturnPolicyPageController::class, 'show']
);

$router->get(
    '/store/{store_slug}/returns/request',
    [CustomerReturnController::class, 'show']
);

$router->post(
    '/store/{store_slug}/returns/request/lookup',
    [CustomerReturnController::class, 'lookup']
);

$router->post(
    '/store/{store_slug}/returns/request',
    [CustomerReturnController::class, 'store']
);

$router->get(
    '/store/{store_slug}/returns/request/success/{token}',
    [CustomerReturnController::class, 'success']
);

$router->post(
    '/store/{store_slug}/returns/shipping-label',
    [ReturnTrackingController::class, 'shippingLabel']
);

$router->post(
    '/store/{store_slug}/returns/authorization',
    [ReturnTrackingController::class, 'authorization']
);

$router->get(
    '/store/{store_slug}/returns/track',
    [ReturnTrackingController::class, 'show']
);

$router->post(
    '/store/{store_slug}/returns/track',
    [ReturnTrackingController::class, 'lookup']
);

$router->get(
    '/store/{store_slug}/track',
    [OrderTrackingController::class, 'show']
);

$router->post(
    '/store/{store_slug}/track',
    [OrderTrackingController::class, 'lookup']
);

$router->get(
    '/store/{store_slug}/receipt',
    [OrderTrackingController::class, 'receipt']
);

$router->get(
    '/store/{slug}',
    [StorefrontController::class, 'show']
);

$router
    ->get(
        '/admin/multi-store-automation',
        [MultiStoreAutomationController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/multi-store-automation/export',
        [MultiStoreAutomationController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/multi-store-automation/audit',
        [MultiStoreAutomationController::class, 'saveAudit']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/multi-store-automation/catalog-candidates/refresh',
        [MultiStoreAutomationController::class, 'refreshCandidates']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/multi-store-automation/catalog-candidates/{candidate_id}',
        [MultiStoreAutomationController::class, 'reviewCandidate']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/multi-store-automation/runs/{run_id}/export',
        [MultiStoreAutomationController::class, 'exportRun']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/multi-store-automation/runs/{run_id}',
        [MultiStoreAutomationController::class, 'run']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->post(
        '/admin/multi-store-automation/{store_id}/profile',
        [MultiStoreAutomationController::class, 'saveProfile']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');

$router
    ->get(
        '/admin/multi-store-automation/{store_id}',
        [MultiStoreAutomationController::class, 'show']
    )
    ->middleware('auth')
    ->middleware('permission:stores.manage');


$router
    ->get(
        '/admin/mission-control',
        [MissionControlNavigationController::class, 'index']
    )
    ->middleware('auth');

$router
    ->get(
        '/admin/workflows',
        [MissionControlNavigationController::class, 'index']
    )
    ->middleware('auth');

$router
    ->get(
        '/admin/navigation',
        [MissionControlNavigationController::class, 'index']
    )
    ->middleware('auth');


$router
    ->get(
        '/admin/reports',
        [ReportsKpiController::class, 'index']
    )
    ->middleware('auth')
    ->middleware('permission:mission_control.view');

$router
    ->get(
        '/admin/reports/export',
        [ReportsKpiController::class, 'export']
    )
    ->middleware('auth')
    ->middleware('permission:mission_control.view');

return $router;

