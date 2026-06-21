<?php

namespace App\Http\Controllers\Api\Store;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreCatalogCollection;
use App\Models\StoreInventory;
use App\Models\StoreProduct;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $query = $this->catalogQuery($store->id, $request)
            ->with([
                'supplierProduct:id,supplier_id,product_id,buy_price,stock_quantity',
                'supplierProduct.supplier:id,name',
                'supplierProduct.product:id,category_id,name',
                'supplierProduct.product.category',
            ]);

        $summary = $this->catalogSummary($store->id, $request);

        $catalog = $query->orderByDesc('store_products.id')
            ->paginate((int) $request->query('per_page', 15));

        return new StoreCatalogCollection($catalog, $summary);
    }

    private function catalogQuery(int $storeId, Request $request): Builder
    {
        $query = StoreProduct::query()
            ->select([
                'store_products.id',
                'store_products.supplier_product_id',
                'store_products.sell_price',
                'store_products.is_active',
            ])
            ->where('store_products.store_id', $storeId);

        $this->applyCatalogFilters($query, $request);

        return $query;
    }

    private function applyCatalogFilters(Builder $query, Request $request): void
    {
        if ($request->filled('is_active')) {
            $query->where(
                'store_products.is_active',
                filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN),
            );
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas(
                'supplierProduct.product',
                fn (Builder $productQuery) => $productQuery->where('name', 'like', "%{$search}%"),
            );
        }
    }

    private function catalogSummary(int $storeId, Request $request): array
    {
        $summaryQuery = StoreProduct::query()
            ->where('store_products.store_id', $storeId)
            ->join('supplier_products', 'store_products.supplier_product_id', '=', 'supplier_products.id');

        $this->applyCatalogFilters($summaryQuery, $request);

        $result = $summaryQuery
            ->selectRaw('COUNT(store_products.id) as total_products')
            ->selectRaw('SUM(CASE WHEN store_products.is_active = 1 THEN 1 ELSE 0 END) as active_products')
            ->selectRaw('COALESCE(SUM((COALESCE(store_products.sell_price, 0) - supplier_products.buy_price) * supplier_products.stock_quantity), 0) as total_profit')
            ->first();

        return [
            'total_products' => (int) ($result->total_products ?? 0),
            'active_products' => (int) ($result->active_products ?? 0),
            'total_profit' => (float) ($result->total_profit ?? 0),
        ];
    }

    public function add(Request $request, string $supplierProductId)
    {
        $store = Auth::guard('store_api')->user();

        $supplierProduct = SupplierProduct::query()
            ->with(['supplier', 'product.category'])
            ->findOrFail($supplierProductId);

        if ($supplierProduct->status === 'archived' || $supplierProduct->supplier?->status !== 'active') {
            throw new ApiException(
                errorCode: 'SUPPLIER_PRODUCT_NOT_AVAILABLE',
                message: 'Supplier product is not available.',
                status: 422,
            );
        }

        $validator = Validator::make($request->all(), [
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $storeProduct = StoreProduct::updateOrCreate(
            ['store_id' => $store->id, 'supplier_product_id' => $supplierProduct->id],
            [
                'product_id' => $supplierProduct->product_id,
                'sell_price' => $data['sell_price'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]
        );
        StoreInventory::firstOrCreate(
    [
        'store_id' => $store->id,
        'store_product_id' => $storeProduct->id,
    ],
    [
        'quantity' => 0,
        'min_stock' => 10,
    ]
);

        return response()->json([
            'data' => $storeProduct->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
            'message' => 'Success',
            'errors' => null,
        ], 201);
    }
public function update(Request $request, string $storeProductId)
{
    $store = Auth::guard('store_api')->user();

    // ✅ غيّر البحث ليستخدم id بدل supplier_product_id
    $storeProduct = StoreProduct::where('store_id', $store->id)
        ->where('id', (int) $storeProductId)
        ->firstOrFail();

    $validator = Validator::make($request->all(), [
        'sell_price' => ['nullable', 'numeric', 'min:0'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'data' => null,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422);
    }

    // ✅ تحديث فقط الحقول المرسلة
    $data = $validator->validated();
    
    // إزالة القيم null لتجنب مسح البيانات الموجودة
    $data = array_filter($data, fn($value) => $value !== null);
    
    if (!empty($data)) {
        $storeProduct->update($data);
    }

    return response()->json([
        'data' => $storeProduct->fresh()->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
        'message' => 'Updated',
        'errors' => null,
    ]);
}

    public function remove(string $storeProductId)
    {
        $store = Auth::guard('store_api')->user();

        // البحث عن المنتج
        $storeProduct = StoreProduct::where('store_id', $store->id)
            ->where('id', (int) $storeProductId)
            ->first();

        if (!$storeProduct) {
            return response()->json([
                'data' => null,
                'message' => 'Catalog item not found',
                'errors' => ['id' => ['The specified catalog item does not exist']],
            ], 404);
        }

        // التحقق من وجود مبيعات مرتبطة بهذا المنتج
        $hasSales = \DB::table('sales_items')
            ->where('store_product_id', $storeProduct->id)
            ->exists();

        if ($hasSales) {
            return response()->json([
                'data' => null,
                'message' => 'Cannot delete catalog item',
                'errors' => [
                    'id' => ['This product has sales history and cannot be deleted. Consider deactivating it instead.']
                ],
            ], 422);
        }

        // التحقق من وجود طلبات شراء مرتبطة (نفس المتجر ونفس منتج المورد)
        $hasOrders = \DB::table('order_items')
            ->join('purchase_orders', 'order_items.order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.store_id', $storeProduct->store_id)
            ->where('order_items.supplier_product_id', $storeProduct->supplier_product_id)
            ->exists();

        if ($hasOrders) {
            return response()->json([
                'data' => null,
                'message' => 'Cannot delete catalog item',
                'errors' => [
                    'id' => ['This product has purchase history and cannot be deleted. Consider deactivating it instead.']
                ],
            ], 422);
        }

        // حذف المنتج إذا لم يكن مستخدماً
        $storeProduct->delete();

        return response()->noContent();
    }
}
