<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierCategoryController extends Controller
{
    public function index(string $supplierId)
    {
        $supplier = Supplier::with('categories')->findOrFail($supplierId);

        return response()->json([
            'data' => $supplier->categories,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function update(Request $request, string $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);

        $validator = Validator::make($request->all(), [
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplier->categories()->sync($validator->validated()['category_ids']);

        return response()->json([
            'data' => $supplier->categories()->get(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
