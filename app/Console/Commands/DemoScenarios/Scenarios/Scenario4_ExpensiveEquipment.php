<?php

namespace App\Console\Commands\DemoScenarios\Scenarios;

class Scenario4_ExpensiveEquipment extends BaseScenario
{
    protected int $scenarioId = 4;
    protected string $name = "💎 Дорогое оборудование";
    protected string $description = "Динамические слоты + строгие ограничения";

    public function getDescription(): string
    {
        return "Демонстрация строгих ограничений для ценных ресурсов";
    }

    public function run(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n💎 СЦЕНАРИЙ 4: ДОРОГОЕ ОБОРУДОВАНИЕ");
        $this->line("🎯 Цель: " . $this->getDescription());
        $this->line("📋 Параметры: строгие ограничения, динамические слоты 120 мин, подтверждение обязательно");

        // ШАГ 1: Попытка брони без достаточного времени (должна быть ошибка)
        $this->info("\n❌ ШАГ 1: Попытка брони без достаточного времени...");
        try {
            $this->runner->createBooking([
                'resource_id' => $resourceId,
                'start' => now()->addMinutes(30)->format('Y-m-d H:i:s'),
                'end' => now()->addMinutes(150)->format('Y-m-d H:i:s'),
                'booker' => ['name' => 'Торопливый клиент']
            ]);
            $this->error("   🚨 НЕОЖИДАННО: Должно было быть ошибкой!");
            $this->runner->debug("Бронь создалась без ошибки, хотя min_advance_time = 2880 минут");
        } catch (\Exception $e) {
            $this->info("   ✅ Ожидаемая ошибка: " . $this->shortenErrorMessage($e->getMessage()));
        }

        // ШАГ 2: Корректная бронь с ожиданием подтверждения
        $this->info("\n⏳ ШАГ 2: Корректная бронь с ожиданием подтверждения...");
        $pendingBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-18 10:00:00',
            'end' => '2024-01-18 12:00:00',
            'booker' => ['name' => 'Серьезный клиент', 'email' => 'serious@example.com']
        ]);
        $this->checkStatus($pendingBooking, 'pending', "Бронь ожидает подтверждения");

        // ШАГ 3: Отклонение брони администратором
        $this->info("\n❌ ШАГ 3: Отклонение брони администратором...");
        $rejectedBooking = $this->runner->cancelBooking($pendingBooking['id'], 'admin', 'Оборудование на обслуживании');
        $this->checkStatus($rejectedBooking, 'cancelled_by_admin', "Бронь отклонена администратором");

        // ШАГ 4: Бронь администратором с обходом ограничений
        $this->info("\n👨‍💼 ШАГ 4: Бронь администратором с обходом ограничений...");

        $adminStart = '2024-01-18 10:00:00';
        $adminEnd = '2024-01-18 12:00:00';

        $adminBooking = $this->runner->createBooking([
            'resource_id' => $resourceId,
            'start' => $adminStart,
            'end' => $adminEnd,
            'is_admin' => true,
            'booker' => ['name' => 'Администратор', 'type' => 'admin']
        ]);
        $this->checkStatus($adminBooking, 'confirmed', "Бронь администратора подтверждена");

        // ШАГ 5: Попытка отмены в последний момент (должна быть ошибка)
        $this->info("\n❌ ШАГ 5: Попытка отмены в последний момент...");
        try {
            $result = $this->runner->cancelBooking($adminBooking['id'], 'client', 'Срочные обстоятельства');

            // Проверяем результат отмены
            if (isset($result['status']) && $result['status'] === 'cancelled_by_client') {
                $this->error("   🚨 НЕОЖИДАННО: Отмена прошла успешно, хотя должна была быть ошибкой!");
            } else {
                $this->error("   🚨 НЕОЖИДАННО: Отмена не вызвала ошибку!");
            }
        } catch (\Exception $e) {
            $this->info("   ✅ Ожидаемая ошибка: " . $this->shortenErrorMessage($e->getMessage()));
        }

        $this->info("\n🎉 СЦЕНАРИЙ 4 ЗАВЕРШЕН: Строгие ограничения для ценных ресурсов работают корректно!");
    }

    private function checkStatus(array $booking, string $expectedStatus, string $message): void
    {
        if ($booking['status'] === $expectedStatus) {
            $this->info("   ✅ {$message}: статус = {$booking['status']}");
        } else {
            $this->error("   ❌ {$message}: ожидался {$expectedStatus}, получен {$booking['status']}");
        }
    }

    /**
     * Сокращает длинные сообщения об ошибках для лучшего отображения
     */
    private function shortenErrorMessage(string $message): string
    {
        if (strlen($message) > 80) {
            return substr($message, 0, 77) . '...';
        }
        return $message;
    }
}
