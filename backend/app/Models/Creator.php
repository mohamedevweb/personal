<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'followers' => 'integer',
            'average_views' => 'integer',
            'average_likes' => 'integer',
            'avg_engagement_rate' => 'float',
            'last_measured_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPost::class);
    }
}
