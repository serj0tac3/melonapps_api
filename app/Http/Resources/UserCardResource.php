<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Los campos estrictos de tu modelo UserCard.php
            'id'       => $this->id,
            'quantity' => $this->quantity,
            'is_foil'  => (bool) $this->is_foil,
            
            // Reutilizamos tu CardResource exacto pasándole la relación cargada
            'card_template' => new CardResource($this->whenLoaded('cardTemplate')),
        ];
    }
}