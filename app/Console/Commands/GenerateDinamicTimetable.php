<?php

namespace App\Console\Commands;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Models\Timetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GenerateDinamicTimetable extends Command
{
    protected $signature = 'timetable:generate-dinamic {company_id} {days=30}';
    protected $description = 'Generate dinamic timetable for specified period';

    public function handle(CreateOrUpdateCompanyAction $createOrUpdateCompanyAction): void
    {
        $companyId = $this->argument('company_id');
        $daysCount = $this->argument('days');

        // Создаем или получаем компанию
        $company = $createOrUpdateCompanyAction->execute($companyId);

        // ОДИН РАЗ выбираем тип расписания
        $scheduleType = $this->selectScheduleType();
        $this->info("Selected schedule type: {$scheduleType}");

        // Генерируем расписание в зависимости от типа
        $schedule = ['dates' => []];
        $startDate = now();

        switch ($scheduleType) {
            case 'weekdays_9h':
                $schedule = $this->generateWeekdays9h($startDate, $daysCount);
                break;
            case 'day_after_day_12h':
                $schedule = $this->generateDayAfterDay12h($startDate, $daysCount);
                break;
            case '24h_after_48h':
                $schedule = $this->generate24hAfter48h($startDate, $daysCount);
                break;
        }

        $timetable = Timetable::create([
            'company_id' => $companyId,
            'type' => 'dinamic',
            'schedule' => $schedule,
        ]);

        // Сохраняем пример в файл
        Storage::put('exports/dinamic_timetable_example.json', json_encode($schedule, JSON_PRETTY_PRINT));

        $this->info("Dinamic timetable created successfully for company {$companyId}");
        $this->info("Generated {$daysCount} days schedule");
        $this->info("Example saved to storage/app/exports/dinamic_timetable_example.json");
    }

    /**
     * Выбирает тип расписания
     */
    private function selectScheduleType(): string
    {
        $types = [
            'weekdays_9h',        // Будни по 9 часов, пятница сокращенная
            'day_after_day_12h',  // День через день по 12 часов
            '24h_after_48h',      // Сутки через двое
        ];

        return $types[array_rand($types)];
    }

    /**
     * Расписание: Будни по 9 часов, пятница сокращенная
     */
    private function generateWeekdays9h($startDate, $daysCount): array
    {
        $schedule = ['dates' => []];
        $workingHoursNormal = ['start' => '09:00', 'end' => '18:00'];
        $workingHoursFriday = ['start' => '09:00', 'end' => '17:00']; // Сокращенная пятница

        // Один обеденный перерыв 60 минут
        $breaks = [
            ['start' => '13:00', 'end' => '14:00']
        ];

        for ($i = 0; $i < $daysCount; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateKey = $date->format('m-d');
            $dayOfWeek = $date->dayOfWeek; // 0 (воскресенье) до 6 (суббота)

            // Только будни (понедельник-пятница)
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $workingHours = ($dayOfWeek === 5) ? $workingHoursFriday : $workingHoursNormal;

                $schedule['dates'][$dateKey] = [
                    'working_hours' => $workingHours,
                    'breaks' => $breaks,
                ];
            }
            // Выходные (суббота, воскресенье) - не добавляем в расписание
        }

        $this->info("Generated weekdays schedule: Mon-Thu 09:00-18:00, Fri 09:00-17:00");
        return $schedule;
    }

    /**
     * Расписание: День через день по 12 часов
     */
    private function generateDayAfterDay12h($startDate, $daysCount): array
    {
        $schedule = ['dates' => []];

        // 12-часовая смена с двумя перерывами
        $workingHours = ['start' => '08:00', 'end' => '20:00'];
        $breaks = [
            ['start' => '12:00', 'end' => '13:00'], // Обед 60 минут
            ['start' => '16:00', 'end' => '16:30']  // Короткий перерыв 30 минут
        ];

        // Случайный первый рабочий день
        $firstWorkingDay = rand(0, 1);

        for ($i = 0; $i < $daysCount; $i++) {
            // День через день (каждый второй день)
            if (($i - $firstWorkingDay) % 2 === 0) {
                $date = $startDate->copy()->addDays($i);
                $dateKey = $date->format('m-d');

                $schedule['dates'][$dateKey] = [
                    'working_hours' => $workingHours,
                    'breaks' => $breaks,
                ];
            }
            // Выходные дни - не добавляем в расписание
        }

        $this->info("Generated day-after-day schedule: 12-hour shifts (08:00-20:00)");
        return $schedule;
    }

    /**
     * Расписание: Сутки через двое (24 часа)
     */
    private function generate24hAfter48h($startDate, $daysCount): array
    {
        $schedule = ['dates' => []];

        // 24-часовая смена
        $workingHours = ['start' => '08:00', 'end' => '08:00']; // Следующего дня

        // 3-4 перерыва суммарно 2 часа
        $breakCount = rand(3, 4);
        $breaks = $this->generate24hBreaks($breakCount);

        // Случайный первый рабочий день (0, 1 или 2)
        $firstWorkingDay = rand(0, 2);

        for ($i = 0; $i < $daysCount; $i++) {
            // Сутки через двое (каждый третий день)
            if (($i - $firstWorkingDay) % 3 === 0) {
                $date = $startDate->copy()->addDays($i);
                $dateKey = $date->format('m-d');

                $schedule['dates'][$dateKey] = [
                    'working_hours' => $workingHours,
                    'breaks' => $breaks,
                ];
            }
            // Выходные дни - не добавляем в расписание
        }

        $this->info("Generated 24h-after-48h schedule: 24-hour shifts with {$breakCount} breaks");
        return $schedule;
    }

    /**
     * Генерирует перерывы для 24-часовой смены
     */
    private function generate24hBreaks(int $breakCount): array
    {
        $breaks = [];
        $totalBreakMinutes = 120; // 2 часа суммарно
        $breakDuration = (int)($totalBreakMinutes / $breakCount);

        // Равномерно распределяем перерывы по 24-часовой смене
        $shiftDuration = 24 * 60; // 24 часа в минутах
        $interval = (int)($shiftDuration / ($breakCount + 1));

        for ($i = 1; $i <= $breakCount; $i++) {
            $breakStartMinutes = $interval * $i;
            $breakEndMinutes = $breakStartMinutes + $breakDuration;

            // Преобразуем минуты в часы и минуты
            $breakStartHour = 8 + (int)($breakStartMinutes / 60); // Начинаем с 8:00
            $breakStartMinute = $breakStartMinutes % 60;

            $breakEndHour = 8 + (int)($breakEndMinutes / 60);
            $breakEndMinute = $breakEndMinutes % 60;

            // Обрабатываем переход через сутки
            $breakStartHour = $breakStartHour % 24;
            $breakEndHour = $breakEndHour % 24;

            $breaks[] = [
                'start' => sprintf('%02d:%02d', $breakStartHour, $breakStartMinute),
                'end' => sprintf('%02d:%02d', $breakEndHour, $breakEndMinute),
            ];
        }

        return $breaks;
    }
}
