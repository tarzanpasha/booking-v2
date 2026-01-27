<?php

namespace App\Listeners;

use App\Events\BookingReminder;
use App\Services\Logging\BookingLoggerService;

class BookingReminderListener
{
    public function handle(BookingReminder $event)
    {
        BookingLoggerService::info("⏰ [EVENT] Напоминание о брони", [
            'booking_id' => $event->booking->id,
            'start' => $event->booking->start
        ]);
    }
}
