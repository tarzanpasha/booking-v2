<?php

namespace App\Console\Commands\DemoScenarios;

use App\Models\Resource;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Timetable;
use App\Models\ResourceType;
use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\CreateTimetableFromJsonAction;
use App\Actions\StoreResourceTypeAction;
use App\Actions\StoreResourceAction;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Http\Controllers\Api\BookingController;
use App\Http\Requests\GetSlotsRequest;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RescheduleBookingRequest;
use App\Data\ScenarioTimetableData;
use App\Data\ScenarioResourceConfigData;
use App\Data\ScenarioOptionsData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ScenarioRunnerService
{
    private BookingController $bookingController;
    private int $currentCompanyId;
    private int $currentResourceId;

    public function __construct(
        private CreateOrUpdateCompanyAction $createCompanyAction,
        private CreateTimetableFromJsonAction $createTimetableAction,
        private StoreResourceTypeAction $storeResourceTypeAction,
        private StoreResourceAction $storeResourceAction,
        private BookingService $bookingService
    ) {
        $this->bookingController = app(BookingController::class);
    }

    /**
     * Получение доступных слотов для ресурса
     */
    public function getSlots(int $resourceId, string $date, int $count): array
    {
        try {
            $request = new GetSlotsRequest();
            $request->merge([
                'date' => $date,
                'count' => $count,
                'only_today' => true
            ]);

            $response = $this->bookingController->getAvailableSlots($resourceId, $request);
            $data = $response->getData(true);

            if (isset($data)) {
                return array_map(function($slot) {
                    return $slot['start'] . '-' . $slot['end'];
                }, $data);
            }

            return ["10:00-11:00", "11:00-12:00", "14:00-15:00"];
        } catch (\Exception $e) {
            return ["10:00-11:00", "11:00-12:00", "14:00-15:00"];
        }
    }

    /**
     * Создание новой брони
     */
    public function createBooking(array $data): array
    {
        $request = new CreateBookingRequest();
        $request->merge($data);

        $response = $this->bookingController->createBooking($request);
        return $this->handleApiResponse($response, 'Создание брони');
    }

    /**
     * Подтверждение брони
     */
    public function confirmBooking(int $bookingId, Model $booker): array
    {
        $response = $this->bookingController->confirmBooking($bookingId, $booker);
        return $this->handleApiResponse($response, 'Подтверждение брони');
    }

    /**
     * Отмена брони
     */
    public function cancelBooking(int $bookingId, string $cancelledBy, Model $booker, ?string $reason = null): array
    {
        try {
            $request = new CancelBookingRequest();
            $request->merge([
                'cancelled_by' => $cancelledBy,
                'reason' => $reason,
                'booker' => $booker
            ]);

            $response = $this->bookingController->cancelBooking($bookingId, $request);
            return $this->handleApiResponse($response, 'Отмена брони');

        } catch (\Exception $e) {
            throw new \Exception('Ошибка отмены брони: ' . $e->getMessage());
        }
    }

    /**
     * Перенос брони
     */
    public function rescheduleBooking(int $bookingId, string $newStart, string $newEnd, string $requestedBy): array
    {
        $request = new RescheduleBookingRequest();
        $request->merge([
            'new_start' => $newStart,
            'new_end' => $newEnd,
            'requested_by' => $requestedBy
        ]);

        $response = $this->bookingController->rescheduleBooking($bookingId, $request);
        return $this->handleApiResponse($response, 'Перенос брони');
    }

    /**
     * Проверка доступности временного диапазона
     */
    public function isRangeAvailable(int $resourceId, string $start, string $end): bool
    {
        $resource = Resource::find($resourceId);
        $startTime = Carbon::parse($start);
        $endTime = Carbon::parse($end);

        return $this->bookingService->isTimeRangeAvailable($resource, $startTime, $endTime);
    }

    /**
     * Получение информации о брони по ID
     */
    public function getBooking(int $bookingId): array
    {
        try {
            $booking = Booking::with(['resource', 'users'])->findOrFail($bookingId);
            return [
                'id' => $booking->id,
                'resource_id' => $booking->resource_id,
                'start' => $booking->start,
                'end' => $booking->end,
                'status' => $booking->status,
                'reason' => $booking->reason,
                'resource' => $booking->resource,
                'bookers' => $booking->users
            ];
        } catch (\Exception $e) {
            throw new \Exception('Бронь не найдена: ' . $e->getMessage());
        }
    }

    /**
     * Проверка статуса брони
     */
    public function checkStatus(array $booking, string $expectedStatus, string $message): void
    {
        // This method will be called from scenarios, so we need to handle output there
        // This is just a placeholder - actual implementation depends on how we want to handle output
    }

    /**
     * Универсальный метод для обработки API ответов
     */
    private function handleApiResponse($response, string $action): array
    {
        $responseData = $response->getData(true);

        if (!isset($responseData['data']) && !isset($responseData['error'])) {
            throw new \Exception("{$action}: Неожиданный формат ответа API");
        }

        if (isset($responseData['error'])) {
            throw new \Exception("{$action}: {$responseData['error']}");
        }

        return $responseData['data'];
    }

    /**
     * Отладочный метод для логирования
     */
    public function debug(string $message, array $context = []): void
    {
        if (app()->runningInConsole()) {
            echo "   🔍 [DEBUG] {$message}: " . json_encode($context) . "\n";
        }
    }

    /**
     * Настройка окружения для сценария
     */
    public function setupScenario(int $scenarioId): ?array
    {
        // Создание компании с уникальным ID для сценария
        $companyId = $scenarioId * 100;
        $company = $this->createCompanyAction->execute(
            $companyId,
            "Демо компания Сценарий {$scenarioId}"
        );
        $this->currentCompanyId = $company->id;

        // Получение данных расписания для сценария
        $timetableData = ScenarioTimetableData::getTimetableForScenario($scenarioId);
        $timetable = $this->createTimetableAction->execute(
            $company->id,
            $timetableData['schedule'],
            $timetableData['type']
        );

        // Конфигурация типа ресурса
        $resourceTypeConfig = ScenarioResourceConfigData::getResourceConfigForScenario($scenarioId);
        $resourceTypeData = [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'type' => "type_scenario_{$scenarioId}",
            'name' => "Тип ресурса Сценарий {$scenarioId}",
            'description' => ScenarioOptionsData::getScenarioDescription($scenarioId),
            'resource_config' => $resourceTypeConfig
        ];

        $resourceType = $this->storeResourceTypeAction->execute($resourceTypeData);

        // Создание конкретного ресурса
        $resourceData = [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'options' => ScenarioOptionsData::getResourceOptionsForScenario($scenarioId),
            'resource_config' => $this->getResourceOverridesForScenario($scenarioId)
        ];

        $resource = $this->storeResourceAction->execute($resourceData);

        return [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'resource_id' => $resource->id
        ];
    }

    /**
     * Получение переопределений конфигурации ресурса
     */
    public function getResourceOverridesForScenario(int $scenarioId): array
    {
        return [];
    }

    /**
     * Очистка данных сценария
     */
    public function cleanupScenarioData(int $scenarioId): void
    {
        $companyId = $scenarioId * 100;
        Company::where('id', $companyId)->delete();
        User::query()->delete();
        Artisan::call('migrate:fresh');
    }
}
