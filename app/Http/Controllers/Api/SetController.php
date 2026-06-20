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
        // Pasamos los filtros de forma variádica (separados por comas) para evitar el TypeError
        $sets = QueryBuilder::for(CardSet::class)
            ->with('translations') // Eager loading esencial para evitar lentitud
            ->allowedFilters(
                AllowedFilter::exact('family'),
                AllowedFilter::partial('code')
            )
            ->get();

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