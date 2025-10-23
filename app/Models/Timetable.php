<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'schedule',
    ];

    protected $casts = [
        'schedule' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resourceTypes(): HasMany
    {
        return $this->hasMany(ResourceType::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
