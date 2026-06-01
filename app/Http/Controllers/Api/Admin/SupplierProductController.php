<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SupplierProduct::query()->with(['supplier', 'product.category']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $result = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $result,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'buy_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:available,unavailable,archived'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $product = Product::findOrFail($data['product_id']);

        $categoryAllowed = $supplier->categories()->whereKey($product->category_id)->exists();
        if (! $categoryAllowed) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => ['supplier_id' => ['Supplier is not assigned to the product category.']],
            ], 422);
        }

        $supplierProduct = SupplierProduct::updateOrCreate(
            ['supplier_id' => (int) $data['supplier_id'], 'product_id' => (int) $data['product_id']],
            [
                'buy_price' => (float) $data['buy_price'],
                'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                'status' => (string) ($data['status'] ?? 'available'),
            ],
        );

        return response()->json([
            'data' => $supplierProduct->load(['supplier', 'product.category']),
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $supplierProduct = SupplierProduct::with(['supplier', 'product.category'])->findOrFail($id);

        return response()->json([
            'data' => $supplierProduct,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplierProduct = SupplierProduct::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'buy_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', 'in:available,unavailable,archived'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplierProduct->update($validator->validated());

        return response()->json([
            'data' => $supplierProduct->fresh()->load(['supplier', 'product.category']),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplierProduct = SupplierProduct::findOrFail($id);
        $supplierProduct->delete();

        return response()->noContent();
    }

    public function updateStock(Request $request, string $id)
    {
        $supplierProduct = SupplierProduct::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplierProduct->update(['stock_quantity' => (int) $validator->validated()['stock_quantity']]);

        return response()->json([
            'data' => $supplierProduct->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
