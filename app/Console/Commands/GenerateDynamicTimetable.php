<?php

namespace App\Console\Commands;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Models\Timetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateDynamicTimetable extends Command
{
    protected $signature = 'timetable:generate-dynamic {company_id} {days=30}';
    protected $description = 'Generate dynamic timetable for specified period';

    public function handle(CreateOrUpdateCompanyAction $createOrUpdateCompanyAction): void
    {
        $companyId = $this->argument('company_id');
        $daysCount = $this->argument('days');

        // Создаем или получаем компанию
        $company = $createOrUpdateCompanyAction->execute($companyId);

        $schedule = ['dates' => []];
        $startDate = now();

        for ($i = 0; $i < $daysCount; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateKey = $date->format('m-d');

            // Время начала: 2-23 часа
            $startHour = rand(2, 23);
            // Время окончания: минимум 1 час работы, максимум до 23:59
            $endHour = min($startHour + rand(1, 8), 23);

            $workingHours = [
                'start' => sprintf('%02d:00', $startHour),
                'end' => sprintf('%02d:00', $endHour),
            ];

            $breaks = [];
            $breakCount = rand(0, 2); // Не более 2 перерывов

            for ($j = 0; $j < $breakCount; $j++) {
                // Перерыв не раньше чем через 1 час после начала и не позже чем за 1 час до конца
                $breakStartHour = rand($startHour + 1, $endHour - 1);
                $breakEndHour = min($breakStartHour + 1, $endHour - 1); // Перерыв 1 час

                $breaks[] = [
                    'start' => sprintf('%02d:00', $breakStartHour),
                    'end' => sprintf('%02d:00', $breakEndHour),
                ];
            }

            $schedule['dates'][$dateKey] = [
                'working_hours' => $workingHours,
                'breaks' => $breaks,
            ];
        }

        $timetable = Timetable::create([
            'company_id' => $companyId,
            'type' => 'dynamic',
            'schedule' => $schedule,
        ]);

        // Сохраняем пример в файл
        Storage::put('exports/dynamic_timetable_example.json', json_encode($schedule, JSON_PRETTY_PRINT));

        $this->info("Dynamic timetable created successfully for company {$companyId}");
        $this->info("Generated {$daysCount} days schedule");
        $this->info("Example saved to storage/app/exports/dynamic_timetable_example.json");
    }
}
