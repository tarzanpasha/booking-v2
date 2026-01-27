<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'resource_id',
        'timetable_id',
        'is_group_booking',
        'start',
        'end',
        'status',
        'reason'
    ];

    protected $casts = [
        'is_group_booking' => 'boolean',
        'start' => 'datetime',
        'end' => 'datetime'
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): MorphToMany
    {
        return $this->morphToMany(
            User::class,
            'bookable',
            'bookables',
            'booking_id',
            'bookable_id'
        );
    }

    public function bookables(): HasMany
    {
        return $this->hasMany(Bookable::class);
    }
}
