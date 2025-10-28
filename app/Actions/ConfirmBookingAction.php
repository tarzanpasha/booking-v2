<?php

namespace App\Actions;

use App\Models\Booking;
use App\Enums\BookingStatus;

class ConfirmBookingAction
{
    public function execute(int $bookingId): Booking
    {
        $booking = Booking::findOrFail($bookingId);

        if ($booking->status !== BookingStatus::PENDING->value) {
            throw new \Exception('Можно подтверждать только брони в статусе ожидания');
        }

        $booking->status = BookingStatus::CONFIRMED->value;
        $booking->save();

        return $booking;
    }
}
