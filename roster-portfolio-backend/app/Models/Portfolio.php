<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    protected $fillable = [
        'url', 'name', 'title', 'bio', 'email', 'location', 'website'
    ];

    public function employers(): HasMany
    {
        return $this->hasMany(Employer::class);
    }
}
