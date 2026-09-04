<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierOfferController extends Controller
{
    public function index(Request $request)
    {
        $query = SupplierOffer::query()->with(['supplierProduct.supplier', 'supplierProduct.product.category']);

        if ($request->filled('supplier_product_id')) {
            $query->where('supplier_product_id', (int) $request->query('supplier_product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15)),
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'offer_price' => ['required', 'numeric', 'min:0'],
            'offer_stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:available,unavailable'],
            'expires_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $supplierProduct = SupplierProduct::with('product')->findOrFail($data['supplier_product_id']);

        if ($request->hasFile('image')) {
            $supplierProduct->product->clearMediaCollection('image');
            $supplierProduct->product->addMediaFromRequest('image')->toMediaCollection('image');
        }

        $offer = SupplierOffer::create([
            ...collect($data)->except('image')->all(),
            'offer_stock' => $data['offer_stock'] ?? 0,
            'status' => $data['status'] ?? 'available',
        ]);

        return response()->json([
            'data' => $offer->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    public function show(string $id)
    {
        $offer = SupplierOffer::with(['supplierProduct.supplier', 'supplierProduct.product.category'])->findOrFail($id);

        return response()->json([
            'data' => $offer,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $offer = SupplierOffer::with('supplierProduct.product')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'supplier_product_id' => ['sometimes', 'required', 'integer', 'exists:supplier_products,id'],
            'offer_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'offer_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', 'in:available,unavailable'],
            'expires_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $offer->update(collect($data)->except('image')->all());

        if ($request->hasFile('image')) {
            $supplierProduct = isset($data['supplier_product_id'])
                ? SupplierProduct::with('product')->findOrFail($data['supplier_product_id'])
                : $offer->supplierProduct;
            $supplierProduct->product->clearMediaCollection('image');
            $supplierProduct->product->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return response()->json([
            'data' => $offer->fresh()->load(['supplierProduct.supplier', 'supplierProduct.product.category']),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }

    public function destroy(string $id)
    {
        SupplierOffer::findOrFail($id)->delete();

        return response()->noContent();
    }
}
