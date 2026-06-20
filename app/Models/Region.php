<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['code', 'name'];

    public function setTranslations()
    {
        return $this->hasMany(CardSetTranslation::class);
    }

    public function templateTranslations()
    {
        return $this->hasMany(CardTemplateTranslation::class);
    }
}