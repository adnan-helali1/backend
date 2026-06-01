<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with(['supplier', 'category']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $products,
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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

        $supplier = Supplier::query()->with('categories')->findOrFail($data['supplier_id']);
        $categoryAllowed = $supplier->categories()->whereKey($data['category_id'])->exists();

        if (! $categoryAllowed) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => ['category_id' => ['Category is not assigned to this supplier.']],
            ], 422);
        }

        $product = Product::create([
            ...$data,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'status' => $data['status'] ?? 'available',
        ]);

        return response()->json([
            'data' => $product->load(['supplier', 'category']),
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['supplier', 'category'])->findOrFail($id);

        return response()->json([
            'data' => $product,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'supplier_id' => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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

        $data = $validator->validated();
        $supplierId = (int) ($data['supplier_id'] ?? $product->supplier_id);
        $categoryId = (int) ($data['category_id'] ?? $product->category_id);

        $supplier = Supplier::query()->findOrFail($supplierId);
        $categoryAllowed = $supplier->categories()->whereKey($categoryId)->exists();

        if (! $categoryAllowed) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => ['category_id' => ['Category is not assigned to this supplier.']],
            ], 422);
        }

        $product->update($data);

        return response()->json([
            'data' => $product->fresh()->load(['supplier', 'category']),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->noContent();
    }

    public function updateStock(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

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

        $product->update(['stock_quantity' => (int) $validator->validated()['stock_quantity']]);

        return response()->json([
            'data' => $product->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
