<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\FinanceController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SalesController as AdminSalesController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Api\Admin\SupplierCategoryController;
use App\Http\Controllers\Api\Admin\SupplierController;
use App\Http\Controllers\Api\Admin\SupplierProductController;
use App\Http\Controllers\Api\Store\CatalogController;
use App\Http\Controllers\Api\Store\DashboardController;
use App\Http\Controllers\Api\Store\InventoryController;
use App\Http\Controllers\Api\Store\LedgerController;
use App\Http\Controllers\Api\Store\OfferController;
use App\Http\Controllers\Api\Store\OrderController;
use App\Http\Controllers\Api\Store\ProductController as StoreProductController;
use App\Http\Controllers\Api\Store\ProfileController;
use App\Http\Controllers\Api\Store\SalesController;
use App\Http\Controllers\Api\Store\StoreAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('refresh', [AdminAuthController::class, 'refresh']);

    Route::middleware('auth:admin_api')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::get('suppliers/{supplier}/categories', [SupplierCategoryController::class, 'index']);
        Route::put('suppliers/{supplier}/categories', [SupplierCategoryController::class, 'update']);

        Route::apiResource('categories', CategoryController::class);

        Route::apiResource('products', ProductController::class);
        Route::patch('products/{product}/stock', [ProductController::class, 'updateStock']);

        Route::apiResource('supplier-products', SupplierProductController::class);
        Route::patch('supplier-products/{supplier_product}/stock', [SupplierProductController::class, 'updateStock']);

        Route::get('stores', [AdminStoreController::class, 'index']);
        Route::get('stores/{store}', [AdminStoreController::class, 'show']);
        Route::put('stores/{store}/status', [AdminStoreController::class, 'updateStatus']);

        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        Route::get('sales', [AdminSalesController::class, 'index']);
        Route::get('sales/{sale}', [AdminSalesController::class, 'show']);

        Route::get('stores/{store}/ledger', [FinanceController::class, 'ledger']);
        Route::post('stores/{store}/payments', [FinanceController::class, 'payment']);
        Route::post('stores/{store}/adjustments', [FinanceController::class, 'adjustment']);

        Route::get('stats/overview', [StatsController::class, 'overview']);
        Route::get('stats/sales-trend', [StatsController::class, 'salesTrend']);
        Route::get('stats/orders-trend', [StatsController::class, 'ordersTrend']);
        Route::get('stats/orders-by-status', [StatsController::class, 'ordersByStatus']);
        Route::get('stats/users-summary', [StatsController::class, 'usersSummary']);
        Route::get('stats/top-stores', [StatsController::class, 'topStores']);
        Route::get('stats/low-stock', [StatsController::class, 'lowStock']);
    });
});

Route::prefix('store')->group(function () {
    Route::post('register', [StoreAuthController::class, 'register']);
    Route::post('login', [StoreAuthController::class, 'login']);
    Route::post('refresh', [StoreAuthController::class, 'refresh']);

    Route::middleware('auth:store_api')->group(function () {
        Route::post('logout', [StoreAuthController::class, 'logout']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);

        Route::get('products', [StoreProductController::class, 'index']);
        Route::get('products/{product}', [StoreProductController::class, 'show']);

        Route::get('catalog', [CatalogController::class, 'index']);
        Route::post('catalog/{supplierProduct}', [CatalogController::class, 'add']);
        Route::patch('catalog/{storeProductId}', [CatalogController::class, 'update']);
        Route::delete('catalog/{storeProductId}', [CatalogController::class, 'remove']);

        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::put('orders/{order}/cancel', [OrderController::class, 'cancel']);

        Route::get('ledger', [LedgerController::class, 'index']);

        Route::get('sales', [SalesController::class, 'index']);
        Route::post('sales', [SalesController::class, 'store']);
        Route::get('sales/{sale}', [SalesController::class, 'show']);
        Route::post('sales/{sale}/pay', [SalesController::class, 'pay']);
        Route::put('sales/{sale}/cancel', [SalesController::class, 'cancel']);

        Route::get('offers', [OfferController::class, 'index']);

        Route::get('dashboard', [DashboardController::class, 'show']);

        Route::get('inventory', [InventoryController::class, 'index']);
        Route::post('inventory/manual-add', [InventoryController::class, 'manualAdd']);
        Route::get('external-purchases', [InventoryController::class, 'externalPurchases']);

    });
});
