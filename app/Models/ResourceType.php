<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'timetable_id',
        'type',
        'name',
        'description',
        'options',
        'resource_config',
    ];

    protected $casts = [
        'options' => 'array',
        'resource_config' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
