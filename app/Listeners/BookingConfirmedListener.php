<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Services\Logging\BookingLoggerService;

class BookingConfirmedListener
{
    public function handle(BookingConfirmed $event)
    {
        BookingLoggerService::info("✅ [EVENT] Бронь подтверждена", [
            'booking_id' => $event->booking->id,
            'status' => $event->booking->status
        ]);
    }
}
