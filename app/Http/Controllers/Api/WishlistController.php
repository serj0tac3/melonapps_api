<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate; 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlist = $request->user()
            ->wishlists()
            // 1. FIX: Cambiamos cardSet a set (asegúrate de que en el modelo CardTemplate la función se llama set())
            ->with(['set:id,name,code']) 
            // 2. FIX: Usamos latest() genérico para que no rompa el SQL buscando la tabla pivote
            ->latest() 
            ->get();

        return response()->json(['data' => $wishlist]);
    }

    public function store(Request $request): JsonResponse
    {
        // ✅ DEJAMOS TU CÓDIGO INTACTO PORQUE FUNCIONA PERFECTO
        $request->validate([
            'card_template_id' => ['required', 'integer', 'exists:card_templates,id'],
        ]);

        $cardId = $request->integer('card_template_id');
        $user   = $request->user();

        if (!$user->wishlists()->where('card_template_id', $cardId)->exists()) {
            $user->wishlists()->attach($cardId);
        }

        return response()->json(['message' => 'Carta añadida a la wishlist.'], 201);
    }

    public function destroy(Request $request, int $cardId): JsonResponse
    {
        $request->user()->wishlists()->detach($cardId);

        return response()->json(['message' => 'Carta eliminada de la wishlist.']);
    }
}