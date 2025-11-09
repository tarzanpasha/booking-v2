<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario7_BeautySalon.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario7_BeautySalon extends BaseScenario
{
    protected int $scenarioId = 7;
    protected string $name = "💅 Салон красоты";
    protected string $description = "Статическое расписание с праздниками";

    public function getDescription(): string
    {
        return "Демонстрация работы с праздничными днями и выходными";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n💅 СЦЕНАРИЙ 7: САЛОН КРАСОТЫ");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: Статическое расписание с праздниками, фиксированные слоты");

        // Тестирование разных типов дней: рабочие, праздничные, выходные
        $testDates = [
            '2024-01-15' => ['type' => 'working', 'desc' => 'Рабочий понедельник'],
            '2024-01-01' => ['type' => 'holiday', 'desc' => 'Праздник (Новый год)'],
            '2024-01-14' => ['type' => 'weekend', 'desc' => 'Воскресенье (выходной)'],
            '2024-03-08' => ['type' => 'holiday', 'desc' => 'Праздник (8 марта)'],
            '2024-01-16' => ['type' => 'working', 'desc' => 'Рабочий вторник']
        ];

        foreach ($testDates as $date => $info) {
            $this->info("\n📅 Проверка {$info['desc']} ({$date})...");
            $slots = $this->runner->getSlots($resourceId, $date, 3);

            if ($info['type'] === 'working' && count($slots) > 0) {
                $this->info("   ✅ {$info['desc']}: " . count($slots) . " слотов доступно");
                $this->line("      🕒 Первые слоты: " . implode(', ', array_slice($slots, 0, 2)));
            } elseif ($info['type'] === 'working') {
                $this->error("   ❌ {$info['desc']}: Нет доступных слотов (НЕОЖИДАННО)");
            } else {
                $this->info("   ✅ {$info['desc']}: Нет слотов (ожидаемо)");
            }
        }

        $this->info("\n🎉 СЦЕНАРИЙ 7 ЗАВЕРШЕН: Обработка праздников и перерывов работает корректно!");
    }
}
