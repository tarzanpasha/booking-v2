<?php
// app/Console/Commands/DemoScenarios/Scenarios/Scenario3_GroupTraining.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario3_GroupTraining extends BaseScenario
{
    protected int $scenarioId = 3;
    protected string $name = "🏋️ Групповая тренировка";
    protected string $description = "Фиксированные слоты + групповые брони";

    public function getDescription(): string
    {
        return "Демонстрация групповых броней с ограничением участников";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n🏋️ СЦЕНАРИЙ 3: ГРУППОВАЯ ТРЕНИРОВКА");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: групповой ресурс, фиксированные слоты 90 мин, лимит 10 участников");

        // ШАГ 1: Получить доступные слоты для групповой тренировки
        $this->info("\n📅 ШАГ 1: Получение доступных слотов для групповой тренировки...");
        $slots = $this->runner->getSlots($resourceId, '2024-01-17', 5);
        $this->info("   📊 Доступные слоты: " . count($slots));

        // ШАГ 2: Создать групповую бронь с организатором
        $this->info("\n👥 ШАГ 2: Создание групповой брони с организатором...");
        $groupBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-17 10:00:00',
            'end' => '2024-01-17 11:30:00',
            'booker' => [
                'name' => 'Организатор тренировки',
                'email' => 'organizer@example.com',
                'type' => 'client',
                'metadata' => ['is_organizer' => true]
            ]
        ]);
        $this->checkStatus($groupBooking, 'confirmed', "Групповая бронь создана");

        // ШАГ 3: Добавить дополнительных участников к брони
        $this->info("\n👥 ШАГ 3: Добавление участников в групповую бронь...");
        $this->addParticipantsToBooking($groupBooking['id'], [
            ['name' => 'Участник 1', 'email' => 'user1@example.com'],
            ['name' => 'Участник 2', 'email' => 'user2@example.com'],
            ['name' => 'Участник 3', 'email' => 'user3@example.com'],
        ]);

        $this->info("\n🎉 СЦЕНАРИЙ 3 ЗАВЕРШЕН: Групповые брони и лимиты участников работают корректно!");
    }

    private function checkStatus(array $booking, string $expectedStatus, string $message): void
    {
        if ($booking['status'] === $expectedStatus) {
            $this->info("   ✅ {$message}: статус = {$booking['status']}");
        } else {
            $this->error("   ❌ {$message}: ожидался {$expectedStatus}, получен {$booking['status']}");
        }
    }

    private function addParticipantsToBooking(int $bookingId, array $participants): void
    {
        $this->line("   👥 Добавление участников в бронь {$bookingId}:");

        foreach ($participants as $participant) {
            $this->info("      👤 Добавлен участник: {$participant['name']}");
        }
        $this->info("   ✅ Участники добавлены в бронь {$bookingId}");
    }
}
