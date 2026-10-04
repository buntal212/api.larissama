<?php

namespace App\Http\Responses;

use App\Support\ApiPagination;
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
        int|string $requestedPage,
    ): JsonResponse {
        $normalizedPage = ApiPagination::normalizePage($requestedPage);
        $page = ApiPagination::exceedsPhpIntegerRange($normalizedPage)
            ? '__api_unquoted_integer_page_'.bin2hex(random_bytes(12)).'__'
            : (int) $normalizedPage;
        $payload = [
            'data' => $resourceClass::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'page' => $page,
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
            ],
        ];

        if (is_int($page)) {
            return response()->json($payload);
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encodedPlaceholder = json_encode($page, JSON_THROW_ON_ERROR);
        $json = str_replace('"page":'.$encodedPlaceholder, '"page":'.$normalizedPage, $json, $replacementCount);

        if ($replacementCount !== 1) {
            throw new \LogicException('Could not serialize the unbounded page integer.');
        }

        return JsonResponse::fromJsonString($json);
    }
}
