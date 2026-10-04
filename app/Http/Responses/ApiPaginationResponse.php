<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiPaginationResponse
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function make(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        Request $request,
    ): JsonResponse {
        return response()->json([
            'data' => $resourceClass::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
            ],
        ]);
    }
}
