<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Store::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $stores = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $stores,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return response()->json([
            'data' => null,
            'message' => 'Not implemented',
            'errors' => null,
        ], 501);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $store = Store::findOrFail($id);

        return response()->json([
            'data' => $store,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return response()->json([
            'data' => null,
            'message' => 'Not implemented',
            'errors' => null,
        ], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json([
            'data' => null,
            'message' => 'Not implemented',
            'errors' => null,
        ], 501);
    }

    public function updateStatus(Request $request, string $id)
    {
        $store = Store::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $store->update(['status' => (string) $validator->validated()['status']]);

        return response()->json([
            'data' => $store->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
