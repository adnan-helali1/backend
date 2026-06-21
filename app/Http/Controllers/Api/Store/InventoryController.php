<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\ExternalPurchase;
use App\Models\StoreInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function index()
    {
        $store = Auth::guard('store_api')->user();

        $inventories = StoreInventory::query()
            ->where('store_id', $store->id)
            ->with([
                'storeProduct.supplierProduct.supplier',
                'storeProduct.supplierProduct.product',
            ])
            ->get();

        return response()->json([
            'data' => $inventories,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function manualAdd(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $validator = Validator::make($request->all(), [
            'store_product_id' => ['required', 'integer', 'exists:store_products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'seller_name' => ['required', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $result = DB::transaction(function () use ($store, $data) {
            $purchase = ExternalPurchase::create([
                'store_id' => $store->id,
                'store_product_id' => (int) $data['store_product_id'],
                'quantity' => (int) $data['quantity'],
                'unit_price' => (float) $data['unit_price'],
                'seller_name' => $data['seller_name'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            StoreInventory::where('store_id', $store->id)
                ->where('store_product_id', (int) $data['store_product_id'])
                ->increment('quantity', (int) $data['quantity']);

            return $purchase->load('storeProduct.supplierProduct.product');
        });

        return response()->json([
            'data' => $result,
            'message' => 'Stock added successfully',
            'errors' => null,
        ], 201);
    }

    public function externalPurchases(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $purchases = ExternalPurchase::query()
            ->where('store_id', $store->id)
            ->with('storeProduct.supplierProduct.product')
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $purchases,
            'message' => 'Success',
            'errors' => null,
        ]);
    }
}
