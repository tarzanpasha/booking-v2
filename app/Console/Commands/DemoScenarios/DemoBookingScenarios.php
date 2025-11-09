<?php
// app/Console/Commands/DemoScenarios/DemoBookingScenarios.php

namespace App\Console\Commands\DemoScenarios;

use Illuminate\Console\Command;
use App\Console\Commands\DemoScenarios\Scenarios;

class DemoBookingScenarios extends Command
{
    protected $signature = 'demo:booking-scenarios
                            {scenario? : Конкретный сценарий для запуска (1-8)}
                            {--all : Запустить все сценарии}
                            {--list : Показать доступные сценарии}';

    protected $description = 'Запуск демонстрационных сценариев системы бронирования';

    public function handle(ScenarioRunnerService $runner): int
    {
        if ($this->option('list')) {
            return $this->showScenarios();
        }

        $this->info('🚀 Запуск демонстрации системы бронирования');
        $this->line('==================================================');

        $scenarios = $this->getScenariosToRun();

        foreach ($scenarios as $scenario) {
            $this->runScenario($scenario, $runner);
        }

        $this->info('🎉 Все сценарии завершены!');
        return 0;
    }

    /**
     * Показать список доступных сценариев с описанием
     */
    private function showScenarios(): int
    {
        $this->info('📋 ДОСТУПНЫЕ ДЕМОНСТРАЦИОННЫЕ СЦЕНАРИИ:');
        $this->line('');

        $scenarios = [
            1 => '💈 СЦЕНАРИЙ 1: Парикмахерская - Фиксированные слоты + автоматическое подтверждение',
            2 => '🏢 СЦЕНАРИЙ 2: Переговорная комната - Динамические слоты + ручное подтверждение',
            3 => '🏋️ СЦЕНАРИЙ 3: Групповая тренировка - Фиксированные слоты + групповые брони',
            4 => '💎 СЦЕНАРИЙ 4: Дорогое оборудование - Динамические слоты + строгие ограничения',
            5 => '🏨 СЦЕНАРИЙ 5: Гостиничный номер - Переходящие брони + разные стратегии',
            6 => '⚡ СЦЕНАРИЙ 6: Экстренный случай - Администратор vs Пользователь',
            7 => '💅 СЦЕНАРИЙ 7: Салон красоты - Статическое расписание с праздниками',
            8 => '🏢 СЦЕНАРИЙ 8: Бизнес-центр - Смешанное расписание + перерывы'
        ];

        foreach ($scenarios as $id => $description) {
            $this->line("  {$id}. {$description}");
        }

        $this->line("\n💡 Использование:");
        $this->line("  php artisan demo:booking-scenarios --all      # Запустить все сценарии");
        $this->line("  php artisan demo:booking-scenarios 1          # Запустить только сценарий 1");
        $this->line("  php artisan demo:booking-scenarios --list     # Показать этот список");

        return 0;
    }

    /**
     * Определить какие сценарии нужно запустить
     */
    private function getScenariosToRun(): array
    {
        if ($this->option('all')) {
            return range(1, 8);
        }

        $scenario = $this->argument('scenario');
        if ($scenario) {
            return [$scenario];
        }

        // Интерактивный выбор если не указаны параметры
        $choice = $this->choice(
            '🎯 Выберите сценарий для запуска:',
            [
                1 => '1. 💈 Парикмахерская (авто-подтверждение, фиксированные слоты)',
                2 => '2. 🏢 Переговорная (ручное подтверждение, динамические слоты)',
                3 => '3. 🏋️ Групповая тренировка (лимит участников, групповые брони)',
                4 => '4. 💎 Дорогое оборудование (строгие правила, подтверждение)',
                5 => '5. 🏨 Гостиничный номер (многодневные брони)',
                6 => '6. ⚡ Экстренный случай (админ vs пользователь)',
                7 => '7. 💅 Салон красоты (праздничные дни, перерывы)',
                8 => '8. 🏢 Бизнес-центр (сложное расписание, множественные перерывы)',
                'all' => 'ALL. 🚀 Запустить все сценарии'
            ],
            'all'
        );

        return $choice === 'all' ? range(1, 8) : [explode('.', $choice)[0]];
    }

    /**
     * Запуск конкретного сценария
     */
    private function runScenario(int $scenarioId, ScenarioRunnerService $runner): void
    {
        $this->info("\n🎬 ЗАПУСК СЦЕНАРИЯ {$scenarioId}");
        $this->line(str_repeat('─', 60));

        // Очистка данных предыдущего запуска
        $runner->cleanupScenarioData($scenarioId);

        // Настройка сценария
        $setupData = $runner->setupScenario($scenarioId);

        if (!$setupData) {
            $this->error("❌ Ошибка настройки сценария {$scenarioId}");
            return;
        }

        // Получение экземпляра сценария
        $scenario = $this->getScenarioInstance($scenarioId, $runner);

        if (!$scenario) {
            $this->error("❌ Сценарий {$scenarioId} не найден");
            return;
        }

        // Запуск сценария
        $scenario->run($setupData);

        $this->info("✅ Сценарий {$scenarioId} завершен");
    }

    /**
     * Получить экземпляр сценария по ID
     */
    private function getScenarioInstance(int $scenarioId, ScenarioRunnerService $runner)
    {
        $scenarios = [
            1 => new Scenarios\Scenario1_HairSalon($this, $runner, app(\App\Services\Booking\BookingService::class)),
            2 => new Scenarios\Scenario2_MeetingRoom($this, $runner, app(\App\Services\Booking\BookingService::class)),
            3 => new Scenarios\Scenario3_GroupTraining($this, $runner, app(\App\Services\Booking\BookingService::class)),
            4 => new Scenarios\Scenario4_ExpensiveEquipment($this, $runner, app(\App\Services\Booking\BookingService::class)),
            5 => new Scenarios\Scenario5_HotelRoom($this, $runner, app(\App\Services\Booking\BookingService::class)),
            6 => new Scenarios\Scenario6_EmergencyCase($this, $runner, app(\App\Services\Booking\BookingService::class)),
            7 => new Scenarios\Scenario7_BeautySalon($this, $runner, app(\App\Services\Booking\BookingService::class)),
            8 => new Scenarios\Scenario8_BusinessCenter($this, $runner, app(\App\Services\Booking\BookingService::class)),
        ];

        return $scenarios[$scenarioId] ?? null;
    }
}
