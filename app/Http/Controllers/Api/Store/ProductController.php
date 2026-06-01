<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SupplierProduct::query()
            ->with(['supplier', 'product.category'])
            ->where('status', 'available')
            ->whereHas('supplier', fn ($q) => $q->where('status', 'active'));

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', (int) $request->query('category_id')));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ((bool) $request->query('in_stock_only', false)) {
            $query->where('stock_quantity', '>', 0);
        }

        $products = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $products,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function show(string $id)
    {
        $product = SupplierProduct::query()
            ->with(['supplier', 'product.category'])
            ->where('status', 'available')
            ->findOrFail($id);

        return response()->json([
            'data' => $product,
            'message' => 'Success',
            'errors' => null,
        ]);
    }
}
