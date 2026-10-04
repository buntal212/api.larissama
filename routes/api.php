<?php

use App\Http\Controllers\Api\V1\AdminWarungController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrentWarungController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('admin/warungs')->name('admin.warungs.')->group(function (): void {
            Route::get('/', [AdminWarungController::class, 'index'])->name('index');
            Route::post('/', [AdminWarungController::class, 'store'])->name('store');
            Route::get('{id}', [AdminWarungController::class, 'show'])->whereNumber('id')->name('show');
            Route::patch('{id}', [AdminWarungController::class, 'update'])->whereNumber('id')->name('update');
        });

        Route::get('warung', [CurrentWarungController::class, 'show'])->name('warung.current');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('users.show');
        Route::patch('users/{id}', [UserController::class, 'update'])->whereNumber('id')->name('users.update');
    });
});
