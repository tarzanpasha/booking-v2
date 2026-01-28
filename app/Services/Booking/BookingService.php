<?php
// app/Services/Booking/BookingService.php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Resource;
use App\Enums\BookingStatus;
use App\ValueObjects\ResourceConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Logging\BookingLoggerService;

class BookingService
{
    public function __construct(
        private SlotGenerationService $slotService
    ) {}

    /**
     * Проверяет доступность диапазона с учетом перерывов и других бронирований
     */
    public function isTimeRangeAvailable(Resource $resource, Carbon $start, Carbon $end): bool
    {
        // Сначала проверяем бронирования
        if (!$this->isRangeAvailable($resource, $start, $end)) {
            return false;
        }

        // Затем проверяем перерывы с ИСПРАВЛЕННОЙ логикой
        if (!$this->isTimeAvailableConsideringBreaks($resource, $start, $end)) {
            return false;
        }

        return true;
    }

    public function isSlotAvailable(Resource $resource, string $start, int $slots = 1): bool
    {
        $startTime = Carbon::parse($start);
        $config = $resource->getResourceConfig();
        $duration = $config->slot_duration_minutes * $slots;
        $endTime = $startTime->copy()->addMinutes($duration);

        return $this->isRangeAvailable($resource, $startTime, $endTime);
    }

    // В файле app/Services/Booking/BookingService.php

    public function isRangeAvailable(Resource $resource, Carbon $from, Carbon $to): bool
    {
        $overlapExists = Booking::where('resource_id', $resource->id)
            ->where(function ($query) use ($from, $to) {
                $query->where(function ($q) use ($from, $to) {
                    // Бронь начинается внутри запрашиваемого диапазона
                    $q->where('start', '>=', $from)
                        ->where('start', '<', $to);
                })->orWhere(function ($q) use ($from, $to) {
                    // Бронь заканчивается внутри запрашиваемого диапазона
                    $q->where('end', '>', $from)
                        ->where('end', '<=', $to);
                })->orWhere(function ($q) use ($from, $to) {
                    // Бронь полностью содержит запрашиваемый диапазон
                    $q->where('start', '<', $from)
                        ->where('end', '>', $to);
                });
            })
            ->whereIn('status', [BookingStatus::PENDING->value, BookingStatus::CONFIRMED->value])
            ->exists();

        return !$overlapExists;
    }

    private function getBookigForThatPeriod(Resource $resource, Carbon $start, Carbon $end): ?Booking
    {
        $booking = Booking::query()
            ->where('resource_id', $resource->id)
            ->where('start', '= ', $start)
            ->where('end', '= ', $end)
            ->first() ?? null;
    }

    /**
     * @throws \Throwable
     */
    public function createBooking(
        Resource $resource,
        string $start,
        string $end,
        Model $bookerData,
        bool $isAdmin = false
    ): Booking {
        return DB::transaction(function () use ($resource, $start, $end, $bookerData, $isAdmin) {
            $config = $resource->getResourceConfig();
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);

            $status = $config->requiresConfirmation() && !$isAdmin
                ? BookingStatus::PENDING
                : BookingStatus::CONFIRMED;

            if ($booking = $this->getBookigForThatPeriod($resource, $startTime, $endTime)) {
                if ($booking->status == BookingStatus::CONFIRMED->value || $booking->status == BookingStatus::PENDING->value) {
                    $this->attachBooker($booking, $bookerData, $isAdmin);
                }
            }

            if (!$isAdmin) {
                $this->validateBookingTime($resource, $startTime, $endTime, $config);

                // Для обычных пользователей проверяем доступность
                if (!$this->isTimeRangeAvailable($resource, $startTime, $endTime)) {
                    throw new \Exception('Выбранный временной диапазон недоступен (занят или пересекается с перерывом)');
                }
            } else {
                // Для администраторов проверяем только базовую валидность времени
                if ($startTime >= $endTime) {
                    throw new \Exception('Время окончания должно быть после времени начала');
                }

            }


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


    private function changeBookableStatus(Booking $booking, Model $booker, string $status, ?string $reason = ""): void
    {
        $booking->bookables()
            ->where('booking_id', '=', $booking->id)
            ->where('bookable_id', '=', $booker->id)
            ->where('bookable_type', '=', $booker::class)
            ->update(['status' => $status, 'reason' => $reason]);
        /**
         * Старая версия. Не проверено тестами еще.
         */
        DB::table('bookables')
            ->where('booking_id', '=', $booking->id)
            ->where('bookable_id', '=', $booker->id)
            ->where('bookable_type', '=', $booker::class)
            ->update(['status' => $status, 'reason' => $reason]);
    }

    public function confirmBooking(int $bookingId, Model $booker): Booking
    {
        $booking = Booking::findOrFail($bookingId);

        if ($booking->status !== BookingStatus::PENDING->value) {
            throw new \Exception('Можно подтверждать только брони в статусе ожидания');
        }

        $booking->status = BookingStatus::CONFIRMED->value;
        $booking->save();

        $this->changeBookableStatus($booking, $booker, BookingStatus::CONFIRMED->value);

        BookingLoggerService::info("✅ Бронь подтверждена", ['booking_id' => $booking->id]);
        event(new \App\Events\BookingConfirmed($booking));

        return $booking;
    }

    public function cancelBooking(int $bookingId, string $cancelledBy = 'client', Model $booker, ?string $reason = null): Booking
    {
        $booking = Booking::findOrFail($bookingId);
        $config = $booking->resource->getResourceConfig();

        if ($cancelledBy === 'client' && !$config->canCancel($booking->start)) {
            throw new \Exception('Время для отмены брони истекло');
        }

        $status = $cancelledBy === 'admin'
            ? BookingStatus::CANCELLED_BY_ADMIN
            : BookingStatus::CANCELLED_BY_CLIENT;


        if (!$booking->is_group_booking) {
            $booking->update([
                'status' => $status->value,
                'reason' => $reason
            ]);
        }

        $this->changeBookableStatus($booking, $booker, $status->value, $reason);

        if ($booking->is_group_booking && !$booking->bookables()->where('status', '=', BookingStatus::CONFIRMED->value)->exists()) {
            $booking->update([
                'status' => $status->value,
                'reason' => $reason
            ]);
        }

        if (!$booking->is_group_booking) {
            BookingLoggerService::warning("❌ Бронь отменена  для {$booker->name} ", [
                'booking_id' => $booking->id,
                'cancelled_by' => $cancelledBy,
                'reason' => $reason
            ]);
        } else {
            BookingLoggerService::warning("❌ Бронь отменена", [
                'booking_id' => $booking->id,
                'cancelled_by' => $cancelledBy,
                'reason' => $reason
            ]);
        }


        event(new \App\Events\BookingCancelled($booking));

        return $booking;
    }

    /**
     * @throws \Throwable
     */
    public function rescheduleBooking(
        int $bookingId,
        string $newStart,
        string $newEnd,
        string $requestedBy = 'client'
    ): Booking {
        return DB::transaction(function () use ($bookingId, $newStart, $newEnd, $requestedBy) {
            $booking = Booking::findOrFail($bookingId);

            if ($booking->is_group_booking && $requestedBy == 'client') {
                throw new \Exception('Невозможно перенести групповую бронь не админу');
            }

            if (BookingStatus::from($booking->status)->isCancelled()) {
                throw new \Exception('Невозможно перенести отмененную бронь');
            }

            $resource = $booking->resource;
            $config = $resource->getResourceConfig();

            if ($requestedBy === 'client' && !$config->canReschedule($booking->start)) {
                throw new \Exception('Время для переноса брони истекло');
            }

            $newStartTime = Carbon::parse($newStart);
            $newEndTime = Carbon::parse($newEnd);

            // Используем новый метод для комплексной проверки нового времени
            if ($requestedBy!=='admin' && !$this->isTimeRangeAvailable($resource, $newStartTime, $newEndTime)) {
                throw new \Exception('Новый временной диапазон недоступен (занят или пересекается с перерывом)');
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

    // В app/Services/Booking/BookingService.php
    private function validateBookingTime(Resource $resource, Carbon $start, Carbon $end, ResourceConfig $config): void
    {
        $now = now();

        // Проверка минимального времени для бронирования
        if ($config->min_advance_time > 0) {
            $minutesUntilStart = $start->diffInMinutes($now, false); // false чтобы получить отрицательное значение для прошедшего времени

            if ($minutesUntilStart < $config->min_advance_time) {
                throw new \Exception('Бронирование возможно только за ' . $config->min_advance_time . ' минут до начала. До начала осталось: ' . $minutesUntilStart . ' минут');
            }
        }

        // Для строгих ограничений (min_advance_time = 0) - бронирование только в будущем
        if ($config->min_advance_time === 0 && $start <= $now) {
            throw new \Exception('Бронирование невозможно для прошедшего времени');
        }

        if ($start >= $end) {
            throw new \Exception('Время окончания должно быть после времени начала');
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
                if ($slot['start'] === $start->toDateTimeString() && $slot['end'] === $end->toDateTimeString()) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    public function attachBooker(Booking $booking, Model $booker, ?bool $isAdmin = false): void
    {
        $config = $booking->resource->getResourceConfig();
        $countBookers = $booking->bookables()
            ->where('status', BookingStatus::CONFIRMED->value)
            ->get()
            ->count();
        if ($countBookers < $config->max_participants) {
            if ($isAdmin) {
                $booker->bookings()->syncWithoutDetaching([$booking->id => [
                    'status' => BookingStatus::CONFIRMED->value,
                    'reason' => $booking->reason,
                ]]);
                $booking->update([
                    'status' => BookingStatus::CONFIRMED->value,
                ]);
            } else {
                $booker->bookings()->syncWithoutDetaching([$booking->id => [
                    'status' => $booking->status,
                    'reason' => $booking->reason,
                ]]);
            }


        } else {
            $booker->bookings()->syncWithoutDetaching([$booking->id => [
                'status' => BookingStatus::REJECTED->value,
                'reason' => "Бронь переполнена",
            ]]);
        }

    }

    /**
     * Проверяет доступность времени с учетом перерывов
     */
    public function isTimeAvailableConsideringBreaks(Resource $resource, Carbon $start, Carbon $end): bool
    {
        $timetable = $resource->getEffectiveTimetable();

        if (!$timetable) {
            return true;
        }

        $workingHours = $this->getWorkingHoursForDate($timetable, $start);

        if (!$workingHours) {
            return true;
        }

        $breaks = $workingHours['breaks'] ?? [];

        foreach ($breaks as $break) {
            if (!isset($break['start']) || !isset($break['end'])) {
                continue;
            }

            try {
                $breakStart = Carbon::parse($start->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = Carbon::parse($start->format('Y-m-d') . ' ' . $break['end']);
            } catch (\Exception $e) {
                continue;
            }

            // Упрощенная и корректная проверка пересечений
            // Пересечение есть если:
            // - начало брони внутри перерыва (исключая конец перерыва)
            // - конец брони внутри перерыва (исключая начало перерыва)
            // - бронь полностью содержит перерыв
            // - перерыв полностью содержит бронь

            $startInBreak = $start->between($breakStart, $breakEnd, false);
            $endInBreak = $end->between($breakStart, $breakEnd, false);
            $spansBreak = $start->lt($breakStart) && $end->gt($breakEnd);
            $containedInBreak = $start->gte($breakStart) && $end->lte($breakEnd);

            // ДОПОЛНЕНИЕ: разрешаем касание границ перерыва
            $touchesBreakStart = $end->eq($breakStart); // заканчивается точно в начале перерыва - РАЗРЕШАЕМ
            $touchesBreakEnd = $start->eq($breakEnd);   // начинается точно в конце перерыва - РАЗРЕШАЕМ

            if ($startInBreak || $endInBreak || $spansBreak || $containedInBreak) {
                return false;
            }

            // Запрещаем только если бронь начинается ДО и заканчивается ПОСЛЕ перерыва
            // (но не просто касается границ)
            if ($start->lt($breakStart) && $end->gt($breakEnd) &&
                !$touchesBreakStart && !$touchesBreakEnd) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получает рабочие часы для даты
     */
    private function getWorkingHoursForDate($timetable, Carbon $date): ?array
    {
        if (!$timetable || !isset($timetable->schedule)) {
            return null;
        }

        if ($timetable->type === 'static') {
            $dayOfWeek = strtolower($date->englishDayOfWeek);
            return $timetable->schedule['days'][$dayOfWeek] ?? null;
        } else {
            $dateKey = $date->format('m-d');
            return $timetable->schedule['dates'][$dateKey] ?? null;
        }
    }
}
