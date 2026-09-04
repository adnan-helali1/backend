<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $query = Store::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $stores = $query
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $stores,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:stores,email'],
            'password' => ['required', 'string', 'min:8'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
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

        $store = Store::create([
            'name' => $data['name'],
            'owner_name' => $data['owner_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if ($request->hasFile('image')) {
            $store
                ->addMediaFromRequest('image')
                ->toMediaCollection('image');
        }

        $store->refresh();

        return response()->json([
            'data' => $store,
            'message' => 'Store created successfully',
            'errors' => null,
        ], 201);
    }

    public function show(string $id)
    {
        $store = Store::findOrFail($id);

        return response()->json([
            'data' => $store,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function update(Request $request, string $id)
    {
        return response()->json([
            'data' => null,
            'message' => 'Not implemented',
            'errors' => null,
        ], 501);
    }

    public function destroy(string $id)
    {
        $store = Store::findOrFail($id);

        $store->clearMediaCollection('image');
        $store->delete();

        return response()->json([
            'data' => null,
            'message' => 'Store deleted successfully',
            'errors' => null,
        ]);
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

        $store->update([
            'status' => (string) $validator->validated()['status'],
        ]);

        return response()->json([
            'data' => $store->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
