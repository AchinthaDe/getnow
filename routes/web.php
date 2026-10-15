<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\DemoHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', DemoHomeController::class)->name('home');

Route::get('/shop', fn () => redirect('/'))->name('shop');
Route::get('/products/{slug}', fn () => redirect('/'))->name('product');
Route::get('/bag', fn () => redirect('/'))->name('cart');
Route::post('/bag', fn () => back())->name('cart.store');
Route::get('/saved', fn () => redirect('/'))->name('wishlist');
Route::post('/saved', fn () => back())->name('wishlist.toggle');
Route::get('/account', fn () => redirect('/'))->name('account');
Route::get('/help', fn () => redirect('/'))->name('help');
Route::get('/terms', fn () => redirect('/'))->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
