<?php

namespace App\Models\Concerns;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasBooking
{
    public function bookings(): MorphToMany
    {
        return $this->morphToMany(
            Booking::class,       // Related model
            'bookable',           // Name of the morph relation
            'bookables',          // Pivot table name
            'bookable_id',        // Foreign key for current model
            'booking_id'          // Foreign key for related model
        );
    }
}
