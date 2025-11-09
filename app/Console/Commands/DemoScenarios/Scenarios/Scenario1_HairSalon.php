<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario1_HairSalon.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario1_HairSalon extends BaseScenario
{
    protected int $scenarioId = 1;
    protected string $name = "💈 Парикмахерская";
    protected string $description = "Фиксированные слоты + автоматическое подтверждение";

    public function getDescription(): string
    {
        return "Демонстрация фиксированных слотов с автоматическим подтверждением";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n💈 СЦЕНАРИЙ 1: ПАРИКМАХЕРСКАЯ");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: авто-подтверждение, фиксированные слоты 60 мин, перерыв 13:15-14:15");

        // ШАГ 1: Получить доступные слоты для отображения пользователю
        $this->info("\n📅 ШАГ 1: Получение доступных слотов...");
        $slots = $this->runner->getSlots($resourceId, '2024-01-15', 8);
        $this->info("   📊 Доступные слоты: " . count($slots));
        $this->info("   🕒 Примеры: " . implode(', ', array_slice($slots, 0, 3)));

        // ШАГ 2: Создать бронь на последний слот до перерыва
        $this->info("\n✅ ШАГ 2: Бронь последнего слота до перерыва...");
        $booking1 = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-15 12:00:00',
            'end' => '2024-01-15 13:00:00',
            'booker' => ['name' => 'Анна Иванова', 'email' => 'anna@example.com']
        ]);
        $this->checkStatus($booking1, 'confirmed', "Бронь авто-подтверждена");

        // ШАГ 3: Попытка брони с пересечением перерыва (должна быть ошибка)
        $this->info("\n❌ ШАГ 3: Попытка брони с пересечением перерыва...");
        try {
            $this->runner->createBooking([
                'resource_id' => $resourceId,
                'start' => '2024-01-15 12:45:00',
                'end' => '2024-01-15 13:45:00',
                'booker' => ['name' => 'Конфликтный клиент']
            ]);
            $this->error("   🚨 НЕОЖИДАННО: Должно было быть ошибкой!");
        } catch (\Exception $e) {
            $this->info("   ✅ Ожидаемая ошибка: {$e->getMessage()}");
        }

        // ШАГ 4: Бронь первого слота после перерыва
        $this->info("\n✅ ШАГ 4: Бронь первого слота после перерыва...");
        $booking2 = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-15 14:15:00',
            'end' => '2024-01-15 15:15:00',
            'booker' => ['name' => 'Петр Сидоров']
        ]);
        $this->checkStatus($booking2, 'confirmed', "Бронь после перерыва создана");

        // ШАГ 5: Отмена брони клиентом
        $this->info("\n🔄 ШАГ 5: Отмена брони клиентом...");
        $canceledBooking = $this->runner->cancelBooking($booking1['id'], 'client', 'Планы изменились');
        $this->checkStatus($canceledBooking, 'cancelled_by_client', "Бронь отменена клиентом");

        $this->info("\n🎉 СЦЕНАРИЙ 1 ЗАВЕРШЕН: Все функции фиксированных слотов работают корректно!");
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
