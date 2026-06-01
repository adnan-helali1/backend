<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function show()
    {
        $store = Auth::guard('store_api')->user();

        return response()->json([
            'data' => $store,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function update(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:stores,email,'.$store->id],
            'address' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $store->update($validator->validated());

        return response()->json([
            'data' => $store->fresh(),
            'message' => 'Updated',
            'errors' => null,
        ]);
    }
}
