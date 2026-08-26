<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifeMoment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'happened_at' => 'date',
            'upcoming_at' => 'date',
        ];
    }
}
