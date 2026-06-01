<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Exceptions\ApiException;
use App\Models\SupplierProduct;
use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $query = StoreProduct::query()
            ->where('store_id', $store->id)
            ->with(['supplierProduct.supplier', 'supplierProduct.product.category']);

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $catalog = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $catalog,
            'message' => 'Success',
            'errors' => null,
        ]);
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

        return response()->json([
            'data' => $storeProduct->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
            'message' => 'Success',
            'errors' => null,
        ], 201);
    }

    public function update(Request $request, string $supplierProductId)
    {
        $store = Auth::guard('store_api')->user();

        $storeProduct = StoreProduct::where('store_id', $store->id)
            ->where('supplier_product_id', (int) $supplierProductId)
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

        $storeProduct->update($validator->validated());

        return response()->json([
            'data' => $storeProduct->fresh()->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }

    public function remove(string $supplierProductId)
    {
        $store = Auth::guard('store_api')->user();

        StoreProduct::where('store_id', $store->id)
            ->where('supplier_product_id', (int) $supplierProductId)
            ->delete();

        return response()->noContent();
    }
}
