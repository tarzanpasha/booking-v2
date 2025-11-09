<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario6_EmergencyCase.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario6_EmergencyCase extends BaseScenario
{
    protected int $scenarioId = 6;
    protected string $name = "⚡ Экстренный случай";
    protected string $description = "Администратор vs Пользователь";

    public function getDescription(): string
    {
        return "Демонстрация приоритета администратора над пользователем";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n⚡ СЦЕНАРИЙ 6: ЭКСТРЕННЫЙ СЛУЧАЙ");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: приоритет администратора, экстренные отмены, перепланирование");

        // ШАГ 1: Пользователь создает обычную бронь
        $this->info("\n👤 ШАГ 1: Пользователь создает обычную бронь...");
        $userBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-19 15:00:00',
            'end' => '2024-01-19 16:00:00',
            'is_admin' => false, // Явно указываем что это не админ
            'booker' => ['name' => 'Обычный пользователь', 'type' => 'client']
        ]);

        // Для сценария 2 требуется подтверждение, поэтому статус будет pending
        $expectedStatus = 'pending';
        $this->checkStatus($userBooking, $expectedStatus, "Бронь пользователя создана");

        // ШАГ 2: Администратор отменяет пользовательскую бронь
        $this->info("\n👨‍💼 ШАГ 2: Администратор отменяет пользовательскую бронь...");
        $cancelledBooking = $this->runner->cancelBooking($userBooking['id'], 'admin', 'Экстренная необходимость');
        $this->checkStatus($cancelledBooking, 'cancelled_by_admin', "Бронь пользователя отменена администратором");

        // ШАГ 3: Администратор создает экстренную бронь на то же время
        $this->info("\n👨‍💼 ШАГ 3: Администратор создает экстренную бронь...");
        $emergencyBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-19 15:00:00',
            'end' => '2024-01-19 16:00:00',
            'is_admin' => true, // Ключевой параметр для администратора
            'booker' => ['name' => 'Администратор', 'type' => 'admin']
        ]);
        $this->checkStatus($emergencyBooking, 'confirmed', "Экстренная бронь администратора создана");

        // ШАГ 4: Проверка что пользовательская бронь действительно отменена
        $this->info("\n🔍 ШАГ 4: Проверка статуса пользовательской брони...");
        $updatedUserBooking = $this->runner->getBooking($userBooking['id']);
        $this->checkStatus($updatedUserBooking, 'cancelled_by_admin', "Бронь пользователя отменена");

        $this->info("\n🎉 СЦЕНАРИЙ 6 ЗАВЕРШЕН: Приоритет администратора работает корректно!");
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
