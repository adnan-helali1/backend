<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Services\StoreDashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(private StoreDashboardService $dashboardService)
    {
    }

    public function show()
    {
        $store = Auth::guard('store_api')->user();

        $cacheKey = "store:dashboard:{$store->id}";
        $payload = Cache::remember($cacheKey, now()->addSeconds(120), function () use ($store) {
            return $this->dashboardService->build($store->id);
        });

        return response()->json($payload);
    }
}
