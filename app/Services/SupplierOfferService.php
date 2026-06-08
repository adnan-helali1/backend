<?php

namespace App\Services;

use App\Models\SupplierOffer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class SupplierOfferService
{
    public function getOffers(int $storeId, array $filters = []): array
    {
        $cacheKey = "store:offers:{$storeId}:" . md5(json_encode($filters));

        return Cache::remember($cacheKey, 120, function () use ($filters) {
            $perPage = (int) ($filters['per_page'] ?? 15);

            $query = SupplierOffer::query()
                ->with([
                    'supplierProduct.supplier',
                    'supplierProduct.product.category'
                ])
                ->where('status', 'available');

            // Filter by search (product name or supplier name)
            if (isset($filters['search']) && !empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('supplierProduct.product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('supplierProduct.supplier', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            // Filter by category
            if (isset($filters['category']) && !empty($filters['category'])) {
                $query->whereHas('supplierProduct.product', function ($q) use ($filters) {
                    $q->where('category_id', $filters['category']);
                });
            }

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Get total count before pagination
            $totalOffers = SupplierOffer::query()->count();
            $availableOffers = SupplierOffer::query()
                ->where('status', 'available')
                ->count();

            // Paginate
            $offers = $query->orderByDesc('id')->paginate($perPage);

            return [
                'data' => $offers->items(),
                'stats' => [
                    'total_offers' => $totalOffers,
                    'available_offers' => $availableOffers,
                ],
                'pagination' => [
                    'per_page' => $offers->perPage(),
                    'current_page' => $offers->currentPage(),
                    'total' => $offers->total(),
                    'last_page' => $offers->lastPage(),
                ],
            ];
        });
    }
}
