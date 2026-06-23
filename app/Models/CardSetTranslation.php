<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardSetTranslation extends Model
{
    protected $fillable = ['card_set_id', 'region_id', 'name', 'short_name', 'release_date', 'image_url'];

    private const KNOWN_PREFIXES = [
        'BOOSTER PACK -',
        'STARTER DECK -',
        'EXTRA BOOSTER -',
        'PREMIUM BOOSTER -',
        'MEMORIAL COLLECTION -',
        'SPECIAL GOODS SET -',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (CardSetTranslation $translation) {
            // Genera short_name automáticamente si no se proporcionó explícitamente
            if (!empty($translation->name) && empty($translation->short_name)) {
                $translation->short_name = self::extractShortName($translation->name);
            }
        });
    }

    private static function extractShortName(string $fullName): string
    {
        foreach (self::KNOWN_PREFIXES as $prefix) {
            if (str_starts_with(strtoupper($fullName), strtoupper($prefix))) {
                return trim(substr($fullName, strlen($prefix)));
            }
        }
        return $fullName;
    }

    public function cardSet()
    {
        return $this->belongsTo(CardSet::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}