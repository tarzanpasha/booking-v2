<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario8_BusinessCenter.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario8_BusinessCenter extends BaseScenario
{
    protected int $scenarioId = 8;
    protected string $name = "🏢 Бизнес-центр";
    protected string $description = "Смешанное расписание + перерывы";

    public function getDescription(): string
    {
        return "Демонстрация работы со сложным расписанием с множественными перерывами";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n🏢 СЦЕНАРИЙ 8: БИЗНЕС-ЦЕНТР");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: Сложное расписание с множественными перерывами, динамические слоты");

        // ШАГ 1: Получить слоты в день со сложным расписанием
        $this->info("\n📅 ШАГ 1: Получение слотов в день со сложным расписанием...");
        $slots = $this->runner->getSlots($resourceId, '2024-01-22', 10);
        $this->info("   📊 Доступные слоты: " . count($slots));
        $this->info("   🕒 Примеры слотов: " . implode(', ', array_slice($slots, 0, 5)));

        // ШАГ 2: Создать бронь между перерывами
        $this->info("\n✅ ШАГ 2: Создание брони между перерывами...");
        $betweenBreaksBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-22 13:30:00',
            'end' => '2024-01-22 15:00:00',
            'booker' => ['name' => 'Бизнес-встреча']
        ]);
        $this->checkStatus($betweenBreaksBooking, 'pending', "Бронь между перерывами создана");

        // ШАГ 3: Подтверждение брони администратором
        $this->info("\n✅ ШАГ 3: Подтверждение брони администратором...");
        $confirmedBooking = $this->runner->confirmBooking($betweenBreaksBooking['id']);
        $this->checkStatus($confirmedBooking, 'confirmed', "Бронь подтверждена");

        // ШАГ 4: Проверка доступности в праздничный день
        $this->info("\n🎄 ШАГ 4: Проверка доступности в праздничный день...");
        $holidaySlots = $this->runner->getSlots($resourceId, '2024-01-01', 5);
        if (count($holidaySlots) === 0) {
            $this->info("   ✅ В праздничный день слотов нет (ожидаемо)");
        } else {
            $this->error("   ❌ В праздничный день есть слоты (НЕОЖИДАННО)");
        }

        $this->info("\n🎉 СЦЕНАРИЙ 8 ЗАВЕРШЕН: Сложное расписание с множественными перерывами работает корректно!");
    }

    private function checkStatus(array $booking, string $expectedStatus, string $message): void
    {
        if ($booking['status'] === $expectedStatus) {
            $this->info("   ✅ {$message}: статус = {$booking['status']}");
        } else {
            $this->error("   ❌ {$message}: ожидался {$expectedStatus}, получен {$booking['status']}");
        }
    }
}
