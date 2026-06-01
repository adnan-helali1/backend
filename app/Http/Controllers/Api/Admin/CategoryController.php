<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $categories,
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
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create([
            'name' => $validator->validated()['name'],
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'data' => $category,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:categories,name,'.$category->id],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $category->update(collect($data)->only(['name'])->all());

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->noContent();
    }
}
