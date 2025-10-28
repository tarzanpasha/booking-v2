<?php

namespace App\Console\Commands;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\CreateTimetableFromJsonAction;
use App\Models\Timetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GenerateDinamicTimetable extends Command
{
    protected $signature = 'timetable:generate-dinamic {company_id} {days=30}';
    protected $description = 'Generate dinamic timetable for specified period';

    public function handle(
        CreateOrUpdateCompanyAction $createOrUpdateCompanyAction,
        CreateTimetableFromJsonAction $createTimetableFromJsonAction
    ): void {
        $companyId = $this->argument('company_id');
        $daysCount = $this->argument('days');

        // Создаем или получаем компанию
        $company = $createOrUpdateCompanyAction->execute($companyId);

        $schedule = ['dates' => []];
        $startDate = now();

        for ($i = 0; $i < $daysCount; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Пропускаем некоторые дни (30% вероятность для выходных)
            $dayOfWeek = $date->dayOfWeek;
            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6); // 0 - воскресенье, 6 - суббота

            if ($isWeekend && rand(1, 100) <= 70) {
                continue; // Пропускаем большинство выходных
            }

            if (!$isWeekend && rand(1, 100) <= 15) {
                continue; // Пропускаем некоторые будни
            }

            $dateKey = $date->format('m-d');

            // Генерируем уникальный график для каждого дня
            $schedule['dates'][$dateKey] = $this->generateDailySchedule();
        }

        $timetable = $createTimetableFromJsonAction->execute($companyId, $schedule, 'dinamic');

        // Сохраняем пример в файл
        Storage::put('exports/dinamic_timetable_example.json', json_encode($schedule, JSON_PRETTY_PRINT));

        $this->info("Dinamic timetable created successfully for company {$companyId}");
        $this->info("Generated schedule for " . count($schedule['dates']) . " days (some days skipped)");
        $this->info("Example saved to storage/app/exports/dinamic_timetable_example.json");
    }

    /**
     * Генерирует уникальный график для одного дня
     */
    private function generateDailySchedule(): array
    {
        $scheduleTypes = [
            '8h_normal' => ['start' => '09:00', 'end' => '17:00'],
            '8h_early' => ['start' => '08:00', 'end' => '16:00'],
            '8h_late' => ['start' => '10:00', 'end' => '18:00'],
            '10h' => ['start' => '08:00', 'end' => '18:00'],
            '12h' => ['start' => '08:00', 'end' => '20:00'],
            '24h' => ['start' => '08:00', 'end' => '08:00'], // Следующего дня
            '6h_short' => ['start' => '10:00', 'end' => '16:00'],
        ];

        // Выбираем случайный тип графика
        $scheduleType = array_rand($scheduleTypes);
        $workingHours = $scheduleTypes[$scheduleType];

        // Генерируем перерывы в зависимости от типа графика
        $breaks = $this->generateBreaksForSchedule($scheduleType, $workingHours);

        return [
            'working_hours' => $workingHours,
            'breaks' => $breaks,
            'schedule_type' => $scheduleType, // Для отладки
        ];
    }

    /**
     * Генерирует перерывы в зависимости от типа графика
     */
    private function generateBreaksForSchedule(string $scheduleType, array $workingHours): array
    {
        $breaks = [];

        switch ($scheduleType) {
            case '8h_normal':
            case '8h_early':
            case '8h_late':
                // 1-2 перерыва для 8-часового дня
                $breakCount = rand(1, 2);
                $breaks = $this->generateBreaks($workingHours, $breakCount, 30, 60);
                break;

            case '10h':
                // 2-3 перерыва для 10-часового дня
                $breakCount = rand(2, 3);
                $breaks = $this->generateBreaks($workingHours, $breakCount, 15, 45);
                break;

            case '12h':
                // 3-4 перерыва для 12-часового дня
                $breakCount = rand(3, 4);
                $breaks = $this->generateBreaks($workingHours, $breakCount, 15, 60);
                break;

            case '24h':
                // 4-6 перерывов для 24-часовой смены
                $breakCount = rand(4, 6);
                $breaks = $this->generate24hBreaks($breakCount);
                break;

            case '6h_short':
                // 0-1 перерыв для короткого дня
                $breakCount = rand(0, 1);
                if ($breakCount > 0) {
                    $breaks = $this->generateBreaks($workingHours, 1, 15, 30);
                }
                break;
        }

        return $breaks;
    }

    /**
     * Генерирует перерывы для обычного графика
     */
    private function generateBreaks(array $workingHours, int $breakCount, int $minDuration, int $maxDuration): array
    {
        $breaks = [];
        $startHour = (int)explode(':', $workingHours['start'])[0];
        $endHour = (int)explode(':', $workingHours['end'])[0];

        // Для 24-часовой смены
        if ($endHour <= $startHour) {
            $endHour += 24;
        }

        $workDuration = $endHour - $startHour;
        $interval = $workDuration / ($breakCount + 1);

        for ($i = 1; $i <= $breakCount; $i++) {
            $breakStartHour = $startHour + ($interval * $i);
            $breakDuration = rand($minDuration, $maxDuration);

            // Случайное смещение начала перерыва (±30 минут)
            $breakStartOffset = rand(-30, 30);
            $breakStartHour += $breakStartOffset / 60;

            $breakStart = $this->minutesToTime($breakStartHour * 60);
            $breakEnd = $this->minutesToTime(($breakStartHour * 60) + $breakDuration);

            $breaks[] = [
                'start' => $breakStart,
                'end' => $breakEnd,
                'duration_minutes' => $breakDuration,
            ];
        }

        return $breaks;
    }

    /**
     * Генерирует перерывы для 24-часовой смены
     */
    private function generate24hBreaks(int $breakCount): array
    {
        $breaks = [];
        $totalBreakMinutes = 180; // 3 часа суммарно
        $breakDuration = (int)($totalBreakMinutes / $breakCount);

        // Равномерно распределяем перерывы по 24-часовой смене
        $shiftDuration = 24 * 60; // 24 часа в минутах
        $interval = (int)($shiftDuration / ($breakCount + 1));

        for ($i = 1; $i <= $breakCount; $i++) {
            $breakStartMinutes = $interval * $i;
            $breakEndMinutes = $breakStartMinutes + $breakDuration;

            $breakStart = $this->minutesToTime($breakStartMinutes);
            $breakEnd = $this->minutesToTime($breakEndMinutes);

            $breaks[] = [
                'start' => $breakStart,
                'end' => $breakEnd,
                'duration_minutes' => $breakDuration,
            ];
        }

        return $breaks;
    }

    /**
     * Преобразует минуты в формат времени HH:MM
     */
    private function minutesToTime(int $totalMinutes): string
    {
        $hours = floor($totalMinutes / 60) % 24;
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
