<?php

namespace App\Actions;

use App\Models\Booking;
use App\Enums\BookingStatus;
use App\ValueObjects\ResourceConfig;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RescheduleBookingAction
{
    public function execute(
        int $bookingId,
        string $newStart,
        string $newEnd,
        string $requestedBy = 'client'
    ): Booking {
        return DB::transaction(function () use ($bookingId, $newStart, $newEnd, $requestedBy) {
            $booking = Booking::findOrFail($bookingId);
            $resource = $booking->resource;
            $config = $resource->getResourceConfig();

            if (BookingStatus::from($booking->status)->isCancelled()) {
                throw new \Exception('Невозможно перенести отмененную бронь');
            }

            if ($requestedBy === 'client' && !$config->canReschedule($booking->start)) {
                throw new \Exception('Время для переноса брони истекло');
            }

            $newStartTime = Carbon::parse($newStart);
            $newEndTime = Carbon::parse($newEnd);

            $booking->update([
                'start' => $newStartTime,
                'end' => $newEndTime
            ]);

            return $booking;
        });
    }
}
