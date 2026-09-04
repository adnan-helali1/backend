<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

        $store->addMediaFromRequest('image')->toMediaCollection('image');
        $store->refresh();

        $token = Auth::guard('store_api')->login($store);

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'email' => $store->email,
                    'image_url' => $store->image_url,
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
                    'image_url' => $store->image_url,
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

    public function refresh()
    {
        $guard = Auth::guard('store_api');

        try {
            $token = $guard->refresh();
            $store = $guard->setToken($token)->user();

            if (! $store || $store->status !== 'active') {
                $guard->setToken($token)->invalidate();

                return response()->json([
                    'data' => null,
                    'message' => $store ? 'Account is inactive' : 'Invalid token',
                    'errors' => null,
                ], $store ? 403 : 401);
            }

            return response()->json([
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
                'message' => 'Token refreshed',
                'errors' => null,
            ]);
        } catch (JWTException) {
            return response()->json([
                'data' => null,
                'message' => 'Token cannot be refreshed',
                'errors' => null,
            ], 401);
        }
    }
}
