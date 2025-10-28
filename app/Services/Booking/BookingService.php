<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\Booker;
use App\Enums\BookingStatus;
use App\ValueObjects\ResourceConfig;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Logging\BookingLoggerService;

class BookingService
{
    public function __construct(
        private SlotGenerationService $slotService
    ) {}

    public function isSlotAvailable(Resource $resource, string $start, int $slots = 1): bool
    {
        $startTime = Carbon::parse($start);
        $config = $resource->getResourceConfig();
        $duration = $config->slot_duration_minutes * $slots;
        $endTime = $startTime->copy()->addMinutes($duration);

        return $this->isRangeAvailable($resource, $startTime, $endTime);
    }

    public function isRangeAvailable(Resource $resource, Carbon $from, Carbon $to): bool
    {
        $overlapExists = Booking::where('resource_id', $resource->id)
            ->where('start', '<', $to)
            ->where('end', '>', $from)
            ->whereIn('status', [BookingStatus::PENDING->value, BookingStatus::CONFIRMED->value])
            ->exists();

        return !$overlapExists;
    }

    public function createBooking(
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

            if (!$isAdmin) {
                $this->validateBookingTime($resource, $startTime, $endTime, $config);
            }

            if (!$this->isRangeAvailable($resource, $startTime, $endTime)) {
                throw new \Exception('Выбранный временной диапазон уже занят');
            }

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
                $this->attachBooker($booking, $bookerData);
            }

            BookingLoggerService::info("✅ Бронь создана", [
                'booking_id' => $booking->id,
                'resource_id' => $resource->id,
                'status' => $booking->status,
                'is_admin' => $isAdmin
            ]);

            event(new \App\Events\BookingCreated($booking));

            return $booking;
        });
    }

    public function confirmBooking(int $bookingId): Booking
    {
        $booking = Booking::findOrFail($bookingId);

        if ($booking->status !== BookingStatus::PENDING->value) {
            throw new \Exception('Можно подтверждать только брони в статусе ожидания');
        }

        $booking->status = BookingStatus::CONFIRMED->value;
        $booking->save();

        BookingLoggerService::info("✅ Бронь подтверждена", ['booking_id' => $booking->id]);
        event(new \App\Events\BookingConfirmed($booking));

        return $booking;
    }

    public function cancelBooking(int $bookingId, string $cancelledBy = 'client', ?string $reason = null): Booking
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

        BookingLoggerService::warning("❌ Бронь отменена", [
            'booking_id' => $booking->id,
            'cancelled_by' => $cancelledBy,
            'reason' => $reason
        ]);

        event(new \App\Events\BookingCancelled($booking));

        return $booking;
    }

    public function rescheduleBooking(
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

            if (!$this->isRangeAvailable($resource, $newStartTime, $newEndTime)) {
                throw new \Exception('Новый временной диапазон уже занят');
            }

            $booking->update([
                'start' => $newStartTime,
                'end' => $newEndTime
            ]);

            BookingLoggerService::info("🔁 Бронь перенесена", [
                'booking_id' => $booking->id,
                'requested_by' => $requestedBy
            ]);

            event(new \App\Events\BookingRescheduled($booking));

            return $booking;
        });
    }

    public function getBookingsForResourceInRange(Resource $resource, string $from, string $to)
    {
        return Booking::where('resource_id', $resource->id)
            ->where('start', '<', $to)
            ->where('end', '>', $from)
            ->whereIn('status', [BookingStatus::PENDING->value, BookingStatus::CONFIRMED->value])
            ->get();
    }

    public function getNextAvailableSlots(
        Resource $resource,
        Carbon $from = null,
        int $count = 5,
        bool $onlyToday = true
    ): array {
        return $this->slotService->getNextAvailableSlots($resource, $from, $count, $onlyToday);
    }

    private function validateBookingTime(Resource $resource, Carbon $start, Carbon $end, ResourceConfig $config): void
    {
        $now = now();

        if ($start->diffInMinutes($now) < $config->min_advance_time) {
            throw new \Exception('Бронирование возможно только за ' . $config->min_advance_time . ' минут');
        }

        if (!$this->isValidSlotTime($resource, $start, $end, $config)) {
            throw new \Exception('Выбранное время не соответствует доступным слотам');
        }
    }

    private function isValidSlotTime(Resource $resource, Carbon $start, Carbon $end, ResourceConfig $config): bool
    {
        if ($config->isFixedStrategy()) {
            $slots = $this->slotService->generateSlotsForDate($resource, $start);

            foreach ($slots as $slot) {
                if ($slot['start']->eq($start) && $slot['end']->eq($end)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    private function attachBooker(Booking $booking, array $bookerData): void
    {
        $booker = Booker::firstOrCreate(
            [
                'external_id' => $bookerData['external_id'] ?? null,
                'type' => $bookerData['type'] ?? 'client'
            ],
            [
                'name' => $bookerData['name'] ?? null,
                'email' => $bookerData['email'] ?? null,
                'phone' => $bookerData['phone'] ?? null,
                'metadata' => $bookerData['metadata'] ?? null,
            ]
        );

        $booking->bookers()->attach($booker->id);
    }
}
