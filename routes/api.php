<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SetController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\WishlistController;

/*
|--------------------------------------------------------------------------
| 🟢 RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

// ── Autenticación ────────────────────────────────────────────────────────
// Sin throttle explícito aquí porque LoginRequest ya lo gestiona internamente
// con RateLimiter por email+IP, que es más granular que throttle middleware.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ── Catálogo público ─────────────────────────────────────────────────────
Route::get('/sets',                  [SetController::class, 'index']);
Route::get('/sets/{code}/cards',     [SetController::class, 'showCards']);

// ✅ /cards/filters ANTES de /cards/{unique_id}
// Si lo declaras después, Laravel captura 'filters' como unique_id
// y show() recibe un ID inválido en lugar de ejecutar filters()
Route::get('/cards/filters',         [CardController::class, 'filters']);
Route::get('/cards',                 [CardController::class, 'index']);
Route::get('/cards/{unique_id}', [CardController::class, 'show'])
    ->where('unique_id', '[A-Za-z0-9_\-]+');

/*
|--------------------------------------------------------------------------
| 🔴 RUTAS PRIVADAS
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ── Sesión ───────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);

    // ✅ /user con respuesta controlada — no expone el modelo completo
    Route::get('/user', function (Request $request): \Illuminate\Http\JsonResponse {
        $user = $request->user();
        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    });

    // ── Colección ────────────────────────────────────────────────────────
    Route::get('/collection',              [CollectionController::class, 'index']);
    Route::post('/collection',             [CollectionController::class, 'store']);
    Route::patch('/collection/{id}',       [CollectionController::class, 'update']); // ✅ sin /quantity en la URL
    Route::delete('/collection/{id}',      [CollectionController::class, 'destroy']);

    // ── Bóveda / Dashboard ───────────────────────────────────────────────
    Route::get('/vault/sets-summary',      [CollectionController::class, 'setsSummary']);

    // ── Wishlist ─────────────────────────────────────────────────────────
    // ✅ Separado en su propio controlador — responsabilidad única
    Route::get('/wishlist',                 [WishlistController::class, 'index']);
    Route::post('/wishlist',                [WishlistController::class, 'store']);
    Route::delete('/wishlist/{cardId}',     [WishlistController::class, 'destroy']);

});