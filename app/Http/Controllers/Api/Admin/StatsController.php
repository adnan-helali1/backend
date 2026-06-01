<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SupplierProduct;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private function monthExpression(string $column = 'created_at'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "DATE_FORMAT($column, '%Y-%m')",
            'pgsql' => "to_char($column, 'YYYY-MM')",
            default => "strftime('%Y-%m', $column)", // sqlite
        };
    }

    public function overview()
    {
        $storesTotal = Store::count();
        $activeStores = Store::where('status', 'active')->count();

        $suppliersTotal = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();

        $productsTotal = Product::count();
        $availableProducts = Product::where('status', 'available')->count();

        $supplierProductsTotal = SupplierProduct::count();
        $availableSupplierProducts = SupplierProduct::where('status', 'available')->count();

        $ordersTotal = PurchaseOrder::count();
        $ordersSubmitted = PurchaseOrder::where('status', 'submitted')->count();
        $ordersReceived = PurchaseOrder::where('status', 'received')->count();
        $ordersCancelled = PurchaseOrder::where('status', 'cancelled')->count();

        $totalDebits = (float) StoreLedgerEntry::where('type', 'debit')->sum('amount');
        $totalCredits = (float) StoreLedgerEntry::where('type', 'credit')->sum('amount');
        $totalBalance = $totalCredits - $totalDebits;

        $salesTotal = (float) SalesOrder::where('status', 'paid')->sum('total');
        $salesProfit = (float) SalesOrder::where('status', 'paid')->sum('profit');

        $salesCountTotal = SalesOrder::count();
        $salesCountPaid = SalesOrder::where('status', 'paid')->count();
        $salesCountDraft = SalesOrder::where('status', 'draft')->count();

        return response()->json([
            'data' => [
                'stores_total' => $storesTotal,
                'stores_active' => $activeStores,
                'suppliers_total' => $suppliersTotal,
                'suppliers_active' => $activeSuppliers,
                'products_total' => $productsTotal,
                'products_available' => $availableProducts,
                'supplier_products_total' => $supplierProductsTotal,
                'supplier_products_available' => $availableSupplierProducts,
                'orders_total' => $ordersTotal,
                'orders_submitted' => $ordersSubmitted,
                'orders_received' => $ordersReceived,
                'orders_cancelled' => $ordersCancelled,
                'sales' => [
                    'total_count' => $salesCountTotal,
                    'paid_total' => $salesTotal,
                    'paid_profit' => $salesProfit,
                    'paid_count' => $salesCountPaid,
                    'draft_count' => $salesCountDraft,
                ],
                'ledger' => [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'total_balance' => $totalBalance,
                ],
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function salesTrend()
    {
        $monthExpr = $this->monthExpression('created_at');

        $rows = SalesOrder::query()
            ->selectRaw("$monthExpr as month, sum(total) as total, sum(profit) as profit")
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'data' => $rows,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function ordersTrend()
    {
        $monthExpr = $this->monthExpression('created_at');

        $rows = PurchaseOrder::query()
            ->selectRaw("$monthExpr as month, count(*) as count")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'data' => $rows,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function usersSummary()
    {
        $adminsTotal = Admin::count();
        $adminsActive = Admin::where('status', 'active')->count();

        $storesTotal = Store::count();
        $storesActive = Store::where('status', 'active')->count();

        return response()->json([
            'data' => [
                'admins' => [
                    'total' => $adminsTotal,
                    'active' => $adminsActive,
                ],
                'stores' => [
                    'total' => $storesTotal,
                    'active' => $storesActive,
                ],
                'users_total' => $adminsTotal + $storesTotal,
                'users_active' => $adminsActive + $storesActive,
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function ordersByStatus()
    {
        $rows = PurchaseOrder::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return response()->json([
            'data' => $rows,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function topStores()
    {
        $rows = SalesOrder::query()
            ->select('store_id', DB::raw('sum(total) as total_sales'), DB::raw('sum(profit) as total_profit'))
            ->where('status', 'paid')
            ->groupBy('store_id')
            ->orderByDesc('total_sales')
            ->with('store:id,name,email')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $rows,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function lowStock()
    {
        $threshold = 5;

        $rows = SupplierProduct::query()
            ->where('status', 'available')
            ->where('stock_quantity', '<=', $threshold)
            ->with(['supplier:id,name', 'product:id,name,category_id', 'product.category:id,name'])
            ->orderBy('stock_quantity')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'threshold' => $threshold,
                'items' => $rows,
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }
}
