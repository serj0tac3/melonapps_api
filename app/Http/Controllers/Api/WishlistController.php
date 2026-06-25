<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate; 
use App\Http\Resources\CardResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlist = $user->wishlists()
            ->with([
                'cardSet:id,code,family',
                'translations', // 🚀 CRÍTICO: Para que el Resource monte la imagen y el nombre
                'userCards' => function ($q) use ($user) { // Para que el Resource calcule 'owned_copies'
                    $q->where('user_id', $user->id);
                }
            ])
            // Para que el Resource sepa que sí está en deseados y marque el corazón
            ->withExists(['wishlists as is_wishlisted' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->latest() 
            ->paginate(40);

        return CardResource::collection($wishlist);
    }

    public function store(Request $request): JsonResponse
    {
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