<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Resource;
use App\Enums\BookingStatus;
use App\ValueObjects\ResourceConfig;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateBookingAction
{
    public function execute(
        Resource $resource,
        string $start,
        string $end,
        array $bookerData = [],
        bool $isAdmin = false
    ): Booking {
        return DB::transaction(function () use ($resource, $start, $end, $bookerData, $isAdmin) {
            $config = $resource->getResourceConfig();
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);

            $status = $config->requiresConfirmation() && !$isAdmin
                ? BookingStatus::PENDING
                : BookingStatus::CONFIRMED;

            $booking = Booking::create([
                'company_id' => $resource->company_id,
                'resource_id' => $resource->id,
                'timetable_id' => $resource->getEffectiveTimetable()?->id,
                'is_group_booking' => $config->isGroupResource(),
                'start' => $startTime,
                'end' => $endTime,
                'status' => $status->value,
            ]);

            if (!empty($bookerData)) {
                app(AttachBookerAction::class)->execute($booking, $bookerData);
            }

            return $booking;
        });
    }
}
