<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remix extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'generated_content' => 'array',
            'last_copied_at' => 'datetime',
        ];
    }

    public function sourceContent(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'source_content_id');
    }

    public function lifeMoment(): BelongsTo
    {
        return $this->belongsTo(LifeMoment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
