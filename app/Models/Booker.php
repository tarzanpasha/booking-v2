<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Booker extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'type',
        'name',
        'email',
        'phone',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function bookings(): MorphToMany
    {
        return $this->morphedByMany(Booking::class, 'bookable');
    }
}
