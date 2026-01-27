<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\Logging\BookingLoggerService;

class BookingCancelledListener
{
    public function handle(BookingCancelled $event)
    {
        BookingLoggerService::warning("❌ [EVENT] Бронь отменена", [
            'booking_id' => $event->booking->id,
            'status' => $event->booking->status,
            'reason' => $event->booking->reason
        ]);
    }
}
