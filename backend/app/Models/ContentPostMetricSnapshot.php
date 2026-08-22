<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPostMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'views' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'views_delta' => 'integer',
            'elapsed_hours' => 'float',
            'views_velocity' => 'float',
            'views_acceleration' => 'float',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }
}
