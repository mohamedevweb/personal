<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    protected $guarded = [];

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPost::class);
    }
}
