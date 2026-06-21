<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // ✅ Import que faltaba
use Illuminate\Database\Eloquent\Relations\HasMany;        // ✅ Para userCards()
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Card;                                        // ✅ Import que faltaba
use App\Models\UserCard;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function collection(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_user')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class);
    }

    public function wishlists(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'wishlists')
                    ->withTimestamps();
    }
}