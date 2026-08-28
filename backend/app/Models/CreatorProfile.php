<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'tone' => 'array',
            'current_projects' => 'array',
            'goals' => 'array',
            'content_strengths' => 'array',
            'discovery_hashtags' => 'array',
            'creator_dna' => 'array',
            'discovery_queries' => 'array',
            'dna_analyzed_at' => 'datetime',
            'analysis_started_at' => 'datetime',
            'analysis_stage_started_at' => 'datetime',
            'analysis_completed_at' => 'datetime',
            'analysis_timings' => 'array',
            'media_enrichment_started_at' => 'datetime',
            'media_enrichment_completed_at' => 'datetime',
            'discovery_refreshed_at' => 'datetime',
            'market_confidence' => 'float',
        ];
    }
}
