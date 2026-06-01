<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StoreAuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:stores,email'],
            'password' => ['required', 'string', 'min:8'],
            'address' => ['nullable', 'string'],
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
            'status' => 'active',
        ]);

        $token = Auth::guard('store_api')->login($store);

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'email' => $store->email,
                ],
            ],
            'message' => 'Success',
            'errors' => null,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();

        $token = Auth::guard('store_api')->attempt($credentials);
        if (! $token) {
            return response()->json([
                'data' => null,
                'message' => 'Invalid credentials',
                'errors' => null,
            ], 401);
        }

        $store = Auth::guard('store_api')->user();
        if ($store?->status !== 'active') {
            Auth::guard('store_api')->logout();

            return response()->json([
                'data' => null,
                'message' => 'Account is inactive',
                'errors' => null,
            ], 403);
        }

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'email' => $store->email,
                ],
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function logout()
    {
        Auth::guard('store_api')->logout();

        return response()->json([
            'data' => null,
            'message' => 'Logged out',
            'errors' => null,
        ]);
    }
}

