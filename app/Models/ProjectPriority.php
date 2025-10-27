<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectPriority extends Model
{
    protected $fillable = [
        'name',
        'color',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean'
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'priority_id');
    }
}
