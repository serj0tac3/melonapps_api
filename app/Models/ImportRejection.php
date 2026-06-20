<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRejection extends Model
{
    protected $fillable = [
        'game_slug', 'card_number', 'attempted_set_code', 
        'error_details', 'raw_data', 'is_resolved'
    ];

    protected $casts = [
        'raw_data' => 'array',
        'is_resolved' => 'boolean',
    ];
}