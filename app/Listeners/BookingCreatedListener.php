<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\Logging\BookingLoggerService;

class BookingCreatedListener
{
    public function handle(BookingCreated $event)
    {
        BookingLoggerService::info("📝 [EVENT] Новая бронь создана", [
            'booking_id' => $event->booking->id,
            'resource_id' => $event->booking->resource_id,
            'status' => $event->booking->status
        ]);
    }
}
