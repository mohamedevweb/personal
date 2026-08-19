<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The user's avatar is derived from their connected Instagram account, so it
     * updates the moment they connect and clears when they disconnect. No column
     * to keep in sync.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->instagramAccount?->profile_picture_url);
    }

    public function instagramAccount(): HasOne
    {
        return $this->hasOne(InstagramAccount::class);
    }

    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class);
    }

    public function moments(): HasMany
    {
        return $this->hasMany(LifeMoment::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(ContentOpportunity::class);
    }

    public function remixes(): HasMany
    {
        return $this->hasMany(Remix::class);
    }

    public function savedContent(): HasMany
    {
        return $this->hasMany(SavedContent::class);
    }

    public function dismissedContent(): HasMany
    {
        return $this->hasMany(DismissedContent::class);
    }
}
