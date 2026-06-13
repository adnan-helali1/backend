<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class StoreCatalogCollection extends ResourceCollection
{
    public static $wrap = null;

    public $collects = StoreCatalogResource::class;

    public function __construct(
        LengthAwarePaginator $resource,
        private readonly array $summary,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'summary' => [
                'total_products' => (int) $this->summary['total_products'],
                'active_products' => (int) $this->summary['active_products'],
                'total_profit' => round((float) $this->summary['total_profit'], 2),
            ],
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }
}
