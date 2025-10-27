<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectProgress extends Model
{
    protected $fillable = [
        'name',
        'percentage',
        'color',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'percentage' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean'
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'progress_id');
    }
}
