<?php
// app/Console/Commands/DemoScenarios/ScenarioRunnerService.php

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
use App\Services\Booking\BookingService;
use App\Http\Controllers\Api\BookingController;
use App\Http\Requests\GetSlotsRequest;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RescheduleBookingRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

            if (isset($data['data'])) {
                return array_map(function($slot) {
                    return $slot['start'] . '-' . $slot['end'];
                }, $data['data']);
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
        $responseData = $response->getData(true);

        if (isset($responseData['data'])) {
            return $responseData['data'];
        }

        throw new \Exception($responseData['error'] ?? 'Ошибка создания брони');
    }

    /**
     * Подтверждение брони
     */
    public function confirmBooking(int $bookingId): array
    {
        $response = $this->bookingController->confirmBooking($bookingId);
        $responseData = $response->getData(true);

        return $responseData['data'];
    }

    /**
     * Отмена брони
     */
    public function cancelBooking(int $bookingId, string $cancelledBy, ?string $reason = null): array
    {
        $request = new CancelBookingRequest();
        $request->merge([
            'cancelled_by' => $cancelledBy,
            'reason' => $reason
        ]);

        $response = $this->bookingController->cancelBooking($bookingId, $request);
        $responseData = $response->getData(true);

        return $responseData['data'];
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
        $responseData = $response->getData(true);

        return $responseData['data'];
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
            $booking = Booking::with(['resource', 'bookers'])->findOrFail($bookingId);
            return [
                'id' => $booking->id,
                'resource_id' => $booking->resource_id,
                'start' => $booking->start,
                'end' => $booking->end,
                'status' => $booking->status,
                'reason' => $booking->reason,
                'resource' => $booking->resource,
                'bookers' => $booking->bookers
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
        $timetableData = $this->getTimetableForScenario($scenarioId);
        $timetable = $this->createTimetableAction->execute(
            $company->id,
            $timetableData['schedule'],
            $timetableData['type']
        );

        // Конфигурация типа ресурса
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

        // Создание конкретного ресурса
        $resourceData = [
            'company_id' => $company->id,
            'timetable_id' => $timetable->id,
            'resource_type_id' => $resourceType->id,
            'options' => $this->getResourceOptionsForScenario($scenarioId),
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
     * Получение данных расписания для конкретного сценария
     */
    public function getTimetableForScenario(int $scenarioId): array
    {
        $timetables = [
            1 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ]
                ]
            ],
            2 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'tuesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'wednesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'thursday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'friday' => ['working_hours' => ['start' => '08:00', 'end' => '18:00']],
                    ]
                ]
            ],
            3 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'tuesday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'wednesday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'thursday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'friday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'saturday' => ['working_hours' => ['start' => '09:00', 'end' => '18:00']],
                        'sunday' => ['working_hours' => ['start' => '09:00', 'end' => '16:00']],
                    ]
                ]
            ],
            4 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                    ]
                ]
            ],
            5 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'tuesday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'wednesday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'thursday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'friday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'saturday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'sunday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                    ]
                ]
            ],
            6 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'tuesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'wednesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'thursday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'friday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                    ]
                ]
            ],
            7 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '18:00'],
                            'breaks' => [['start' => '14:00', 'end' => '15:00']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '21:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ],
                    'holidays' => ['01-01', '01-02', '01-07', '03-08', '05-01', '05-09']
                ]
            ],
            8 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '20:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '15:00', 'end' => '15:30']
                            ]
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ],
                    'holidays' => ['01-01', '01-02', '01-07', '02-23', '03-08', '05-01', '05-09', '06-12', '11-04']
                ]
            ]
        ];

        return $timetables[$scenarioId] ?? $timetables[1];
    }

    /**
     * Получение конфигурации ресурса для сценария
     */
    public function getResourceConfigForScenario(int $scenarioId): array
    {
        $configs = [
            1 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 60,
                'cancellation_time' => 120,
                'reschedule_time' => 240,
                'reminder_time' => 1440
            ],
            2 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 30,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 1440,
                'cancellation_time' => 720,
                'reschedule_time' => 1440
            ],
            3 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 90,
                'slot_strategy' => 'fixed',
                'max_participants' => 10,
                'min_advance_time' => 60,
                'cancellation_time' => 180,
                'reschedule_time' => 360
            ],
            4 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 120,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 2880,
                'cancellation_time' => 4320,
                'reschedule_time' => 5760,
                'reminder_time' => 1440
            ],
            5 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 1440,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 0,
                'cancellation_time' => 10080,
                'reschedule_time' => 10080
            ],
            6 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 0,
                'cancellation_time' => 0,
                'reschedule_time' => 0
            ],
            7 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 120,
                'cancellation_time' => 180,
                'reschedule_time' => 360,
                'reminder_time' => 1440
            ],
            8 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'dinamic',
                'max_participants' => 20,
                'min_advance_time' => 1440,
                'cancellation_time' => 720,
                'reschedule_time' => 1440
            ]
        ];

        return $configs[$scenarioId] ?? $configs[1];
    }

    /**
     * Получение описания сценария
     */
    public function getScenarioDescription(int $scenarioId): string
    {
        $descriptions = [
            1 => "Парикмахерская услуга с фиксированными слотами и автоматическим подтверждением",
            2 => "Переговорная комната с динамическими слотами и ручным подтверждением",
            3 => "Групповая тренировка с фиксированными слотами и ограничением участников",
            4 => "Дорогое оборудование с динамическими слотами и строгими ограничениями",
            5 => "Гостиничный номер с переходящими бронями на несколько дней",
            6 => "Экстренные случаи с приоритетом администратора",
            7 => "Салон красоты со статическим расписанием и учетом праздничных дней",
            8 => "Бизнес-центр со сложным расписанием и множественными перерывами"
        ];

        return $descriptions[$scenarioId] ?? "Демонстрационный сценарий {$scenarioId}";
    }

    /**
     * Получение опций ресурса для сценария
     */
    public function getResourceOptionsForScenario(int $scenarioId): array
    {
        $options = [
            1 => ['specialization' => 'Парикмахер', 'experience' => '5 лет'],
            2 => ['location' => 'Этаж 3', 'capacity' => 8, 'equipment' => ['projector', 'whiteboard']],
            3 => ['location' => 'Зал А', 'trainer' => 'Иван Петров', 'type' => 'Йога'],
            4 => ['name' => '3D принтер', 'model' => 'Ultimaker S5', 'value' => '250000 руб'],
            5 => ['room_number' => '404', 'type' => 'Стандарт', 'beds' => 2],
            6 => ['priority' => 'high', 'emergency_contact' => '+7-XXX-XXX-XX-XX'],
            7 => ['specialization' => 'Косметолог', 'services' => ['маникюр', 'педикюр']],
            8 => ['location' => 'Бизнес-центр "Сити"', 'floor' => '15', 'capacity' => 50]
        ];

        return $options[$scenarioId] ?? ['scenario_id' => $scenarioId, 'demo' => true];
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
    }
}
