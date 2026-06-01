<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\SalesItem;
use App\Models\SalesOrder;
use App\Models\SalesPayment;
use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $query = SalesOrder::query()
            ->where('store_id', $store->id)
            ->with(['customer', 'items.storeProduct.supplierProduct.product', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $orders = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $orders,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function show(string $id)
    {
        $store = Auth::guard('store_api')->user();

        $order = SalesOrder::query()
            ->where('store_id', $store->id)
            ->with(['customer', 'items.storeProduct.supplierProduct.product.category', 'payments'])
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
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_id' => ['required', 'integer', 'exists:store_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_sell_price' => ['nullable', 'numeric', 'min:0'],

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

        $result = DB::transaction(function () use ($payload, $store) {
            $customerId = $payload['customer_id'] ?? null;
            if (! $customerId && isset($payload['customer'])) {
                $customer = Customer::create([
                    'store_id' => $store->id,
                    'name' => $payload['customer']['name'],
                    'phone' => $payload['customer']['phone'] ?? null,
                    'email' => $payload['customer']['email'] ?? null,
                    'address' => $payload['customer']['address'] ?? null,
                ]);
                $customerId = $customer->id;
            }

            $order = SalesOrder::create([
                'store_id' => $store->id,
                'customer_id' => $customerId,
                'status' => 'draft',
                'total' => 0,
                'total_cost' => 0,
                'profit' => 0,
                'paid_amount' => 0,
                'notes' => $payload['notes'] ?? null,
            ]);

            $total = 0.0;
            $totalCost = 0.0;

            foreach ($payload['items'] as $item) {
                $storeProduct = StoreProduct::query()
                    ->where('store_id', $store->id)
                    ->whereKey((int) $item['store_product_id'])
                    ->with('supplierProduct.product')
                    ->firstOrFail();

                if (! $storeProduct->is_active) {
                    throw new ApiException(
                        errorCode: 'CATALOG_ITEM_INACTIVE',
                        message: 'Catalog item is inactive.',
                        status: 422,
                    );
                }

                $supplierProduct = $storeProduct->supplierProduct;
                if (! $supplierProduct) {
                    throw new ApiException(
                        errorCode: 'CATALOG_MISSING_SUPPLIER_PRODUCT',
                        message: 'Store product is missing supplier product linkage.',
                        status: 422,
                    );
                }

                $qty = (int) $item['quantity'];
                $unitSell = array_key_exists('unit_sell_price', $item) && $item['unit_sell_price'] !== null
                    ? (float) $item['unit_sell_price']
                    : ($storeProduct->sell_price !== null ? (float) $storeProduct->sell_price : null);

                if ($unitSell === null) {
                    throw new ApiException(
                        errorCode: 'SELL_PRICE_REQUIRED',
                        message: 'Sell price is required (set catalog sell_price or pass unit_sell_price).',
                        status: 422,
                        errors: [
                            'items' => ['unit_sell_price is missing and catalog sell_price is not set.'],
                        ],
                    );
                }

                $unitBuy = (float) $supplierProduct->buy_price;

                $lineTotal = $unitSell * $qty;
                $lineCost = $unitBuy * $qty;

                SalesItem::create([
                    'sales_order_id' => $order->id,
                    'store_product_id' => $storeProduct->id,
                    'supplier_product_id' => $supplierProduct->id,
                    'quantity' => $qty,
                    'unit_sell_price' => $unitSell,
                    'unit_buy_price' => $unitBuy,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                ]);

                $total += $lineTotal;
                $totalCost += $lineCost;
            }

            $order->update([
                'total' => $total,
                'total_cost' => $totalCost,
                'profit' => $total - $totalCost,
            ]);

            return $order->load(['customer', 'items.storeProduct.supplierProduct.product.category', 'payments']);
        });

        return response()->json([
            'data' => $result,
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    public function pay(Request $request, string $id)
    {
        $store = Auth::guard('store_api')->user();

        $order = SalesOrder::query()
            ->where('store_id', $store->id)
            ->with('payments')
            ->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json([
                'data' => null,
                'message' => 'Cannot pay a cancelled sale',
                'errors' => null,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $result = DB::transaction(function () use ($order, $data) {
            $payment = SalesPayment::create([
                'sales_order_id' => $order->id,
                'amount' => (float) $data['amount'],
                'method' => $data['method'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $paid = (float) SalesPayment::where('sales_order_id', $order->id)->sum('amount');
            $newStatus = $paid >= (float) $order->total ? 'paid' : 'draft';

            $order->update([
                'paid_amount' => $paid,
                'status' => $newStatus,
            ]);

            return $order->fresh()->load(['customer', 'items.storeProduct.supplierProduct.product.category', 'payments']);
        });

        return response()->json([
            'data' => $result,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function cancel(string $id)
    {
        $store = Auth::guard('store_api')->user();

        $order = SalesOrder::query()
            ->where('store_id', $store->id)
            ->findOrFail($id);

        if ($order->status === 'paid') {
            return response()->json([
                'data' => null,
                'message' => 'Cannot cancel a paid sale',
                'errors' => null,
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'data' => $order->fresh(),
            'message' => 'Cancelled',
            'errors' => null,
        ]);
    }
}
