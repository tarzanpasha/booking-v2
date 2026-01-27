<?php

namespace App\Console\Commands;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Models\Timetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Data\WorkingHoursTemplates;

class GenerateStaticTimetable extends Command
{
    protected $signature = 'timetable:generate-static {company_id}';
    protected $description = 'Generate static timetable with random parameters';

    public function handle(CreateOrUpdateCompanyAction $createOrUpdateCompanyAction): void
    {
        $companyId = $this->argument('company_id');

        // Создаем или получаем компанию
        $company = $createOrUpdateCompanyAction->execute($companyId);

        // Выбираем один из двух вариантов рабочих дней
        $workingDaysPattern = $this->selectWorkingDaysPattern();

        // Выбираем один из трех вариантов рабочего времени
        $workingHoursTemplate = $this->selectWorkingHoursTemplate();

        // Генерируем перерывы один раз для всего расписания
        $breaksTemplate = $this->generateBreaksTemplate($workingHoursTemplate['regular']);

        $schedule = ['days' => []];

        // Определяем все дни недели
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($allDays as $day) {
            // Проверяем, является ли день рабочим в выбранном паттерне
            if (in_array($day, $workingDaysPattern)) {
                // Определяем рабочие часы для дня
                if (in_array($day, ['friday', 'saturday'])) {
                    // Пятница и суббота - на час раньше
                    $workingHours = $workingHoursTemplate['early'];
                } else {
                    // Остальные рабочие дни - обычное время
                    $workingHours = $workingHoursTemplate['regular'];
                }

                $schedule['days'][$day] = [
                    'working_hours' => $workingHours,
                    'breaks' => $breaksTemplate,
                ];
            }
            // Нерабочие дни не добавляем в расписание
        }

        // Российские праздники
        $schedule['holidays'] = WorkingHoursTemplates::getRussianHolidays();

        $timetable = Timetable::create([
            'company_id' => $companyId,
            'type' => 'static',
            'schedule' => $schedule,
        ]);

        // Сохраняем пример в файл
        Storage::put('exports/static_timetable_example.json', json_encode($schedule, JSON_PRETTY_PRINT));

        $this->info("Static timetable created successfully for company {$companyId}");
        $this->info("Working days pattern: " . implode(', ', $workingDaysPattern));
        $this->info("Working hours: {$workingHoursTemplate['regular']['start']} - {$workingHoursTemplate['regular']['end']}");
        $this->info("Breaks count: " . count($breaksTemplate));
        $this->info("Example saved to storage/app/exports/static_timetable_example.json");
    }

    /**
     * Выбирает паттерн рабочих дней
     */
    private function selectWorkingDaysPattern(): array
    {
        $patterns = WorkingHoursTemplates::getWorkingDaysPatterns();
        return $patterns[array_rand($patterns)];
    }

    /**
     * Выбирает шаблон рабочих часов
     */
    private function selectWorkingHoursTemplate(): array
    {
        $templates = WorkingHoursTemplates::getWorkingHoursTemplates();
        return $templates[array_rand($templates)];
    }

    /**
     * Генерирует шаблон перерывов для всего расписания
     */
    private function generateBreaksTemplate(array $workingHours): array
    {
        $breaks = [];

        // Парсим время начала и окончания
        $startHour = (int)explode(':', $workingHours['start'])[0];
        $endHour = (int)explode(':', $workingHours['end'])[0];

        // Определяем количество перерывов
        $breakCount = $this->getBreakCount();

        if ($breakCount === 0) {
            return $breaks;
        }

        if ($breakCount === 1) {
            // Один перерыв продолжительностью 60 минут
            $breakStart = $startHour + 3; // Не менее 3 часов после начала
            $maxBreakStart = $endHour - 4; // Не менее 3 часов до конца (60 мин перерыв + 3 часа работы)

            if ($breakStart <= $maxBreakStart) {
                $breakStart = rand($breakStart, $maxBreakStart);
                $breaks[] = [
                    'start' => sprintf('%02d:00', $breakStart),
                    'end' => sprintf('%02d:00', $breakStart + 1),
                ];
            }
        } else {
            // Два перерыва по 30 минут каждый
            $minGapBetweenBreaks = 3; // 3 часа между перерывами
            $minEdgeGap = 2.5; // 2.5 часа от краев

            $firstBreakStart = $startHour + $minEdgeGap;
            $secondBreakEnd = $endHour - $minEdgeGap;

            // Проверяем, достаточно ли времени для двух перерывов
            $availableTime = $secondBreakEnd - $firstBreakStart - 1; // 1 час на оба перерыва
            if ($availableTime >= $minGapBetweenBreaks) {
                $firstBreakStart = rand(
                    (int)$firstBreakStart,
                    (int)($secondBreakEnd - $minGapBetweenBreaks - 1)
                );

                $secondBreakStart = $firstBreakStart + 0.5 + $minGapBetweenBreaks; // 30 мин первый перерыв + 3 часа
                $secondBreakStart = min($secondBreakStart, $endHour - $minEdgeGap - 0.5);

                if ($secondBreakStart <= $endHour - $minEdgeGap - 0.5) {
                    $breaks[] = [
                        'start' => sprintf('%02d:00', $firstBreakStart),
                        'end' => sprintf('%02d:30', $firstBreakStart),
                    ];
                    $breaks[] = [
                        'start' => sprintf('%02d:00', $secondBreakStart),
                        'end' => sprintf('%02d:30', $secondBreakStart),
                    ];
                }
            }
        }

        return $breaks;
    }

    /**
     * Определяет количество перерывов с заданными вероятностями
     */
    private function getBreakCount(): int
    {
        $random = rand(1, 100);

        if ($random <= 40) {
            return 1;
        } else {
            return 2;
        }
    }
}
