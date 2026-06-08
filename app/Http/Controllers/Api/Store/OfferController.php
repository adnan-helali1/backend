<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Services\SupplierOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends Controller
{
    public function __construct(private SupplierOfferService $offerService)
    {
    }

    public function index(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $filters = [
            'search' => $request->query('search'),
            'category' => $request->query('category'),
            'status' => $request->query('status', 'available'),
            'per_page' => (int) $request->query('per_page', 15),
        ];

        $result = $this->offerService->getOffers($store->id, $filters);

        return response()->json([
            'data' => $result['data'],
            'stats' => $result['stats'],
            'pagination' => $result['pagination'],
            'message' => 'Success',
            'errors' => null,
        ]);
    }
}
