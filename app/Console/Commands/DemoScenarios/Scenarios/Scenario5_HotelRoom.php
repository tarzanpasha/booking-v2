<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario5_HotelRoom.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario5_HotelRoom extends BaseScenario
{
    protected int $scenarioId = 5;
    protected string $name = "🏨 Гостиничный номер";
    protected string $description = "Переходящие брони + разные стратегии";

    public function getDescription(): string
    {
        return "Демонстрация многодневных (переходящих) броней";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n🏨 СЦЕНАРИЙ 5: ГОСТИНИЧНЫЙ НОМЕР");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: переходящие брони, многодневные, фиксированные слоты 24 часа");

        // ШАГ 1: Бронь на 3 дня
        $this->info("\n📅 ШАГ 1: Бронь номера на 3 дня...");
        $threeDayBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-20 14:00:00',
            'end' => '2024-01-23 12:00:00',
            'booker' => ['name' => 'Гость отеля', 'email' => 'guest@example.com']
        ]);
        $this->checkStatus($threeDayBooking, 'confirmed', "Бронь на 3 дня создана");

        // ШАГ 2: Попытка брони в пересекающиеся даты (должна быть ошибка)
        $this->info("\n❌ ШАГ 2: Попытка брони в пересекающиеся даты...");
        try {
            $this->runner->createBooking([
                'resource_id' => $resourceId,
                'start' => '2024-01-22 10:00:00',
                'end' => '2024-01-24 12:00:00',
                'booker' => ['name' => 'Конфликтный гость']
            ]);
            $this->error("   🚨 НЕОЖИДАННО: Должно было быть ошибкой!");
        } catch (\Exception $e) {
            $this->info("   ✅ Ожидаемая ошибка: {$e->getMessage()}");
        }

        // ШАГ 3: Бронь сразу после освобождения номера
        $this->info("\n✅ ШАГ 3: Бронь сразу после освобождения номера...");
        $nextBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-23 14:00:00',
            'end' => '2024-01-25 12:00:00',
            'booker' => ['name' => 'Следующий гость']
        ]);
        $this->checkStatus($nextBooking, 'confirmed', "Бронь после освобождения создана");

        $this->info("\n🎉 СЦЕНАРИЙ 5 ЗАВЕРШЕН: Многодневные брони работают корректно!");
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
