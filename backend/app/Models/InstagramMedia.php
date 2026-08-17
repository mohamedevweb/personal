<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramMedia extends Model
{
    protected $table = 'instagram_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class);
    }
}
