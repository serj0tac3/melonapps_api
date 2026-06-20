<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Obtenemos el ID de la región que pide el usuario (por defecto usaremos el 1, que suele ser Global/Inglés)
        $regionId = $request->get('region_id', 1);

        // 2. Buscamos la traducción que coincida con ese region_id exacto
        $translation = $this->relationLoaded('translations') 
            ? $this->translations->firstWhere('region_id', $regionId) 
            : null;

        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'family'       => $this->family,
            'name'         => $translation?->name ?? 'Unknown Set',
            'release_date' => $translation?->release_date ?? null,
            'image_url'    => $translation?->image_url ? asset($translation->image_url) : null,
        ];
    }
}