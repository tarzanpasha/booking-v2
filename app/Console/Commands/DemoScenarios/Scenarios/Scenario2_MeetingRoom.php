<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario2_MeetingRoom.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario2_MeetingRoom extends BaseScenario
{
    protected int $scenarioId = 2;
    protected string $name = "🏢 Переговорная комната";
    protected string $description = "Динамические слоты + ручное подтверждение";

    public function getDescription(): string
    {
        return "Демонстрация динамических слотов с ручным подтверждением";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n🏢 СЦЕНАРИЙ 2: ПЕРЕГОВОРНАЯ КОМНАТА");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: ручное подтверждение, динамические слоты 30 мин, разные права доступа");

        // ШАГ 1: Администратор создает бронь вне стандартного расписания
        $this->info("\n👨‍💼 ШАГ 1: Администратор создает бронь вне расписания...");
        $adminBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-16 10:00:00',
            'end' => '2024-01-16 11:30:00',
            'is_admin' => true,
            'booker' => ['name' => 'Администратор', 'type' => 'admin']
        ]);
        $this->checkStatus($adminBooking, 'confirmed', "Бронь администратора авто-подтверждена");

        // ШАГ 2: Пользователь создает бронь (требует подтверждения)
        $this->info("\n👤 ШАГ 2: Пользователь создает бронь (требует подтверждения)...");
        $userBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-16 13:00:00',
            'end' => '2024-01-16 14:00:00',
            'booker' => ['name' => 'Петр Сидоров', 'email' => 'peter@example.com']
        ]);
        $this->checkStatus($userBooking, 'pending', "Бронь пользователя ожидает подтверждения");

        // ШАГ 3: Проверить слоты с учетом pending брони
        $this->info("\n📅 ШАГ 3: Проверка слотов с учетом ожидающей брони...");
        $slots = $this->runner->getSlots($resourceId, '2024-01-16', 8);
        $this->info("   📊 Доступные слоты: " . count($slots));

        // ШАГ 4: Подтверждение брони администратором
        $this->info("\n✅ ШАГ 4: Подтверждение брони администратором...");
        $confirmedBooking = $this->runner->confirmBooking($userBooking['id']);
        $this->checkStatus($confirmedBooking, 'confirmed', "Бронь подтверждена администратором");

        $this->info("\n🎉 СЦЕНАРИЙ 2 ЗАВЕРШЕН: Динамические слоты и ручное подтверждение работают корректно!");
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
