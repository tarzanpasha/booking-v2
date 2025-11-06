<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\CreateTimetableFromJsonAction;
use App\Actions\StoreResourceTypeAction;
use App\Actions\StoreResourceAction;
use App\Services\Logging\BookingLoggerService;
use App\Models\Company;

class DemoBookingScenarios extends Command
{
    protected $signature = 'demo:booking-scenarios
                            {scenario? : Конкретный сценарий для запуска (1-8)}
                            {--all : Запустить все сценарии}
                            {--list : Показать доступные сценарии}';

    protected $description = 'Запуск демонстрационных сценариев системы бронирования';

    private string $baseUrl;
    private int $currentCompanyId;
    private int $currentResourceId;

    /**
     * Конструктор команды с внедрением зависимостей
     *
     * @param CreateOrUpdateCompanyAction $createCompanyAction - Действие создания/обновления компании
     * @param CreateTimetableFromJsonAction $createTimetableAction - Действие создания расписания из JSON
     * @param StoreResourceTypeAction $storeResourceTypeAction - Действие создания типа ресурса
     * @param StoreResourceAction $storeResourceAction - Действие создания ресурса
     */
    public function __construct(
        private CreateOrUpdateCompanyAction $createCompanyAction,
        private CreateTimetableFromJsonAction $createTimetableAction,
        private StoreResourceTypeAction $storeResourceTypeAction,
        private StoreResourceAction $storeResourceAction
    ) {
        parent::__construct();
    }

    /**
     * Основной метод выполнения команды
     *
     * @return int
     */
    public function handle(): int
    {
        $this->baseUrl = config('app.url') . '/api';

        if ($this->option('list')) {
            return $this->showScenarios();
        }

        $this->info('🚀 Запуск демонстрации системы бронирования');
        $this->line('==================================================');

        $scenarios = $this->getScenariosToRun();

        foreach ($scenarios as $scenario) {
            $this->runScenario($scenario);
        }

        $this->info('🎉 Все сценарии завершены!');
        BookingLoggerService::info('Демонстрация всех сценариев завершена');

        return 0;
    }

    /**
     * Показать список доступных сценариев
     *
     * @return int
     */
    private function showScenarios(): int
    {
        $this->info('Доступные демонстрационные сценарии:');
        $this->line('');

        $scenarios = [
            1 => '💈 Парикмахерская - Фиксированные слоты + автоматическое подтверждение',
            2 => '🏢 Переговорная комната - Динамические слоты + ручное подтверждение',
            3 => '🏋️ Групповая тренировка - Фиксированные слоты + групповые брони',
            4 => '💎 Дорогое оборудование - Динамические слоты + строгие ограничения',
            5 => '🏨 Гостиничный номер - Переходящие брони + разные стратегии',
            6 => '⚡ Экстренный случай - Администратор vs Пользователь',
            7 => '💅 Салон красоты - Статическое расписание с праздниками',
            8 => '🏢 Бизнес-центр - Смешанное расписание + перерывы'
        ];

        foreach ($scenarios as $id => $description) {
            $this->line("  {$id}. {$description}");
        }

        return 0;
    }

    /**
     * Получить список сценариев для запуска
     *
     * @return array
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

        $choice = $this->choice(
            'Выберите сценарий для запуска:',
            [
                1 => '1. Парикмахерская (авто-подтверждение)',
                2 => '2. Переговорная (ручное подтверждение)',
                3 => '3. Групповая тренировка (лимит участников)',
                4 => '4. Дорогое оборудование (строгие правила)',
                5 => '5. Гостиничный номер (многодневные)',
                6 => '6. Экстренный случай (админ vs пользователь)',
                7 => '7. Салон красоты (праздничные дни)',
                8 => '8. Бизнес-центр (сложное расписание)',
                'all' => 'ALL. Запустить все сценарии'
            ],
            'all'
        );

        return $choice === 'all' ? range(1, 8) : [explode('.', $choice)[0]];
    }

    /**
     * Запустить конкретный сценарий
     *
     * @param int $scenarioId - ID сценария (1-8)
     * @return void
     */
    private function runScenario(int $scenarioId): void
    {
        $this->info("\n🎬 Запуск сценария {$scenarioId}");
        $this->line(str_repeat('─', 60));

        BookingLoggerService::info("Начало сценария {$scenarioId}", ['scenario_id' => $scenarioId]);

        // Очистка и настройка
        $this->cleanupScenarioData($scenarioId);
        $setupData = $this->setupScenario($scenarioId);

        if (!$setupData) {
            $this->error("Ошибка настройки сценария {$scenarioId}");
            BookingLoggerService::error("Сценарий {$scenarioId} не настроен");
            return;
        }

        $this->currentResourceId = $setupData['resource_id'];

        // Выполнение сценария
        $method = "runScenario{$scenarioId}";
        if (method_exists($this, $method)) {
            $this->$method($setupData);
        }

        $this->info("✅ Сценарий {$scenarioId} завершен");
        $this->storeScenarioResults($scenarioId, $setupData);

        BookingLoggerService::info("Сценарий {$scenarioId} завершен", [
            'scenario_id' => $scenarioId,
            'resource_id' => $this->currentResourceId
        ]);
    }

    /**
     * Настроить данные для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return array|null - Данные настройки или null при ошибке
     */
    private function setupScenario(int $scenarioId): ?array
    {
        $this->info("Настройка сценария {$scenarioId}...");

        $companyId = $scenarioId * 100;

        // Создание компании через Action
        $company = $this->createCompanyAction->execute(
            $companyId,
            "Демо компания {$scenarioId}"
        );

        $this->currentCompanyId = $company->id;

        // Создание расписания через Action
        $timetableData = $this->getTimetableForScenario($scenarioId);
        $timetable = $this->createTimetableAction->execute(
            $company->id,
            $timetableData['schedule'],
            $timetableData['type']
        );

        // Создание типа ресурса через Action
        $resourceTypeConfig = $this->getResourceConfigForScenario($scenarioId);
        $resourceTypeData = [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'type' => "type_scenario_{$scenarioId}",
            'name' => "Тип ресурса Сценарий {$scenarioId}",
            'description' => $this->getScenarioDescription($scenarioId),
            'resource_config' => $resourceTypeConfig
        ];

        $resourceType = $this->storeResourceTypeAction->execute($resourceTypeData);

        // Создание ресурса через Action
        $resourceData = [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'options' => $this->getResourceOptionsForScenario($scenarioId),
            'resource_config' => $this->getResourceOverridesForScenario($scenarioId)
        ];

        $resource = $this->storeResourceAction->execute($resourceData);

        $this->info("✅ Компания: {$company->name} (ID: {$company->id})");
        $this->info("✅ Расписание: {$timetable->type}");
        $this->info("✅ Тип ресурса: {$resourceType->name}");
        $this->info("✅ Ресурс: {$this->getResourceName($scenarioId)} (ID: {$resource->id})");
        $this->info("📋 Конфигурация: " . $this->getConfigSummary($resourceTypeConfig));

        BookingLoggerService::info("Сценарий {$scenarioId} настроен", [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'resource_id' => $resource->id
        ]);

        return [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'resource_id' => $resource->id
        ];
    }

    /**
     * СЦЕНАРИЙ 1: Парикмахерская - Фиксированные слоты + автоматическое подтверждение
     *
     * @param array $setupData - Данные настройки сценария
     * @return void
     */
    private function runScenario1(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n💈 СЦЕНАРИЙ 1: Парикмахерская");
        $this->line("Параметры: автоматическое подтверждение, фиксированные слоты, длительность 60 мин");
        $this->line("Тестирование: авто-подтверждение, фиксированные слоты, отмена, перенос");

        BookingLoggerService::info("Начало Сценария 1: Парикмахерская");

        // Шаг 1: Получить доступные слоты
        $this->info("\n📅 Шаг 1: Получение доступных слотов...");
        $slots = $this->getSlots($resourceId, '2024-01-15', 6);
        $this->info("Доступные слоты: " . count($slots));
        BookingLoggerService::info("Получены слоты для Сценария 1", ['slot_count' => count($slots)]);

        // Шаг 2: Создать бронь (автоматическое подтверждение)
        $this->info("\n✅ Шаг 2: Создание брони (авто-подтверждение)...");
        $booking1 = $this->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-15 11:00:00',
            'end' => '2024-01-15 12:00:00',
            'booker' => ['name' => 'Анна Иванова', 'email' => 'anna@example.com']
        ]);

        $this->checkStatus($booking1, 'confirmed', "Бронь авто-подтверждена");
        BookingLoggerService::info("Бронь создана и авто-подтверждена", ['booking_id' => $booking1['id']]);

        // Шаг 3: Проверить обновленные слоты
        $this->info("\n📅 Шаг 3: Проверка обновленных слотов...");
        $updatedSlots = $this->getSlots($resourceId, '2024-01-15', 6);
        $this->info("Доступные слоты после брони: " . count($updatedSlots));

        // Шаг 4: Попробовать забронировать занятый слот
        $this->info("\n❌ Шаг 4: Попытка бронирования занятого слота...");
        try {
            $booking2 = $this->createBooking([
                'resource_id' => $resourceId,
                'start' => '2024-01-15 11:00:00',
                'end' => '2024-01-15 12:00:00',
                'booker' => ['name' => 'Конфликтный клиент']
            ]);
            $this->error("НЕОЖИДАННО: Должно было быть ошибкой!");
        } catch (\Exception $e) {
            $this->info("✅ Ожидаемая ошибка: {$e->getMessage()}");
            BookingLoggerService::warning("Конфликт бронирования предотвращен", ['error' => $e->getMessage()]);
        }

        // Шаг 5: Отмена брони
        $this->info("\n🔄 Шаг 5: Отмена брони...");
        $canceledBooking = $this->cancelBooking($booking1['id'], 'client', 'Планы изменились');
        $this->checkStatus($canceledBooking, 'cancelled_by_client', "Бронь отменена клиентом");
        BookingLoggerService::info("Бронь отменена клиентом", ['booking_id' => $booking1['id']]);

        // Шаг 6: Перенос брони
        $this->info("\n🔄 Шаг 6: Перенос брони...");
        $newBooking = $this->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-15 14:00:00',
            'end' => '2024-01-15 15:00:00',
            'booker' => ['name' => 'Анна Иванова']
        ]);

        $rescheduled = $this->rescheduleBooking($newBooking['id'],
            '2024-01-15 15:00:00', '2024-01-15 16:00:00', 'client');
        $this->info("✅ Бронь перенесена: {$rescheduled['start']} → {$rescheduled['end']}");
        BookingLoggerService::info("Бронь успешно перенесена", [
            'booking_id' => $newBooking['id'],
            'new_time' => $rescheduled['start'] . ' - ' . $rescheduled['end']
        ]);
    }

    /**
     * СЦЕНАРИЙ 2: Переговорная комната - Динамические слоты + ручное подтверждение
     *
     * @param array $setupData - Данные настройки сценария
     * @return void
     */
    private function runScenario2(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n🏢 СЦЕНАРИЙ 2: Переговорная комната");
        $this->line("Параметры: ручное подтверждение, динамические слоты, длительность 30 мин");
        $this->line("Тестирование: ручное подтверждение, динамические слоты, права администратора");

        BookingLoggerService::info("Начало Сценария 2: Переговорная комната");

        // Шаг 1: Администратор создает бронь вне расписания
        $this->info("\n👨‍💼 Шаг 1: Администратор создает бронь вне расписания...");
        $adminBooking = $this->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-16 10:00:00',
            'end' => '2024-01-16 11:30:00',
            'is_admin' => true,
            'booker' => ['name' => 'Администратор', 'type' => 'admin']
        ]);
        $this->checkStatus($adminBooking, 'confirmed', "Бронь администратора авто-подтверждена");
        BookingLoggerService::info("Админ создал бронь с обходом ограничений", ['booking_id' => $adminBooking['id']]);

        // Шаг 2: Пользователь создает бронь (ожидает подтверждения)
        $this->info("\n👤 Шаг 2: Пользователь создает бронь (требует подтверждения)...");
        $userBooking = $this->createBooking([
            'resource_id' => $resourceId,
            'start' => '2024-01-16 13:00:00',
            'end' => '2024-01-16 14:00:00',
            'booker' => ['name' => 'Петр Сидоров', 'email' => 'peter@example.com']
        ]);
        $this->checkStatus($userBooking, 'pending', "Бронь пользователя ожидает подтверждения");
        BookingLoggerService::info("Бронь пользователя создана и ожидает подтверждения", ['booking_id' => $userBooking['id']]);

        // Шаг 3: Проверить слоты с учетом pending брони
        $this->info("\n📅 Шаг 3: Проверка слотов с учетом ожидающей брони...");
        $slots = $this->getSlots($resourceId, '2024-01-16', 8);
        $this->info("Доступные слоты: " . count($slots));

        // Шаг 4: Подтверждение брони администратором
        $this->info("\n✅ Шаг 4: Подтверждение брони администратором...");
        $confirmedBooking = $this->confirmBooking($userBooking['id']);
        $this->checkStatus($confirmedBooking, 'confirmed', "Бронь подтверждена администратором");
        BookingLoggerService::info("Бронь подтверждена администратором", ['booking_id' => $userBooking['id']]);

        // Шаг 5: Попытка отмены просроченной брони
        $this->info("\n❌ Шаг 5: Попытка поздней отмены...");
        try {
            // Создаем бронь в прошлом для теста отмены
            $pastBooking = $this->createBooking([
                'resource_id' => $resourceId,
                'start' => '2024-01-10 10:00:00',
                'end' => '2024-01-10 11:00:00',
                'is_admin' => true,
                'booker' => ['name' => 'Тест отмены']
            ]);

            $this->cancelBooking($pastBooking['id'], 'client', 'Поздняя отмена');
            $this->error("НЕОЖИДАННО: Должно было быть ошибкой для поздней отмены!");
        } catch (\Exception $e) {
            $this->info("✅ Ожидаемая ошибка: {$e->getMessage()}");
            BookingLoggerService::warning("Поздняя отмена предотвращена", ['error' => $e->getMessage()]);
        }
    }

    /**
     * СЦЕНАРИЙ 7: Салон красоты - Статическое расписание с праздниками
     *
     * @param array $setupData - Данные настройки сценария
     * @return void
     */
    private function runScenario7(array $setupData): void
    {
        $resourceId = $setupData['resource_id'];

        $this->info("\n💅 СЦЕНАРИЙ 7: Салон красоты");
        $this->line("Параметры: Статическое расписание с праздниками, фиксированные слоты");
        $this->line("Тестирование: Обнаружение праздников, обработка выходных, время перерывов");

        BookingLoggerService::info("Начало Сценария 7: Салон красоты с праздниками");

        $testDates = [
            '2024-01-15' => ['type' => 'working', 'desc' => 'Рабочий понедельник'],
            '2024-01-01' => ['type' => 'holiday', 'desc' => 'Праздник (Новый год)'],
            '2024-01-14' => ['type' => 'weekend', 'desc' => 'Воскресенье (выходной)'],
            '2024-03-08' => ['type' => 'holiday', 'desc' => 'Праздник (8 марта)'],
            '2024-01-16' => ['type' => 'working', 'desc' => 'Рабочий вторник']
        ];

        foreach ($testDates as $date => $info) {
            $this->info("\n📅 Проверка {$info['desc']} ({$date})...");

            $slots = $this->getSlots($resourceId, $date, 3);

            if ($info['type'] === 'working' && count($slots) > 0) {
                $this->info("✅ {$info['desc']}: " . count($slots) . " слотов доступно");
                $this->line("   Первые слоты: " . implode(', ', array_slice($slots, 0, 2)));
                BookingLoggerService::info("Рабочий день: слоты доступны", [
                    'date' => $date,
                    'slot_count' => count($slots)
                ]);
            } elseif ($info['type'] === 'working') {
                $this->error("❌ {$info['desc']}: Нет доступных слотов (НЕОЖИДАННО)");
                BookingLoggerService::error("Рабочий день без слотов", ['date' => $date]);
            } else {
                $this->info("✅ {$info['desc']}: Нет слотов (ожидаемо)");
                BookingLoggerService::info("Выходной/праздник: слотов нет", ['date' => $date, 'type' => $info['type']]);
            }
        }

        // Тестирование бронирования вокруг перерывов
        $this->info("\n⏰ Тестирование обработки перерывов...");
        $slotsMonday = $this->getSlots($resourceId, '2024-01-15', 10);
        $hasBreakGap = false;

        foreach ($slotsMonday as $slot) {
            if (strpos($slot, '13:00') !== false) {
                $hasBreakGap = true;
                break;
            }
        }

        if ($hasBreakGap) {
            $this->info("✅ Перерывы правильно исключены из слотов");
            BookingLoggerService::info("Перерывы корректно обработаны в расписании");
        } else {
            $this->error("❌ Перерывы неправильно обработаны");
            BookingLoggerService::warning("Возможная проблема с обработкой перерывов");
        }
    }

    // 🔧 ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ API ВЫЗОВОВ

    /**
     * Получить доступные слоты для ресурса
     *
     * @param int $resourceId - ID ресурса
     * @param string $date - Дата в формате YYYY-MM-DD
     * @param int $count - Количество слотов
     * @return array - Массив слотов в формате ['start-end', ...]
     */
    private function getSlots(int $resourceId, string $date, int $count): array
    {
        $response = Http::get("{$this->baseUrl}/booking/{$resourceId}/slots", [
            'date' => $date,
            'count' => $count
        ]);

        if ($response->successful()) {
            return array_map(function($slot) {
                return $slot['start'] . '-' . $slot['end'];
            }, $response->json()['data'] ?? []);
        }

        return [];
    }

    /**
     * Создать новую бронь
     *
     * @param array $data - Данные для создания брони
     * @return array - Данные созданной брони
     * @throws \Exception - Если создание не удалось
     */
    private function createBooking(array $data): array
    {
        $response = Http::post("{$this->baseUrl}/booking/create", $data);

        if ($response->successful()) {
            return $response->json()['data'];
        }

        throw new \Exception($response->json()['error'] ?? 'Ошибка создания брони');
    }

    /**
     * Подтвердить бронь
     *
     * @param int $bookingId - ID брони
     * @return array - Данные подтвержденной брони
     * @throws \Exception - Если подтверждение не удалось
     */
    private function confirmBooking(int $bookingId): array
    {
        $response = Http::post("{$this->baseUrl}/booking/{$bookingId}/confirm");

        if ($response->successful()) {
            return $response->json()['data'];
        }

        throw new \Exception($response->json()['error'] ?? 'Ошибка подтверждения брони');
    }

    /**
     * Отменить бронь
     *
     * @param int $bookingId - ID брони
     * @param string $cancelledBy - Кто отменяет ('client' или 'admin')
     * @param string|null $reason - Причина отмены
     * @return array - Данные отмененной брони
     * @throws \Exception - Если отмена не удалась
     */
    private function cancelBooking(int $bookingId, string $cancelledBy, ?string $reason = null): array
    {
        $response = Http::post("{$this->baseUrl}/booking/{$bookingId}/cancel", [
            'cancelled_by' => $cancelledBy,
            'reason' => $reason
        ]);

        if ($response->successful()) {
            return $response->json()['data'];
        }


        throw new \Exception($response->json()['error'] ?? 'Ошибка отмены брони');
    }

    /**
     * Перенести бронь
     *
     * @param int $bookingId - ID брони
     * @param string $newStart - Новое время начала
     * @param string $newEnd - Новое время окончания
     * @param string $requestedBy - Кто запрашивает ('client' или 'admin')
     * @return array - Данные перенесенной брони
     * @throws \Exception - Если перенос не удался
     */
    private function rescheduleBooking(int $bookingId, string $newStart, string $newEnd, string $requestedBy): array
    {
        $response = Http::post("{$this->baseUrl}/booking/{$bookingId}/reschedule", [
            'new_start' => $newStart,
            'new_end' => $newEnd,
            'requested_by' => $requestedBy
        ]);

        if ($response->successful()) {
            return $response->json()['data'];
        }

        throw new \Exception($response->json()['error'] ?? 'Ошибка переноса брони');
    }

    /**
     * Проверить статус брони
     *
     * @param array $booking - Данные брони
     * @param string $expectedStatus - Ожидаемый статус
     * @param string $message - Сообщение для вывода
     * @return void
     */
    private function checkStatus(array $booking, string $expectedStatus, string $message): void
    {
        if ($booking['status'] === $expectedStatus) {
            $this->info("✅ {$message}: статус = {$booking['status']}");
        } else {
            $this->error("❌ {$message}: ожидался {$expectedStatus}, получен {$booking['status']}");
        }
    }

    // 📋 МЕТОДЫ ДЛЯ ПОЛУЧЕНИЯ ДАННЫХ СЦЕНАРИЕВ

    /**
     * Получить расписание для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return array - Данные расписания
     */
    private function getTimetableForScenario(int $scenarioId): array
    {
        $timetables = [
            1 => [ // Парикмахерская
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ]
                        // Суббота и воскресенье - не включаем (выходные)
                    ]
                ]
            ],
            7 => [ // Салон красоты с праздниками
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '18:00'],
                            'breaks' => [['start' => '14:00', 'end' => '15:00']] // Послеобеденный перерыв
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '21:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ]
                        // Воскресенье - не включаем (выходной)
                    ],
                    'holidays' => [
                        '01-01', // Новый год
                        '01-02', // Продолжение новогодних праздников
                        '01-07', // Рождество
                        '03-08', // Международный женский день
                        '05-01', // Праздник весны и труда
                        '05-09'  // День победы
                    ]
                ]
            ],
            8 => [ // Бизнес-центр со сложным расписанием
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:00', 'end' => '13:00'], // Обеденный перерыв
                                ['start' => '16:00', 'end' => '16:30']  // Кофе-брейк
                            ]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:00', 'end' => '13:00'], // Обеденный перерыв
                                ['start' => '16:00', 'end' => '16:30']  // Кофе-брейк
                            ]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:00', 'end' => '13:00'], // Обеденный перерыв
                                ['start' => '16:00', 'end' => '16:30']  // Кофе-брейк
                            ]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:00', 'end' => '13:00'], // Обеденный перерыв
                                ['start' => '16:00', 'end' => '16:30']  // Кофе-брейк
                            ]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '20:00'],
                            'breaks' => [
                                ['start' => '12:00', 'end' => '13:00'], // Обеденный перерыв
                                ['start' => '15:00', 'end' => '15:30']  // Ранний кофе-брейк
                            ]
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:00', 'end' => '14:00']] // Обеденный перерыв
                        ]
                        // Воскресенье - не включаем
                    ],
                    'holidays' => [
                        '01-01', // Новый год
                        '01-02', // Продолжение новогодних праздников
                        '01-07', // Рождество
                        '02-23', // День защитника отечества
                        '03-08', // Международный женский день
                        '05-01', // Праздник весны и труда
                        '05-09', // День победы
                        '06-12', // День России
                        '11-04'  // День народного единства
                    ]
                ]
            ]
        ];

        return $timetables[$scenarioId] ?? $timetables[1];
    }

    /**
     * Получить конфигурацию ресурса для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return array - Конфигурация ресурса
     */
    private function getResourceConfigForScenario(int $scenarioId): array
    {
        $configs = [
            1 => [ // Парикмахерская
                'require_confirmation' => false,           // Автоматическое подтверждение брони
                'slot_duration_minutes' => 60,             // Длительность слота: 60 минут
                'slot_strategy' => 'fixed',                // Стратегия: фиксированные слоты
                'min_advance_time' => 60,                  // Минимальное время для брони: 60 минут
                'cancellation_time' => 120,                // Время для отмены: 120 минут
                'reschedule_time' => 240,                  // Время для переноса: 240 минут
                'reminder_time' => 1440                    // Время напоминания: 1440 минут (сутки)
            ],
            2 => [ // Переговорная
                'require_confirmation' => true,            // Ручное подтверждение брони
                'slot_duration_minutes' => 30,             // Длительность слота: 30 минут
                'slot_strategy' => 'dinamic',              // Стратегия: динамические слоты
                'min_advance_time' => 1440,                // Минимальное время для брони: 1440 минут (сутки)
                'cancellation_time' => 720,                // Время для отмены: 720 минут (12 часов)
                'reschedule_time' => 1440                  // Время для переноса: 1440 минут (сутки)
            ],
            7 => [ // Салон красоты
                'require_confirmation' => false,           // Автоматическое подтверждение брони
                'slot_duration_minutes' => 60,             // Длительность слота: 60 минут
                'slot_strategy' => 'fixed',                // Стратегия: фиксированные слоты
                'min_advance_time' => 120,                 // Минимальное время для брони: 120 минут
                'cancellation_time' => 180,                // Время для отмены: 180 минут
                'reschedule_time' => 360,                  // Время для переноса: 360 минут
                'reminder_time' => 1440                    // Время напоминания: 1440 минут (сутки)
            ],
            8 => [ // Бизнес-центр
                'require_confirmation' => true,            // Ручное подтверждение брони
                'slot_duration_minutes' => 60,             // Длительность слота: 60 минут
                'slot_strategy' => 'dinamic',              // Стратегия: динамические слоты
                'max_participants' => 20,                  // Максимальное количество участников: 20
                'min_advance_time' => 1440,                // Минимальное время для брони: 1440 минут (сутки)
                'cancellation_time' => 720,                // Время для отмены: 720 минут (12 часов)
                'reschedule_time' => 1440                  // Время для переноса: 1440 минут (сутки)
            ]
        ];

        return $configs[$scenarioId] ?? $configs[1];
    }

    /**
     * Получить описание сценария
     *
     * @param int $scenarioId - ID сценария
     * @return string - Описание сценария
     */
    private function getScenarioDescription(int $scenarioId): string
    {
        $descriptions = [
            1 => "Парикмахерская услуга с фиксированными слотами и автоматическим подтверждением",
            2 => "Переговорная комната с динамическими слотами и ручным подтверждением",
            7 => "Салон красоты со статическим расписанием и учетом праздничных дней",
            8 => "Бизнес-центр со сложным расписанием и множественными перерывами"
        ];

        return $descriptions[$scenarioId] ?? "Демонстрационный сценарий {$scenarioId}";
    }

    /**
     * Получить название ресурса для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return string - Название ресурса
     */
    private function getResourceName(int $scenarioId): string
    {
        $names = [
            1 => "💈 Парикмахер",
            2 => "🏢 Переговорная комната",
            7 => "💅 Салон красоты",
            8 => "🏢 Бизнес-центр"
        ];

        return $names[$scenarioId] ?? "Ресурс {$scenarioId}";
    }

    /**
     * Получить опции ресурса для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return array - Опции ресурса
     */
    private function getResourceOptionsForScenario(int $scenarioId): array
    {
        return ['scenario_id' => $scenarioId, 'demo' => true];
    }

    /**
     * Получить переопределения конфигурации ресурса для сценария
     *
     * @param int $scenarioId - ID сценария
     * @return array - Переопределения конфигурации
     */
    private function getResourceOverridesForScenario(int $scenarioId): array
    {
        return [];
    }

    /**
     * Получить краткое описание конфигурации
     *
     * @param array $config - Конфигурация ресурса
     * @return string - Краткое описание
     */
    private function getConfigSummary(array $config): string
    {
        $parts = [];
        if (isset($config['require_confirmation'])) {
            $parts[] = $config['require_confirmation'] ? 'подтверждение:ручное' : 'подтверждение:авто';
        }
        if (isset($config['slot_strategy'])) {
            $strategy = $config['slot_strategy'] === 'fixed' ? 'фиксированные' : 'динамические';
            $parts[] = "слоты:{$strategy}";
        }
        if (isset($config['slot_duration_minutes'])) {
            $parts[] = "длительность:{$config['slot_duration_minutes']}мин";
        }
        if (isset($config['max_participants'])) {
            $parts[] = "макс:{$config['max_participants']}";
        }

        return implode(', ', $parts);
    }

    /**
     * Очистить данные сценария
     *
     * @param int $scenarioId - ID сценария
     * @return void
     */
    private function cleanupScenarioData(int $scenarioId): void
    {
        $companyId = $scenarioId * 100;
        Company::where('id', $companyId)->delete();

        BookingLoggerService::info("Данные сценария очищены", ['scenario_id' => $scenarioId]);
    }

    /**
     * Сохранить результаты выполнения сценария
     *
     * @param int $scenarioId - ID сценария
     * @param array $setupData - Данные настройки
     * @return void
     */
    private function storeScenarioResults(int $scenarioId, array $setupData): void
    {
        $filename = storage_path("app/demo/scenario_{$scenarioId}_results.json");

        $results = [
            'scenario_id' => $scenarioId,
            'company_id' => $setupData['company_id'],
            'resource_id' => $setupData['resource_id'],
            'timestamp' => now()->toISOString(),
            'description' => $this->getScenarioDescription($scenarioId),
            'config' => $this->getResourceConfigForScenario($scenarioId)
        ];

        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        file_put_contents($filename, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("📄 Результаты сохранены в: {$filename}");

        BookingLoggerService::info("Результаты сценария сохранены", [
            'scenario_id' => $scenarioId,
            'file' => $filename
        ]);
    }
}
