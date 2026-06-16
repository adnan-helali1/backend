<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Exceptions\ApiException;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\StoreLedgerEntry;
use App\Models\StoreProduct;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
   public function index(Request $request)
{
    $store = Auth::guard('store_api')->user();

    $query = PurchaseOrder::query()
        ->where('store_id', $store->id)
        ->with([
            'supplier',
            'items.supplierProduct.supplier',
            'items.supplierProduct.product.category'
        ]);

    if ($request->filled('status')) {
        $query->where('status', (string) $request->query('status'));
    }

    $totalBuy = (clone $query)->sum('total_buy');
    $totalSell = (clone $query)->sum('total_sell');

    $orders = $query
        ->orderByDesc('id')
        ->paginate((int) $request->query('per_page', 15));

    return response()->json([
        'data' => $orders,
        'message' => 'Success',
        'errors' => null,
        'summery' => [
            'total_buy' => (float) $totalBuy,
            'total_sell' => (float) $totalSell,
        ],
    ]);
}    public function show(string $id)
    {
        $store = Auth::guard('store_api')->user();

        $order = PurchaseOrder::query()
            ->where('store_id', $store->id)
            ->with(['supplier', 'items.supplierProduct.supplier', 'items.supplierProduct.product.category'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function store(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $items = collect($payload['items'])
            ->map(fn ($i) => ['supplier_product_id' => (int) $i['supplier_product_id'], 'quantity' => (int) $i['quantity']])
            ->values();

        $supplierProductIds = $items->pluck('supplier_product_id')->unique()->values()->all();

        $supplierProducts = SupplierProduct::query()
            ->with(['supplier', 'product'])
            ->whereIn('id', $supplierProductIds)
            ->get()
            ->keyBy('id');

        $itemErrors = [];

        foreach ($items as $item) {
            $sp = $supplierProducts->get($item['supplier_product_id']);
            if (! $sp) {
                $itemErrors[] = "Supplier product {$item['supplier_product_id']} not found.";
                continue;
            }

            if ($sp->status !== 'available' || $sp->supplier?->status !== 'active') {
                $itemErrors[] = "Supplier product {$sp->id} is not available.";
            }

            if ((int) $sp->stock_quantity < (int) $item['quantity']) {
                $itemErrors[] = "Insufficient stock for supplier product {$sp->id}.";
            }
        }

        if ($itemErrors) {
            throw new ApiException(
                errorCode: 'ORDER_ITEMS_INVALID',
                message: 'Some order items are invalid.',
                status: 422,
                errors: ['items' => $itemErrors],
            );
        }

        $catalog = StoreProduct::query()
            ->where('store_id', $store->id)
            ->whereIn('supplier_product_id', $supplierProductIds)
            ->get()
            ->keyBy('supplier_product_id');

        $now = now();

        $groupedBySupplier = $items->groupBy(function ($item) use ($supplierProducts) {
            return (int) $supplierProducts->get($item['supplier_product_id'])->supplier_id;
        });

        $orders = DB::transaction(function () use ($store, $groupedBySupplier, $supplierProducts, $catalog, $payload, $now) {
            $created = [];

            foreach ($groupedBySupplier as $supplierId => $supplierItems) {
                $totalBuy = 0.0;
                $totalSell = 0.0;
                $hasAnySell = false;

                $order = PurchaseOrder::create([
                    'store_id' => $store->id,
                    'supplier_id' => (int) $supplierId,
                    'status' => 'submitted',
                    'total_buy' => 0,
                    'total_sell' => null,
                    'notes' => $payload['notes'] ?? null,
                ]);

                foreach ($supplierItems as $item) {
                    $sp = $supplierProducts->get($item['supplier_product_id']);
                    $qty = (int) $item['quantity'];
                    $unitBuy = (float) $sp->buy_price;

                    $storeProduct = $catalog->get($sp->id);
                    $unitSell = $storeProduct?->sell_price !== null ? (float) $storeProduct->sell_price : null;

                    $totalBuy += $unitBuy * $qty;

                    if ($unitSell !== null) {
                        $hasAnySell = true;
                        $totalSell += $unitSell * $qty;
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $sp->product_id,
                        'supplier_product_id' => $sp->id,
                        'quantity' => $qty,
                        'unit_buy_price' => $unitBuy,
                        'unit_sell_price' => $unitSell,
                    ]);

                    $sp->decrement('stock_quantity', $qty);
                }

                $order->update([
                    'total_buy' => $totalBuy,
                    'total_sell' => $hasAnySell ? $totalSell : null,
                ]);

                StoreLedgerEntry::create([
                    'store_id' => $store->id,
                    'type' => 'debit',
                    'source_type' => 'order',
                    'source_id' => $order->id,
                    'amount' => $totalBuy,
                    'occurred_at' => $now,
                    'notes' => null,
                    'created_by_admin_id' => null,
                ]);

                $created[] = $order->load(['supplier', 'items.supplierProduct.supplier', 'items.supplierProduct.product.category']);
            }

            return $created;
        });

        return response()->json([
            'data' => [
                'orders' => $orders,
                'created_count' => count($orders),
            ],
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    public function cancel(string $id)
    {
        $store = Auth::guard('store_api')->user();

        $order = PurchaseOrder::query()
            ->where('store_id', $store->id)
            ->with('items')
            ->findOrFail($id);

        if ($order->status !== 'submitted') {
            throw new ApiException(
                errorCode: 'ORDER_CANNOT_CANCEL',
                message: 'Order cannot be cancelled in current status.',
                status: 422,
                errors: ['status' => [$order->status]],
            );
        }

        $now = now();

        $order = DB::transaction(function () use ($order, $now, $store) {
            foreach ($order->items as $item) {
                SupplierProduct::whereKey($item->supplier_product_id)->increment('stock_quantity', (int) $item->quantity);
            }

            $order->update(['status' => 'cancelled']);

            StoreLedgerEntry::create([
                'store_id' => $store->id,
                'type' => 'credit',
                'source_type' => 'order',
                'source_id' => $order->id,
                'amount' => (float) $order->total_buy,
                'occurred_at' => $now,
                'notes' => 'Order cancelled',
                'created_by_admin_id' => null,
            ]);

            return $order->fresh()->load(['supplier', 'items.supplierProduct.supplier', 'items.supplierProduct.product.category']);
        });

        return response()->json([
            'data' => $order,
            'message' => 'Cancelled',
            'errors' => null,
        ]);
    }
}
