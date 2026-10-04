<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiErrorResponse
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'request_id' => (string) Str::uuid(),
        ], $status);
    }
}
