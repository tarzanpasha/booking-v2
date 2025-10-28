<?php

namespace App\Actions;

use App\Models\Booking;
use App\Enums\BookingStatus;
use App\ValueObjects\ResourceConfig;

class CancelBookingAction
{
    public function execute(int $bookingId, string $cancelledBy = 'client', ?string $reason = null): Booking
    {
        $booking = Booking::findOrFail($bookingId);
        $config = $booking->resource->getResourceConfig();

        if ($cancelledBy === 'client' && !$config->canCancel($booking->start)) {
            throw new \Exception('Время для отмены брони истекло');
        }

        $status = $cancelledBy === 'admin'
            ? BookingStatus::CANCELLED_BY_ADMIN
            : BookingStatus::CANCELLED_BY_CLIENT;

        $booking->update([
            'status' => $status->value,
            'reason' => $reason
        ]);

        return $booking;
    }
}
