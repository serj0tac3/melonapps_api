<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCard extends Model
{
    protected $fillable = [
        'user_id',
        'card_template_id',
        'quantity',
        'is_foil',
    ];

    // Relación: Esta carta pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: La plantilla original de la carta
    public function cardTemplate()
    {
        return $this->belongsTo(CardTemplate::class);
    }
}