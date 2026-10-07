<?php

use App\Http\Controllers\Api\V1\AdminWarungController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrentWarungController;
use App\Http\Controllers\Api\V1\KategoriMenuController;
use App\Http\Controllers\Api\V1\LaporanController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PembelianController;
use App\Http\Controllers\Api\V1\PenjualanController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureValidJsonApiBody;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware([EnsureValidJsonApiBody::class, 'throttle:login'])
        ->name('auth.login');

    Route::post('auth/register', [RegistrationController::class, 'store'])
        ->middleware([EnsureValidJsonApiBody::class, 'throttle:register'])
        ->name('auth.register');

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureValidJsonApiBody::class])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('admin/warungs')->name('admin.warungs.')->group(function (): void {
            Route::get('/', [AdminWarungController::class, 'index'])->name('index');
            Route::post('/', [AdminWarungController::class, 'store'])->name('store');
            Route::get('{id}', [AdminWarungController::class, 'show'])->where('id', '[1-9][0-9]*')->name('show');
            Route::patch('{id}', [AdminWarungController::class, 'update'])->where('id', '[1-9][0-9]*')->name('update');
            Route::post('{id}/persetujuan', [AdminWarungController::class, 'approve'])->where('id', '[1-9][0-9]*')->name('approve');
            Route::post('{id}/langganan/perpanjangan', [AdminWarungController::class, 'extendSubscription'])->where('id', '[1-9][0-9]*')->name('subscription.extend');
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
        Route::patch('penjualans/{id}', [PenjualanController::class, 'update'])->where('id', '[1-9][0-9]*')->name('penjualans.update');
        Route::post('penjualans/{id}/pembayaran', [PenjualanController::class, 'pay'])->where('id', '[1-9][0-9]*')->name('penjualans.pay');
        Route::post('penjualans/{id}/pembatalan', [PenjualanController::class, 'cancel'])->where('id', '[1-9][0-9]*')->name('penjualans.cancel');
        Route::post('penjualans/{id}/retur', [PenjualanController::class, 'storeReturn'])->where('id', '[1-9][0-9]*')->name('penjualans.return');

        Route::get('pembelians', [PembelianController::class, 'index'])->name('pembelians.index');
        Route::post('pembelians', [PembelianController::class, 'store'])->name('pembelians.store');
        Route::get('pembelians/{id}', [PembelianController::class, 'show'])->where('id', '[1-9][0-9]*')->name('pembelians.show');
        Route::patch('pembelians/{id}', [PembelianController::class, 'update'])->where('id', '[1-9][0-9]*')->name('pembelians.update');
        Route::post('pembelians/{id}/pembatalan', [PembelianController::class, 'cancel'])->where('id', '[1-9][0-9]*')->name('pembelians.cancel');

        Route::get('laporan/penjualan', [LaporanController::class, 'penjualan'])->name('laporan.penjualan');
        Route::get('laporan/pembelian', [LaporanController::class, 'pembelian'])->name('laporan.pembelian');
    });
});
