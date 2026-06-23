<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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
        return $this->belongsToMany(CardTemplate::class, 'card_user')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class);
    }

    // ✅ Card → CardTemplate
    public function wishlists(): BelongsToMany
    {
        return $this->belongsToMany(CardTemplate::class, 'wishlists')
                    ->withTimestamps();
    }
}