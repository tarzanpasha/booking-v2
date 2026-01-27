<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasResourceConfig;

class Resource extends Model
{
    use HasFactory, HasResourceConfig;

    protected $fillable = [
        'company_id',
        'timetable_id',
        'resource_type_id',
        'options',
        'payload',
        'resource_config',
    ];

    protected $casts = [
        'options' => 'array',
        'payload' => 'array',
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

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
