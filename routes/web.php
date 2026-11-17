<?php

use App\Http\Controllers\Admin\UsageReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use App\Http\Middleware\VerifyPixelforgeSignature;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    // The starter kit sends users to /dashboard after login; the Generate page is our home.
    Route::redirect('dashboard', '/generate')->name('dashboard');

    Route::get('generate', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('generations.store');
    Route::get('history', [GenerationController::class, 'index'])->name('generations.index');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('credits.store');

    Route::get('admin/usage', UsageReportController::class)
        ->middleware('can:view-admin-report')
        ->name('admin.usage');
});

Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->middleware(VerifyPixelforgeSignature::class)
    ->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
