<?php

namespace App\Services\Booking;

use App\Models\Resource;
use App\Models\Booking;
use App\ValueObjects\ResourceConfig;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class SlotGenerationService
{
    public function getNextAvailableSlots(
        Resource $resource,
        Carbon $from = null,
        int $count = 5,
        bool $onlyToday = true
    ): array {
        $from = $from ?? now();
        $config = $resource->getResourceConfig();
        $slots = [];
        $currentDate = $from->copy();

        while (count($slots) < $count) {
            $daySlots = $this->generateSlotsForDate($resource, $currentDate);

            // Добавляем проверку на пустые слоты и корректную структуру
            if (!empty($daySlots)) {
                foreach ($daySlots as $slot) {
                    if (count($slots) >= $count) break;

                    // Проверяем что слот имеет правильную структуру
                    if (isset($slot['start']) && isset($slot['end'])) {
                        // Проверяем что слот доступен
                        if ($this->isSlotAvailable($resource, $slot['start'], 1)) {
                            $slots[] = $slot;
                        }
                    }
                }
            }

            if ($onlyToday) break;
            $currentDate->addDay();
        }

        return $slots;
    }

    public function generateSlotsForDate(Resource $resource, Carbon $date): array
    {
        $config = $resource->getResourceConfig();
        $timetable = $resource->getEffectiveTimetable();

        if (!$timetable) {
            return [];
        }

        $workingHours = $this->getWorkingHoursForDate($timetable, $date);

        dump($workingHours);

        // Если рабочие часы не найдены (праздник или выходной) - возвращаем пустой массив
        if (!$workingHours) {
            return [];
        }

        $slots = $config->isFixedStrategy()
            ? $this->generateFixedSlots($resource, $date, $workingHours, $config)
            : $this->generateDynamicSlots($resource, $date, $workingHours, $config);

        // Гарантируем что все слоты имеют правильную структуру
        return array_filter($slots, function($slot) {
            return isset($slot['start']) && isset($slot['end']) && isset($slot['duration_minutes']);
        });
    }

    private function generateFixedSlots(Resource $resource, Carbon $date, array $workingHours, ResourceConfig $config): array
    {
        if (!isset($workingHours['working_hours'])) {
            return [];
        }
        $breaks = $workingHours['breaks'] ?? [];
        $workingHours = $workingHours['working_hours'];
        $slots = [];
        $slotDuration = $config->slot_duration_minutes ?? 60;

        try {
            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
            $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);
        } catch (\Exception $e) {
            return [];
        }

        $current = $startTime->copy();

        while ($current->lt($endTime)) {
            $slotEnd = $current->copy()->addMinutes($slotDuration);

            if ($slotEnd->gt($endTime)) break;

            // ИСПРАВЛЕННАЯ ПРОВЕРКА: используем исправленный метод проверки перерывов
            $slotAvailable = true;
            foreach ($breaks as $break) {
                if (!isset($break['start']) || !isset($break['end'])) continue;

                $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $break['end']);

                // Слот не должен пересекаться с перерывом
                if ($current->lt($breakEnd) && $slotEnd->gt($breakStart) &&
                    !$slotEnd->eq($breakStart) && !$current->eq($breakEnd)) {
                    $slotAvailable = false;
                    break;
                }
            }

            if ($slotAvailable) {
                $slots[] = [
                    'start' => $current->copy()->toDateTimeString(),
                    'end' => $slotEnd->copy()->toDateTimeString(),
                    'duration_minutes' => $slotDuration
                ];
                $current->addMinutes($slotDuration);
            } else {
                $current = $breakEnd->copy();
            }


        }

        return $slots;
    }

    private function generateDynamicSlots(Resource $resource, Carbon $date, array $workingHours, ResourceConfig $config): array
    {
        if (!isset($workingHours['working_hours'])) {
            return [];
        }

        $breaks = $workingHours['breaks'] ?? [];

        $workingHours = $workingHours['working_hours'];

        // Проверяем что working_hours имеет нужные поля
        if (!isset($workingHours['start']) || !isset($workingHours['end'])) {
            return [];
        }

        $bookings = Booking::where('resource_id', $resource->id)
            ->whereDate('start', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start')
            ->get();

        $slots = [];
        $slotDuration = $config->slot_duration_minutes ?? 60;

        try {
            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
            $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);
        } catch (\Exception $e) {
            return [];
        }

        $availablePeriods = $this->getAvailablePeriods($startTime, $endTime, $bookings, $breaks);

        foreach ($availablePeriods as $period) {
            $current = $period['start']->copy();

            while ($current->addMinutes($slotDuration)->lte($period['end'])) {
                $slotStart = $current->copy()->subMinutes($slotDuration);
                $slotEnd = $current->copy();

                $slots[] = [
                    'start' => $slotStart->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'duration_minutes' => $slotDuration
                ];
            }
        }

        return $slots;
    }

    private function getAvailablePeriods(Carbon $startTime, Carbon $endTime, $bookings, array $breaks): array
    {
        $periods = [['start' => $startTime, 'end' => $endTime]];

        // Обрабатываем бронирования
        foreach ($bookings as $booking) {
            $newPeriods = [];
            foreach ($periods as $period) {
                // Проверяем что период имеет правильную структуру
                if (!isset($period['start']) || !isset($period['end'])) {
                    continue;
                }

                if ($booking->start >= $period['end'] || $booking->end <= $period['start']) {
                    $newPeriods[] = $period;
                } else {
                    if ($booking->start > $period['start']) {
                        $newPeriods[] = ['start' => $period['start'], 'end' => $booking->start];
                    }
                    if ($booking->end < $period['end']) {
                        $newPeriods[] = ['start' => $booking->end, 'end' => $period['end']];
                    }
                }
            }
            $periods = $newPeriods;
        }

        // Обрабатываем перерывы
        foreach ($breaks as $break) {
            // Проверяем что перерыв имеет правильную структуру
            if (!isset($break['start']) || !isset($break['end'])) {
                continue;
            }

            try {
                $breakStart = Carbon::parse($startTime->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = Carbon::parse($startTime->format('Y-m-d') . ' ' . $break['end']);
            } catch (\Exception $e) {
                continue;
            }

            $newPeriods = [];
            foreach ($periods as $period) {
                if (!isset($period['start']) || !isset($period['end'])) {
                    continue;
                }

                if ($breakStart >= $period['end'] || $breakEnd <= $period['start']) {
                    $newPeriods[] = $period;
                } else {
                    if ($breakStart > $period['start']) {
                        $newPeriods[] = ['start' => $period['start'], 'end' => $breakStart];
                    }
                    if ($breakEnd < $period['end']) {
                        $newPeriods[] = ['start' => $breakEnd, 'end' => $period['end']];
                    }
                }
            }
            $periods = $newPeriods;
        }

        return $periods;
    }

    // В app/Services/Booking/SlotGenerationService.php
    private function getWorkingHoursForDate($timetable, Carbon $date): ?array
    {
        if (!$timetable || !isset($timetable->schedule)) {
            return null;
        }

        // Проверяем праздничные дни для статического расписания
        if ($timetable->type === 'static') {
            $dateKey = $date->format('m-d');
            $holidays = $timetable->schedule['holidays'] ?? [];

            // Если дата в праздниках - возвращаем null
            if (in_array($dateKey, $holidays)) {
                return null;
            }

            $dayOfWeek = strtolower($date->englishDayOfWeek);


            // Проверяем, есть ли рабочие часы для этого дня недели
            if (!isset($timetable->schedule['days'][$dayOfWeek])) {
                return null;
            }

            $daySchedule = $timetable->schedule['days'][$dayOfWeek];

            // Проверяем, что день действительно рабочий (есть рабочие часы)
            if (!isset($daySchedule['working_hours']) ||
                !isset($daySchedule['working_hours']['start']) ||
                !isset($daySchedule['working_hours']['end']) ||
                empty($daySchedule['working_hours']['start']) ||
                empty($daySchedule['working_hours']['end'])) {
                return null;
            }

            return $daySchedule;
        } else {
            $dateKey = $date->format('m-d');
            return isset($timetable->schedule['dates'][$dateKey]) ? $timetable->schedule['dates'][$dateKey] : null;
        }
    }

    private function isTimeInBreaks(Carbon $start, Carbon $end, array $breaks): bool
    {
        foreach ($breaks as $break) {
            // Проверяем что перерыв имеет правильную структуру
            if (!isset($break['start']) || !isset($break['end'])) {
                continue;
            }

            try {
                $breakStart = Carbon::parse($start->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = Carbon::parse($start->format('Y-m-d') . ' ' . $break['end']);
            } catch (\Exception $e) {
                continue;
            }

            if ($start < $breakEnd && $end > $breakStart) {
                return true;
            }
        }
        return false;
    }

    private function isSlotAvailable(Resource $resource, string $start, int $slots = 1): bool
    {
        try {
            $startTime = Carbon::parse($start);
        } catch (\Exception $e) {
            return false;
        }

        $config = $resource->getResourceConfig();
        $duration = ($config->slot_duration_minutes ?? 60) * $slots;
        $endTime = $startTime->copy()->addMinutes($duration);

        $overlapExists = Booking::where('resource_id', $resource->id)
            ->where('start', '<', $endTime)
            ->where('end', '>', $startTime)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        return !$overlapExists;
    }
}
