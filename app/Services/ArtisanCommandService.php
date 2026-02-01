<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ArtisanCommandService
{
    /**
     * Выполняет команду генерации статического расписания и возвращает данные
     */
    public function generateStaticTimetable(int $companyId): array
    {
        // Выполняем команду
        Artisan::call('timetable:generate-static', [
            'company_id' => $companyId
        ]);

        // Получаем сгенерированные данные из файла
        $filePath = storage_path('app/exports/static_timetable_example.json');
        if (!file_exists($filePath)) {
            throw new \Exception('Static timetable example file not found');
        }

        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }

    /**
     * Выполняет команду генерации динамического расписания и возвращает данные
     */
    public function generateDynamicTimetable(int $companyId, int $days = 30): array
    {
        // Выполняем команду
        Artisan::call('timetable:generate-dynamic', [
            'company_id' => $companyId,
            'days' => $days
        ]);

        // Получаем сгенерированные данные из файла
        $filePath = storage_path('app/exports/dynamic_timetable_example.json');
        if (!file_exists($filePath)) {
            throw new \Exception('Dynamic timetable example file not found');
        }

        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }

    /**
     * Получает информацию о сгенерированном расписании
     */
    public function getTimetableInfo(array $timetableData, string $type): array
    {
        if ($type === 'static') {
            $workingDays = count($timetableData['days'] ?? []);
            $totalBreaks = 0;

            foreach ($timetableData['days'] ?? [] as $day) {
                $totalBreaks += count($day['breaks'] ?? []);
            }

            return [
                'working_days' => $workingDays,
                'total_breaks' => $totalBreaks,
                'holidays' => count($timetableData['holidays'] ?? [])
            ];
        } else {
            $workingDays = count($timetableData['dates'] ?? []);
            $totalBreaks = 0;

            foreach ($timetableData['dates'] ?? [] as $date) {
                $totalBreaks += count($date['breaks'] ?? []);
            }

            return [
                'working_days' => $workingDays,
                'total_breaks' => $totalBreaks,
                'schedule_types' => array_unique(array_column($timetableData['dates'] ?? [], 'schedule_type'))
            ];
        }
    }
}
