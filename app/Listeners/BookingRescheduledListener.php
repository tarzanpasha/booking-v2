<?php

namespace App\Listeners;

use App\Events\BookingRescheduled;
use App\Services\Logging\BookingLoggerService;

class BookingRescheduledListener
{
    public function handle(BookingRescheduled $event)
    {
        BookingLoggerService::info("🔁 [EVENT] Бронь перенесена", [
            'booking_id' => $event->booking->id,
            'new_start' => $event->booking->start,
            'new_end' => $event->booking->end
        ]);
    }
}
