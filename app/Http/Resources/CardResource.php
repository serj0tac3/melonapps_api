<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Obtenemos el ID de la región (por defecto 1 = Inglés/Global)
        $regionId = $request->get('region_id', 1);

        // 2. Buscamos la traducción vinculada a esa región
        $translation = $this->relationLoaded('translations') 
            ? $this->translations->firstWhere('region_id', $regionId) 
            : null;

        // Extraemos los atributos dinámicos del JSON agnóstico de forma segura
        $attrs = $this->attributes ?? [];

        // Calculamos cuántas copias tiene de esta variante
        $ownedCopies = 0;
        if ($this->relationLoaded('userCards')) {
            $ownedCopies = $this->userCards->sum('quantity');
        }

        // Extraemos el ID del pivote por si el usuario quiere borrarla
        $userCard = $this->relationLoaded('userCards') ? $this->userCards->first() : null;
        $ownedCopies = $userCard ? $this->userCards->sum('quantity') : 0;

        return [
            'id'          => $this->id,
            'unique_id'   => $this->unique_id,
            'card_number' => $this->card_number,
            'name'        => $translation?->name ?? 'Unknown Name',
            'effect'      => $translation?->effect ?? null,
            'image_url'   => $translation?->image_url ? asset($translation->image_url) : null,
            
            'cost'        => $attrs['cost'] ?? '-',
            'power'       => $attrs['power'] ?? '-',
            'counter'     => $attrs['counter'] ?? '-', // 🚀 Nuevo para la vista detalle
            'feature'     => $attrs['feature'] ?? '-', // 🚀 Nuevo para la vista detalle
            'life'        => $attrs['life'] ?? '-',
            'color'       => $attrs['color'] ?? 'N/A',
            'category'    => $attrs['category'] ?? 'N/A',
            'rarity'      => $attrs['rarity'] ?? 'N/A',

            'is_wishlisted' => (bool) $this->is_wishlisted,
            'owned_copies'=> $ownedCopies,
            'user_card_id'=> $userCard ? $userCard->id : null, // 🚀 ID del pivote para poder borrar
            'variants'    => $this->relationLoaded('variants') ? CardResource::collection($this->variants) : [],
        ];
    }
}