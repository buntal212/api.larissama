<?php

use App\Http\Controllers\Api\V1\AdminWarungController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrentWarungController;
use App\Http\Controllers\Api\V1\KategoriMenuController;
use App\Http\Controllers\Api\V1\LaporanController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PembelianController;
use App\Http\Controllers\Api\V1\PenjualanController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureValidJsonApiBody;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware([EnsureValidJsonApiBody::class, 'throttle:login'])
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureValidJsonApiBody::class])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('admin/warungs')->name('admin.warungs.')->group(function (): void {
            Route::get('/', [AdminWarungController::class, 'index'])->name('index');
            Route::post('/', [AdminWarungController::class, 'store'])->name('store');
            Route::get('{id}', [AdminWarungController::class, 'show'])->where('id', '[1-9][0-9]*')->name('show');
            Route::patch('{id}', [AdminWarungController::class, 'update'])->where('id', '[1-9][0-9]*')->name('update');
        });

        Route::get('warung', [CurrentWarungController::class, 'show'])->name('warung.current');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{id}', [UserController::class, 'show'])->where('id', '[1-9][0-9]*')->name('users.show');
        Route::patch('users/{id}', [UserController::class, 'update'])->where('id', '[1-9][0-9]*')->name('users.update');

        Route::get('kategori-menus', [KategoriMenuController::class, 'index'])->name('kategori-menus.index');
        Route::post('kategori-menus', [KategoriMenuController::class, 'store'])->name('kategori-menus.store');
        Route::get('kategori-menus/{id}', [KategoriMenuController::class, 'show'])->where('id', '[1-9][0-9]*')->name('kategori-menus.show');
        Route::patch('kategori-menus/{id}', [KategoriMenuController::class, 'update'])->where('id', '[1-9][0-9]*')->name('kategori-menus.update');

        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('menus/{id}', [MenuController::class, 'show'])->where('id', '[1-9][0-9]*')->name('menus.show');
        Route::patch('menus/{id}', [MenuController::class, 'update'])->where('id', '[1-9][0-9]*')->name('menus.update');

        Route::get('penjualans', [PenjualanController::class, 'index'])->name('penjualans.index');
        Route::post('penjualans', [PenjualanController::class, 'store'])->name('penjualans.store');
        Route::get('penjualans/{id}', [PenjualanController::class, 'show'])->where('id', '[1-9][0-9]*')->name('penjualans.show');

        Route::get('pembelians', [PembelianController::class, 'index'])->name('pembelians.index');
        Route::post('pembelians', [PembelianController::class, 'store'])->name('pembelians.store');
        Route::get('pembelians/{id}', [PembelianController::class, 'show'])->where('id', '[1-9][0-9]*')->name('pembelians.show');

        Route::get('laporan/penjualan', [LaporanController::class, 'penjualan'])->name('laporan.penjualan');
        Route::get('laporan/pembelian', [LaporanController::class, 'pembelian'])->name('laporan.pembelian');
    });
});
