<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::query()->with(['store', 'customer']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->query('store_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to_date'));
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
        $order = SalesOrder::query()
            ->with(['store', 'customer', 'items.storeProduct.supplierProduct.supplier', 'items.storeProduct.supplierProduct.product.category', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
            'message' => 'Success',
            'errors' => null,
        ]);
    }
}
