<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCard;
use Illuminate\Http\Request;
use App\Models\Wishlist;

class CollectionController extends Controller
{
    /**
     * Devuelve las cartas de la bóveda (SIN AGRUPAR, filtradas por set)
     */
    public function index(Request $request)
    {
        $userId = auth('sanctum')->id();
        $setId = $request->get('set_id');

        $query = \App\Models\UserCard::with(['cardTemplate.translations'])
            ->where('user_id', $userId)
            ->where('quantity', '>', 0);

        if ($setId) {
            $query->whereHas('cardTemplate', function ($q) use ($setId) {
                $q->where('card_set_id', $setId); // Ajusta la columna a tu BD
            });
        }

        // Ordenamos por número de carta y variante
        $userCards = $query->join('card_templates', 'user_cards.card_template_id', '=', 'card_templates.id')
                           ->orderBy('card_templates.card_number')
                           ->orderBy('card_templates.unique_id')
                           ->select('user_cards.*') // Evitamos colisión de IDs
                           ->paginate(40);

        // Devolvemos los datos aplanados para que Angular los consuma fácil
        $transformed = $userCards->through(function ($uc) {
            $t = $uc->cardTemplate->translations->first();
            $attrs = $uc->cardTemplate->attributes ?? [];
            return [
                'user_card_id' => $uc->id,
                'card_id'      => $uc->cardTemplate->id,
                'unique_id'    => $uc->cardTemplate->unique_id,
                'card_number'  => $uc->cardTemplate->card_number,
                'name'         => $t->name ?? 'Unknown',
                'image_url'    => $t->image_url ? asset($t->image_url) : null,
                'cost'         => $attrs['cost'] ?? '-', // El número naranja arriba a la izquierda
                'owned_copies' => $uc->quantity,
            ];
        });

        return response()->json($transformed);
    }

    // 2. AÑADIR O SUMAR UNA CARTA
    public function store(Request $request)
    {
        $request->validate([
            'card_template_id' => 'required|exists:card_templates,id',
            'is_foil'          => 'boolean'
        ]);

        // Busca si el usuario ya tiene esta carta (misma plantilla y mismo tipo de brillo)
        $userCard = UserCard::firstOrNew([
            'user_id'          => $request->user()->id,
            'card_template_id' => $request->card_template_id,
            'is_foil'          => $request->is_foil ?? false,
        ]);

        // Si ya existía, suma 1. Si es nueva, la cantidad empieza en 1.
        $userCard->exists ? $userCard->increment('quantity') : $userCard->save();

        return response()->json([
            'message' => 'Carta añadida a tu colección',
            'data'    => $userCard
        ]);
    }

    // 3. QUitar O RESTAR UNA CARTA
    public function destroy(Request $request, $id)
    {
        $userCard = UserCard::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($userCard->quantity > 1) {
            $userCard->decrement('quantity');
            $message = 'Cantidad reducida en 1';
        } else {
            $userCard->delete();
            $message = 'Carta eliminada de la colección';
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Devuelve el resumen de progreso por cada Set/Expansión (Solo los empezados)
     */
    public function setsSummary()
    {
        $userId = auth('sanctum')->id();

        // 🚀 MEJORA: Filtramos desde la Base de Datos para traer SOLO los sets donde el usuario tiene cartas
        $sets = \App\Models\CardSet::whereHas('templates.userCards', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('quantity', '>', 0);
            })
            ->withCount('templates')
            ->withCount(['templates as owned_count' => function ($query) use ($userId) {
                $query->whereHas('userCards', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)->where('quantity', '>', 0);
                });
            }])
            ->get()
            ->map(function ($set) {
                $total = $set->templates_count;
                $owned = $set->owned_count;
                $percentage = $total > 0 ? round(($owned / $total) * 100) : 0;

                return [
                    'id'         => $set->id,
                    'code'       => $set->code,
                    'name'       => $set->name ?? $set->family,
                    'total'      => $total,
                    'owned'      => $owned,
                    'percentage' => $percentage
                ];
            })->values(); // values() asegura que el array se reindexe limpio para Angular

        $globalDistinct = \App\Models\UserCard::where('user_id', $userId)->where('quantity', '>', 0)->count();
        $globalTotal = \App\Models\UserCard::where('user_id', $userId)->sum('quantity');

        $wishlistCount = class_exists('\App\Models\Wishlist') 
            ? \App\Models\Wishlist::where('user_id', $userId)->count() 
            : 0;

        return response()->json([
            'sets' => $sets,
            'stats' => [
                'distinct_cards' => $globalDistinct,
                'total_copies'   => (int) $globalTotal,
                'wishlist_count' => $wishlistCount
            ]
        ]);
    }

    /**
     * Obtener las cartas de la Wishlist del usuario autenticado
     */
    public function getWishlist(Request $request)
    {
        $userId = auth('sanctum')->id();
        $setId = $request->get('set_id');

        // Cargamos la relación de traducciones
        $query = \App\Models\Wishlist::with(['cardTemplate.translations'])
            ->where('user_id', $userId);

        // Filtrado por set desde Angular
        if ($setId) {
            $query->whereHas('cardTemplate', function ($q) use ($setId) {
                $q->where('card_set_id', $setId);
            });
        }

        // 🚀 CAMBIO 1: Usamos paginate en lugar de get()
        $wishlistItems = $query->paginate(40);

        // 🚀 CAMBIO 2: Usamos through en lugar de map para mantener los metadatos de paginación
        $transformed = $wishlistItems->through(function ($wish) {
            $t = $wish->cardTemplate->translations->first();
            $attrs = $wish->cardTemplate->attributes ?? [];
            return [
                'card_id'      => $wish->cardTemplate->id,
                'unique_id'    => $wish->cardTemplate->unique_id,
                'card_number'  => $wish->cardTemplate->card_number,
                'name'         => $t->name ?? 'Unknown',
                'image_url'    => $t->image_url ? asset($t->image_url) : null,
                'cost'         => $attrs['cost'] ?? '-',
            ];
        });

        // 🚀 CAMBIO 3: Devolvemos directamente $transformed (Laravel ya incluye el wrapper 'data')
        return response()->json($transformed);
    }

    /**
     * Añadir una carta a la Wishlist (Se usará desde el Catálogo)
     */
    public function addToWishlist(Request $request)
    {
        $request->validate([
            'card_template_id' => 'required|exists:card_templates,id',
        ]);

        $user = $request->user();

        $wishlistItem = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'card_template_id' => $request->card_template_id
        ]);

        return response()->json([
            'message' => 'Carta añadida a la wishlist con éxito',
            'data' => $wishlistItem
        ], 201);
    }

    /**
     * Eliminar una carta de la Wishlist
     */
    public function removeFromWishlist($cardTemplateId, Request $request)
    {
        $user = $request->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('card_template_id', $cardTemplateId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'La carta no estaba en tu wishlist'
            ], 404);
        }

        return response()->json([
            'message' => 'Carta eliminada de la wishlist'
        ]);
    }
}