<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
            'performance_ratio' => 'float',
            'engagement_rate' => 'float',
            'outlier_score' => 'float',
            'measured_at' => 'datetime',
            'shares' => 'integer',
            'metadata' => 'array',
            'last_fetched_at' => 'datetime',
            'metrics_updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }
}
