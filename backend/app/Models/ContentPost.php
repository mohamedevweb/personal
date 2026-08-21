<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'media_urls' => 'array',
            'analysis_translations' => 'array',
            'safety_reasons' => 'array',
            'last_fetched_at' => 'datetime',
            'metrics_updated_at' => 'datetime',
            'safety_checked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function savedContent(): HasMany
    {
        return $this->hasMany(SavedContent::class);
    }

    public function remixes(): HasMany
    {
        return $this->hasMany(Remix::class, 'source_content_id');
    }
}
