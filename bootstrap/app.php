<?php

use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiErrorResponse::make(
                    'VALIDATION_ERROR',
                    'Data belum valid.',
                    422,
                    $exception->errors(),
                );
            }

            if ($exception instanceof AuthenticationException) {
                return ApiErrorResponse::make(
                    'UNAUTHENTICATED',
                    'Login diperlukan atau kredensial/token tidak valid.',
                    401,
                );
            }

            if ($exception instanceof AuthorizationException) {
                return ApiErrorResponse::make(
                    'FORBIDDEN',
                    'Akses ditolak.',
                    403,
                );
            }

            if ($exception instanceof ModelNotFoundException) {
                return ApiErrorResponse::make(
                    'NOT_FOUND',
                    'Resource tidak ditemukan dalam akses yang tersedia.',
                    404,
                );
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    422 => 'VALIDATION_ERROR',
                    429 => 'RATE_LIMITED',
                    default => $status >= 500 ? 'INTERNAL_ERROR' : 'BAD_REQUEST',
                };
                $message = match ($code) {
                    'BAD_REQUEST' => $status === 400 ? 'JSON request tidak dapat dibaca.' : 'Request tidak dapat diproses.',
                    'UNAUTHENTICATED' => 'Login diperlukan atau kredensial/token tidak valid.',
                    'FORBIDDEN' => 'Akses ditolak.',
                    'NOT_FOUND' => 'Resource tidak ditemukan dalam akses yang tersedia.',
                    'CONFLICT' => 'Data bertentangan dengan keadaan saat ini.',
                    'VALIDATION_ERROR' => 'Data belum valid.',
                    'RATE_LIMITED' => 'Terlalu banyak request. Coba lagi nanti.',
                    default => 'Terjadi kesalahan pada server.',
                };

                return ApiErrorResponse::make($code, $message, $status);
            }

            return ApiErrorResponse::make(
                'INTERNAL_ERROR',
                'Terjadi kesalahan pada server.',
                500,
            );
        });
    })->create();
