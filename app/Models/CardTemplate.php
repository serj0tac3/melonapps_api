<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    protected $fillable = ['card_set_id', 'unique_id', 'card_number', 'attributes'];

    // 🚀 MAGIA: Laravel convierte automáticamente el JSON en Array
    protected $casts = [
        'attributes' => 'array',
    ];

    public function cardSet()
    {
        return $this->belongsTo(CardSet::class);
    }

    public function translations()
    {
        return $this->hasMany(CardTemplateTranslation::class);
    }

    /**
     * RELACIÓN: Colecciones de usuarios que contienen esta plantilla de carta.
     */
    public function userCards()
    {
        return $this->hasMany(UserCard::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
}