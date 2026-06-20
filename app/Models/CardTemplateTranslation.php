<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardTemplateTranslation extends Model
{
    protected $fillable = ['card_template_id', 'region_id', 'name', 'effect', 'image_url'];

    public function cardTemplate()
    {
        return $this->belongsTo(CardTemplate::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}