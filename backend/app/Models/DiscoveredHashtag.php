<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscoveredHashtag extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_scraped_at' => 'datetime',
        ];
    }
}
