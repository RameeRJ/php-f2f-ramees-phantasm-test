<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Product routes
Route::prefix('products')->group(function () {
    // Public routes - anyone can view products
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    
    // Protected routes - requires authentication
    Route::middleware('auth:api')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
    });
});

// Cart routes (all protected - require JWT authentication)
Route::prefix('cart')->middleware('auth:api')->group(function () {
    // View cart
    Route::get('/', [CartController::class, 'getCart']);
    
    // Get cart count (useful for navbar badge)
    Route::get('/count', [CartController::class, 'getCartCount']);
    
    // Add product to cart
    Route::post('/add', [CartController::class, 'addToCart']);
    
    // Update cart item quantity
    Route::put('/items/{itemId}', [CartController::class, 'updateCartItem']);
    Route::patch('/items/{itemId}', [CartController::class, 'updateCartItem']);
    
    // Remove single item from cart
    Route::delete('/items/{itemId}', [CartController::class, 'removeCartItem']);
    
    // Clear entire cart
    Route::delete('/clear', [CartController::class, 'clearCart']);
});