<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Content\GetLayoutContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/content')->middleware(['throttle:content-api'])->group(function (): void {
    Route::get('layout', GetLayoutContentController::class)->name('api.v1.content.layout');
});

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/tokens.php';
});
