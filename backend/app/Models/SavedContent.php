<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedContent extends Model
{
    protected $table = 'saved_content';

    protected $guarded = [];

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class);
    }
}
