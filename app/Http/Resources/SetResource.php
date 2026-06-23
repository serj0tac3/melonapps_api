<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $regionId = $request->get('region_id', 1);

        $translation = $this->relationLoaded('translations') 
            ? $this->translations->firstWhere('region_id', $regionId) 
            : null;

        // Calculamos el porcentaje de progreso de forma segura
        $totalCards = $this->templates_count ?? 0;
        $ownedCards = $this->owned_count ?? 0;
        $percentage = $totalCards > 0 ? round(($ownedCards / $totalCards) * 100) : 0;

        return [
            'id'           => $this->id,
            'game_id'      => $this->game_id,
            'code'         => $this->code,
            'family'       => $this->family,
            'name'         => $translation?->name ?? 'Unknown Set',
            'short_name'   => $translation?->short_name ?? $translation?->name ?? 'Unknown Set',
            'release_date' => $translation?->release_date ?? null,
            'image_url'    => $translation?->image_url ? asset($translation->image_url) : null,
            'total_cards'  => $totalCards,
            'progress'     => $percentage,
        ];
    }
}