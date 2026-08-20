<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorRelationship extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'relevance_score' => 'float',
            'discovered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function sourceCreator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'source_creator_id');
    }

    public function relatedCreator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'related_creator_id');
    }
}
