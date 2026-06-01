<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
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

        $token = Auth::guard('admin_api')->attempt($credentials);
        if (! $token) {
            return response()->json([
                'data' => null,
                'message' => 'Invalid credentials',
                'errors' => null,
            ], 401);
        }

        $admin = Auth::guard('admin_api')->user();
        if ($admin?->status !== 'active') {
            Auth::guard('admin_api')->logout();

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
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function logout()
    {
        Auth::guard('admin_api')->logout();

        return response()->json([
            'data' => null,
            'message' => 'Logged out',
            'errors' => null,
        ]);
    }
}

