<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
            'baseline_engagement' => 'integer',
            'niche_topics' => 'array',
            'metadata' => 'array',
            'performance_baselines' => 'array',
            'safety_reasons' => 'array',
            'is_catalog_seed' => 'boolean',
            'discovered_at' => 'datetime',
            'last_fetched_at' => 'datetime',
            'metrics_updated_at' => 'datetime',
            'last_measured_at' => 'datetime',
            'safety_checked_at' => 'datetime',
            'last_scraped_at' => 'datetime',
            'next_scrape_at' => 'datetime',
            'last_post_at' => 'datetime',
            'scrape_priority' => 'float',
            'scrape_failures' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function niches(): BelongsToMany
    {
        return $this->belongsToMany(Niche::class, 'creator_niches')
            ->withPivot(['relevance_score', 'source'])
            ->withTimestamps();
    }

    public function relatedCreators(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'creator_relationships',
            'source_creator_id',
            'related_creator_id',
        )->withPivot(['relationship_type', 'relevance_score', 'discovered_at', 'last_seen_at']);
    }

    public function inspiredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_creator_inspirations')
            ->withPivot('priority')
            ->withTimestamps();
    }
}
