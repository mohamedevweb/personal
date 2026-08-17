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
        ];
    }
}
