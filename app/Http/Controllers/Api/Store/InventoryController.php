<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StoreInventory;
use Illuminate\Support\Facades\Auth;
class InventoryController extends Controller
{
    public function index()
{
    $store = Auth::guard('store_api')->user();

    $inventories = StoreInventory::query()
        ->where('store_id', $store->id)
        ->with([
            'storeProduct.supplierProduct.supplier',
            'storeProduct.supplierProduct.product',
        ])
        ->get();

    return response()->json([
        'data' => $inventories,
        'message' => 'Success',
        'errors' => null,
    ]);
}
}
