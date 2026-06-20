<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardSetTranslation extends Model
{
    protected $fillable = ['card_set_id', 'region_id', 'name', 'release_date', 'image_url'];

    public function cardSet()
    {
        return $this->belongsTo(CardSet::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}