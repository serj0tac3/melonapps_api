<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\SetController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| 🟢 RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/
// Rutas Públicas de Autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auth-status', function (Request $request) {
    // Intentamos obtener el usuario con el guard de sanctum. Si no hay, devuelve null pacíficamente.
    return response()->json([
        'user' => $request->user('sanctum') 
    ]);
});

Route::get('/sets', [SetController::class, 'index']);
Route::get('/sets/{code}/cards', [SetController::class, 'showCards']);

Route::get('/cards', [CardController::class, 'index']);
// Ruta para obtener la estructura de filtros dinámicos del catálogo
Route::get('/cards/filters', [CardController::class, 'filters']);
Route::get('/cards/{unique_id}', [CardController::class, 'show']);

/*
|--------------------------------------------------------------------------
| 🔴 RUTAS PRIVADAS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/collection', [CollectionController::class, 'index']);
    Route::post('/collection', [CollectionController::class, 'store']);
    Route::get('vault/sets-summary', [CollectionController::class, 'setsSummary']);
    Route::patch('collection/{userCardId}/quantity', [CollectionController::class, 'updateQuantity']);
    Route::delete('collection/{userCardId}', [CollectionController::class, 'destroy']);

    Route::get('/wishlist', [CollectionController::class, 'getWishlist']);
    Route::post('/wishlist', [CollectionController::class, 'addToWishlist']);
    Route::delete('/wishlist/{cardId}', [CollectionController::class, 'removeFromWishlist']);
    
});