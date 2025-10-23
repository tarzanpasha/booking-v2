<?php

namespace App\Console\Commands;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Models\Timetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateStaticTimetable extends Command
{
    protected $signature = 'timetable:generate-static {company_id}';
    protected $description = 'Generate static timetable with random parameters';

    public function handle(CreateOrUpdateCompanyAction $createOrUpdateCompanyAction): void
    {
        $companyId = $this->argument('company_id');

        // Создаем или получаем компанию
        $company = $createOrUpdateCompanyAction->execute($companyId);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $schedule = ['days' => []];

        foreach ($days as $day) {
            // Время начала: 8-10 утра
            $startHour = rand(8, 10);
            // Время окончания: 17-19 вечера, по пятницам чаще в 17
            $endHour = ($day === 'friday' && rand(1, 100) > 30) ? 17 : rand(17, 19);

            $workingHours = [
                'start' => sprintf('%02d:00', $startHour),
                'end' => sprintf('%02d:00', $endHour),
            ];

            $breaks = [];
            $breakCount = rand(0, 2); // Не более 2 перерывов

            for ($i = 0; $i < $breakCount; $i++) {
                // Перерыв не раньше чем через 3 часа после начала и не позже чем за 2 часа до конца
                $breakStartHour = rand($startHour + 3, $endHour - 3);
                $breakEndHour = min($breakStartHour + 1, $endHour - 2); // Перерыв 1 час

                $breaks[] = [
                    'start' => sprintf('%02d:00', $breakStartHour),
                    'end' => sprintf('%02d:00', $breakEndHour),
                ];
            }

            $schedule['days'][$day] = [
                'working_hours' => $workingHours,
                'breaks' => $breaks,
            ];
        }

        // Российские праздники
        $schedule['holidays'] = [
            '01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-08', // Новогодние каникулы
            '01-07', // Рождество
            '02-23', // День защитника Отечества
            '03-08', // Международный женский день
            '05-01', // Праздник Весны и Труда
            '05-09', // День Победы
            '06-12', // День России
            '11-04', // День народного единства
        ];

        $timetable = Timetable::create([
            'company_id' => $companyId,
            'type' => 'static',
            'schedule' => $schedule,
        ]);

        // Сохраняем пример в файл
        Storage::put('exports/static_timetable_example.json', json_encode($schedule, JSON_PRETTY_PRINT));

        $this->info("Static timetable created successfully for company {$companyId}");
        $this->info("Example saved to storage/app/exports/static_timetable_example.json");
    }
}
