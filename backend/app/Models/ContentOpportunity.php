<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentOpportunity extends Model
{
    protected $guarded = [];

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class);
    }

    public function lifeMoment(): BelongsTo
    {
        return $this->belongsTo(LifeMoment::class);
    }
}
