<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $categories,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:categories,name',
            ],
            'color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'image' => [
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $category = Category::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $category
                ->clearMediaCollection('image')
                ->addMediaFromRequest('image')
                ->toMediaCollection('image');
        }

        return response()->json([
            'data' => $category->fresh(),
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    public function show(string $id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'data' => $category,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'unique:categories,name,'.$category->id,
            ],
            'color' => [
                'sometimes',
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'image' => [
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $category->update(
            collect($data)
                ->only(['name', 'color'])
                ->all()
        );

        if ($request->hasFile('image')) {
            $category
                ->clearMediaCollection('image')
                ->addMediaFromRequest('image')
                ->toMediaCollection('image');
        }

        return response()->json([
            'data' => $category->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->noContent();
    }
}
