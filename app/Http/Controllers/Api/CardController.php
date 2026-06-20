<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use App\Http\Resources\CardResource;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Configuramos los filtros con Spatie Query Builder sobre el modelo base
        $baseQuery = QueryBuilder::for(CardTemplate::class)
            ->allowedFilters(
                AllowedFilter::partial('card_number'),
                AllowedFilter::exact('color', 'attributes->color'),
                AllowedFilter::exact('category', 'attributes->category'),
                AllowedFilter::exact('cost', 'attributes->cost'),
                AllowedFilter::exact('rarity', 'attributes->rarity'),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) { 
                        $q->where('card_number', 'LIKE', "%{$value}%")
                          ->orWhereHas('translations', function ($t) use ($value) {
                              $t->where('name', 'LIKE', "%{$value}%");
                          });
                    });
                })
            );

        // 🚀 FASE 1: Paginamos estrictamente las FAMILIAS (card_number) únicas
        // Clonamos la query base, colapsamos las variantes por número de carta y paginamos 50 familias
        $paginator = (clone $baseQuery)
            ->select('card_number')
            ->distinct()
            ->reorder()
            ->paginate(50);

        // Extraemos la lista de los 50 card_number que han entrado en esta página exacta
        $cardNumbersOnPage = $paginator->pluck('card_number');

        // 🚀 FASE 2: Hidratamos la página trayendo las variantes completas de esas 50 familias
        // Volvemos a clonar la query base para aplicar los mismos filtros y traemos todo lo que coincida
        $variantsQuery = (clone $baseQuery)
            ->with('translations') // Cargamos las traducciones de forma eficiente solo para lo que se va a pintar
            ->whereIn('card_number', $cardNumbersOnPage);

        // MAGIA: Si el usuario está autenticado, inyectamos sus copias de la Bóveda en esta petición
        // MAGIA: Si el usuario está autenticado, inyectamos sus copias de la Bóveda y su Wishlist en esta petición
        if ($user = auth('sanctum')->user()) {
            $variantsQuery->with(['userCards' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            // 🚀 NUEVO: Comprobamos si la tiene en deseados
            ->withExists(['wishlists as is_wishlisted' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }

        // Ejecutamos la consulta final de hidratación
        $allVariants = $variantsQuery->get();

        // 🚀 FASE 3: Agrupación estructural en memoria para el Frontend
        $groupedCollection = $allVariants
            ->groupBy('card_number')
            ->map(function ($variants) {
                $mainCard = clone $variants->first(); // La primera variante se convierte en la "portada" de la tarjeta
                $mainCard->setRelation('variants', $variants->values()); // Le adjuntamos todas sus variantes reales
                return $mainCard;
            })
            ->values();

        // 🚀 FASE 4: Reinyectamos la colección en el paginador original
        // Así Angular sigue recibiendo el formato exacto de paginación (total, current_page...) sin romper nada
        $paginator->setCollection($groupedCollection);

        return CardResource::collection($paginator);
    }

    public function show($unique_id)
    {
        $user = auth('sanctum')->user();

        // 1. Encontramos la carta base para saber su número de serie
        $requestedCard = CardTemplate::where('unique_id', $unique_id)->firstOrFail();

        // 2. Buscamos todas las variantes asociadas a ese número
        $query = CardTemplate::with('translations')->where('card_number', $requestedCard->card_number);
        
        // 3. Inyectamos los datos del usuario logueado (Bóveda y Wishlist)
        if ($user) {
            $query->with(['userCards' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            // 🚀 NUEVO: Comprobamos si la tiene en deseados
            ->withExists(['wishlists as is_wishlisted' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }
        
        $variants = $query->get();
        
        // 4. Transformamos la respuesta: La portada es la que el usuario pidió por URL
        $mainCard = clone $variants->firstWhere('unique_id', $unique_id);
        $mainCard->setRelation('variants', $variants->values());

        return new CardResource($mainCard);
    }
    /**
     * Obtiene la configuración de filtros dinámicos disponibles para el catálogo.
     */
    public function filters(): \Illuminate\Http\JsonResponse
    {
        $filtersConfig = [
            'colors' => [
                ['value' => 'Red', 'label' => 'Rojo'],
                ['value' => 'Green', 'label' => 'Verde'],
                ['value' => 'Blue', 'label' => 'Azul'],
                ['value' => 'Purple', 'label' => 'Morado'],
                ['value' => 'Black', 'label' => 'Negro'],
                ['value' => 'Yellow', 'label' => 'Amarillo']
            ],
            'categories' => [
                ['value' => 'LEADER', 'label' => 'Líder'],
                ['value' => 'CHARACTER', 'label' => 'Personaje'],
                ['value' => 'EVENT', 'label' => 'Evento'],
                ['value' => 'STAGE', 'label' => 'Escenario']
            ],
            'rarities' => [
                ['value' => 'L', 'label' => 'Leader (L)'],
                ['value' => 'C', 'label' => 'Common (C)'],
                ['value' => 'UC', 'label' => 'Uncommon (UC)'],
                ['value' => 'R', 'label' => 'Rare (R)'],
                ['value' => 'SR', 'label' => 'Super Rare (SR)'],
                ['value' => 'SEC', 'label' => 'Secret (SEC)']
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $filtersConfig
        ]);
    }
}