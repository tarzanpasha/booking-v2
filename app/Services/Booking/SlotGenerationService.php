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

            foreach ($daySlots as $slot) {
                if (count($slots) >= $count) break;

                if ($this->isSlotAvailable($resource, $slot['start'], 1)) {
                    $slots[] = $slot;
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

        return $config->isFixedStrategy()
            ? $this->generateFixedSlots($resource, $date, $timetable, $config)
            : $this->generateDynamicSlots($resource, $date, $timetable, $config);
    }

    private function generateFixedSlots(Resource $resource, Carbon $date, $timetable, ResourceConfig $config): array
    {
        $workingHours = $this->getWorkingHoursForDate($timetable, $date);
        if (!$workingHours) return [];

        $slots = [];
        $slotDuration = $config->slot_duration_minutes;

        $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
        $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);
        $breaks = $workingHours['breaks'] ?? [];

        $current = $startTime->copy();

        while ($current->lt($endTime)) {
            $slotEnd = $current->copy()->addMinutes($slotDuration);

            if ($slotEnd->gt($endTime)) break;

            if (!$this->isTimeInBreaks($current, $slotEnd, $breaks)) {
                $slots[] = [
                    'start' => $current->copy(),
                    'end' => $slotEnd->copy()
                ];
            }

            $current->addMinutes($slotDuration);
        }

        return $slots;
    }

    private function generateDynamicSlots(Resource $resource, Carbon $date, $timetable, ResourceConfig $config): array
    {
        $workingHours = $this->getWorkingHoursForDate($timetable, $date);
        if (!$workingHours) return [];

        $bookings = Booking::where('resource_id', $resource->id)
            ->whereDate('start', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start')
            ->get();

        $slots = [];
        $slotDuration = $config->slot_duration_minutes;

        $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
        $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);
        $breaks = $workingHours['breaks'] ?? [];

        $availablePeriods = $this->getAvailablePeriods($startTime, $endTime, $bookings, $breaks);

        foreach ($availablePeriods as $period) {
            $current = $period['start']->copy();

            while ($current->addMinutes($slotDuration)->lte($period['end'])) {
                $slotStart = $current->copy()->subMinutes($slotDuration);
                $slotEnd = $current->copy();

                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd
                ];
            }
        }

        return $slots;
    }

    private function getAvailablePeriods(Carbon $startTime, Carbon $endTime, $bookings, array $breaks): array
    {
        $periods = [['start' => $startTime, 'end' => $endTime]];

        foreach ($bookings as $booking) {
            $newPeriods = [];
            foreach ($periods as $period) {
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

        foreach ($breaks as $break) {
            $breakStart = Carbon::parse($startTime->format('Y-m-d') . ' ' . $break['start']);
            $breakEnd = Carbon::parse($startTime->format('Y-m-d') . ' ' . $break['end']);

            $newPeriods = [];
            foreach ($periods as $period) {
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

    private function getWorkingHoursForDate($timetable, Carbon $date): ?array
    {
        if ($timetable->type === 'static') {
            $dayOfWeek = strtolower($date->englishDayOfWeek);
            return $timetable->schedule['days'][$dayOfWeek] ?? null;
        } else {
            $dateKey = $date->format('m-d');
            return $timetable->schedule['dates'][$dateKey] ?? null;
        }
    }

    private function isTimeInBreaks(Carbon $start, Carbon $end, array $breaks): bool
    {
        foreach ($breaks as $break) {
            $breakStart = Carbon::parse($start->format('Y-m-d') . ' ' . $break['start']);
            $breakEnd = Carbon::parse($start->format('Y-m-d') . ' ' . $break['end']);

            if ($start < $breakEnd && $end > $breakStart) {
                return true;
            }
        }
        return false;
    }

    private function isSlotAvailable(Resource $resource, Carbon $start, int $slots = 1): bool
    {
        $config = $resource->getResourceConfig();
        $duration = $config->slot_duration_minutes * $slots;
        $end = $start->copy()->addMinutes($duration);

        $overlapExists = Booking::where('resource_id', $resource->id)
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        return !$overlapExists;
    }
}
