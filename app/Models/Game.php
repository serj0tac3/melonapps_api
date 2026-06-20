<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = ['name', 'slug'];

    public function sets()
    {
        return $this->hasMany(CardSet::class);
    }
}