<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardSet;
use App\Models\CardTemplate;
use App\Http\Resources\SetResource;
use App\Http\Resources\CardResource;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class SetController extends Controller
{
    public function index()
    {
        $query = QueryBuilder::for(CardSet::class)
            ->with('translations')
            ->allowedFilters(
                AllowedFilter::exact('game_id'), // 🚀 Permitimos filtrar por el ID del juego (las pestañas)
                AllowedFilter::exact('family'),
                AllowedFilter::partial('code')
            );

        // Contamos el total de cartas de la expansión
        $query->withCount('templates');

        // Si hay un usuario logueado, cruzamos los datos con su colección (sin Lazy Loading)
        if ($userId = auth('sanctum')->id()) {
            $query->withCount(['templates as owned_count' => function ($q) use ($userId) {
                $q->whereHas('userCards', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)->where('quantity', '>', 0);
                });
            }]);
        }

        // Ordenamos por fecha de salida (las más nuevas arriba, como en tu diseño)
        $sets = $query->orderByDesc('id')->get();

        return SetResource::collection($sets);
    }

    public function showCards($code)
    {
        // Buscamos las plantillas de cartas asociadas al código del set y resolvemos los filtros JSON
        $cards = QueryBuilder::for(CardTemplate::class)
            ->with('translations')
            ->whereHas('cardSet', function ($query) use ($code) {
                $query->where('code', $code);
            })
            ->allowedFilters(
                // Spatie mapea filtros directos a claves internas del JSON de tu BD
                AllowedFilter::exact('color', 'attributes->color'),
                AllowedFilter::exact('category', 'attributes->category'),
                AllowedFilter::exact('cost', 'attributes->cost'),
                AllowedFilter::exact('rarity', 'attributes->rarity')
            )
            ->paginate(50);

        return CardResource::collection($cards);
    }
}