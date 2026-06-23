<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardSet extends Model
{
    protected $fillable = ['game_id', 'code', 'family', 'total_cards'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function templates()
    {
        return $this->hasMany(CardTemplate::class);
    }

    public function translations()
    {
        return $this->hasMany(CardSetTranslation::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Normaliza el código automáticamente antes de cualquier INSERT o UPDATE
        // Esto protege contra re-imports de scrapers que traigan "OP-15" de nuevo
        static::saving(function (CardSet $set) {
            if (!empty($set->code)) {
                $set->code = str_replace('-', '', $set->code);
            }
        });
    }
}