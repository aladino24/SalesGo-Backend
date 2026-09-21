<?php

use App\Http\Controllers\Web\InternalAppReleaseController;
use App\Http\Controllers\Web\InternalMasterDataController;
use App\Http\Controllers\Web\InternalMasterAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/internal/app-releases');

Route::prefix('internal/master-data')->name('internal.master-data.')->group(function (): void {
    Route::get('login', [InternalMasterAuthController::class, 'create'])->name('login');
    Route::post('login', [InternalMasterAuthController::class, 'store'])->name('login.store');
});

Route::middleware('internal.release.portal')
    ->prefix('internal/app-releases')
    ->name('internal.app-releases.')
    ->group(function (): void {
        Route::get('/', [InternalAppReleaseController::class, 'index'])->name('index');
        Route::post('/', [InternalAppReleaseController::class, 'store'])->name('store');
    });

Route::middleware('internal.master.portal')
    ->prefix('internal/master-data')
    ->name('internal.master-data.')
    ->group(function (): void {
        Route::get('/', [InternalMasterDataController::class, 'index'])->name('index');
        Route::post('logout', [InternalMasterAuthController::class, 'destroy'])->name('logout');
        Route::get('products/{id}/image', [InternalMasterDataController::class, 'productImage'])->name('product-image');
        Route::get('{entity}/{id}', [InternalMasterDataController::class, 'show'])->name('show');
        Route::post('{entity}', [InternalMasterDataController::class, 'store'])->name('store');
        Route::patch('{entity}/{id}/status', [InternalMasterDataController::class, 'toggle'])->name('toggle');
    });
