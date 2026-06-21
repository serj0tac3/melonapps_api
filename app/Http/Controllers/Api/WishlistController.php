<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlist = $request->user()
            ->wishlists()
            ->with(['cardSet:id,name,code'])
            ->latest('wishlists.created_at')
            ->get();

        return response()->json(['data' => $wishlist]);
    }

    // WishlistController.php — store() ya recibe cardId por URL
    public function store(Request $request, int $cardId): JsonResponse
    {
        $card = Card::findOrFail($cardId);
        $user = $request->user();

        if (!$user->wishlists()->where('card_id', $cardId)->exists()) {
            $user->wishlists()->attach($cardId);
        }

        return response()->json(['message' => 'Carta añadida a la wishlist.'], 201);
    }

    public function destroy(Request $request, int $cardId): JsonResponse
    {
        $request->user()->wishlists()->detach($cardId);

        return response()->json([
            'message' => 'Carta eliminada de la wishlist.',
        ]);
    }
}