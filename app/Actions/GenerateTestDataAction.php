<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Timetable;
use App\Models\ResourceType;
use App\Models\Resource;
use App\Models\Booking;
use App\Services\ArtisanCommandService;
use Carbon\Carbon;

class GenerateTestDataAction
{
    public function __construct(
        private CreateOrUpdateCompanyAction $createOrUpdateCompanyAction,
        private CreateTimetableFromJsonAction $createTimetableFromJsonAction,
        private ArtisanCommandService $artisanCommandService
    ) {}

    public function execute(int $companyId = 1): array
    {
        $company = $this->createOrUpdateCompanyAction->execute($companyId, 'Test Company ' . $companyId);

        // Создаем расписания используя Artisan команды
        $staticTimetable = $this->createStaticTimetable($companyId);
        $dynamicTimetable = $this->createDynamicTimetable($companyId);

        // Анализируем сгенерированные расписания для создания соответствующих ресурсов
        $staticTimetableInfo = $this->artisanCommandService->getTimetableInfo($staticTimetable->schedule, 'static');
        $dynamicTimetableInfo = $this->artisanCommandService->getTimetableInfo($dynamicTimetable->schedule, 'dinamic');

        // Создаем типы ресурсов на основе анализа расписаний
        $resourceTypes = $this->createResourceTypes($companyId, $staticTimetable, $dynamicTimetable, $staticTimetableInfo, $dynamicTimetableInfo);

        // Создаем ресурсы на основе типов
        $resources = $this->createResources($companyId, $resourceTypes, $staticTimetable, $dynamicTimetable);

        // Создаем тестовые бронирования на основе реального расписания
        $bookings = $this->createTestBookings($resources, $staticTimetable, $dynamicTimetable);

        return [
            'company' => $company,
            'timetables' => [
                'static' => $staticTimetable,
                'dynamic' => $dynamicTimetable
            ],
            'timetable_info' => [
                'static' => $staticTimetableInfo,
                'dynamic' => $dynamicTimetableInfo
            ],
            'resource_types' => $resourceTypes,
            'resources' => $resources,
            'bookings' => $bookings,
        ];
    }

    private function createStaticTimetable(int $companyId): Timetable
    {
        // Используем Artisan команду для генерации реального статического расписания
        $staticData = $this->artisanCommandService->generateStaticTimetable($companyId);

        return $this->createTimetableFromJsonAction->execute($companyId, $staticData, 'static');
    }

    private function createDynamicTimetable(int $companyId): Timetable
    {
        // Используем Artisan команду для генерации реального динамического расписания
        $dynamicData = $this->artisanCommandService->generateDynamicTimetable($companyId, 14); // 14 дней для тестов

        return $this->createTimetableFromJsonAction->execute($companyId, $dynamicData, 'dinamic');
    }

    private function createResourceTypes(
        int $companyId,
        Timetable $staticTimetable,
        Timetable $dynamicTimetable,
        array $staticInfo,
        array $dynamicInfo
    ): array {
        // Анализируем расписания для создания соответствующих типов ресурсов

        // Для статического расписания (регулярные рабочие дни)
        $employeeType = ResourceType::create([
            'company_id' => $companyId,
            'timetable_id' => $staticTimetable->id,
            'type' => 'employee',
            'name' => 'Сотрудник',
            'description' => "Персональные консультации ({$staticInfo['working_days']} рабочих дней в неделю)",
            'resource_config' => [
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'require_confirmation' => false,
                'min_advance_time' => 60,
                'cancellation_time' => 120,
                'reschedule_time' => 240,
            ]
        ]);

        // Для статического расписания (переговорные комнаты)
        $roomType = ResourceType::create([
            'company_id' => $companyId,
            'timetable_id' => $staticTimetable->id,
            'type' => 'meeting_room',
            'name' => 'Переговорная',
            'description' => "Комната для встреч ({$staticInfo['total_breaks']} перерывов в расписании)",
            'resource_config' => [
                'slot_duration_minutes' => 30,
                'slot_strategy' => 'dinamic',
                'require_confirmation' => true,
                'max_participants' => 10,
                'min_advance_time' => 30,
            ]
        ]);

        // Для динамического расписания (специальные мероприятия)
        $trainingType = ResourceType::create([
            'company_id' => $companyId,
            'timetable_id' => $dynamicTimetable->id,
            'type' => 'training',
            'name' => 'Групповая тренировка',
            'description' => "Групповые занятия ({$dynamicInfo['working_days']} дней в расписании)",
            'resource_config' => [
                'slot_duration_minutes' => 90,
                'slot_strategy' => 'fixed',
                'require_confirmation' => false,
                'max_participants' => 20,
                'min_advance_time' => 1440,
            ]
        ]);

        // Дополнительный тип для динамического расписания (специальное оборудование)
        $equipmentType = ResourceType::create([
            'company_id' => $companyId,
            'timetable_id' => $dynamicTimetable->id,
            'type' => 'equipment',
            'name' => 'Специальное оборудование',
            'description' => 'Оборудование для специальных мероприятий',
            'resource_config' => [
                'slot_duration_minutes' => 120,
                'slot_strategy' => 'dinamic',
                'require_confirmation' => true,
                'max_participants' => 5,
                'min_advance_time' => 480,
            ]
        ]);

        return [
            'employee' => $employeeType,
            'meeting_room' => $roomType,
            'training' => $trainingType,
            'equipment' => $equipmentType,
        ];
    }

    private function createResources(
        int $companyId,
        array $resourceTypes,
        Timetable $staticTimetable,
        Timetable $dynamicTimetable
    ): array {
        $resources = [];

        // Ресурсы для статического расписания (сотрудники)
        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['employee']->id,
            'timetable_id' => $staticTimetable->id,
            'options' => ['specialization' => 'Парикмахер', 'experience' => '5 лет'],
            'resource_config' => ['slot_duration_minutes' => 45]
        ]);

        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['employee']->id,
            'timetable_id' => $staticTimetable->id,
            'options' => ['specialization' => 'Массажист', 'experience' => '3 года'],
        ]);

        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['employee']->id,
            'timetable_id' => $staticTimetable->id,
            'options' => ['specialization' => 'Косметолог', 'experience' => '4 года'],
        ]);

        // Ресурсы для статического расписания (переговорные)
        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['meeting_room']->id,
            'timetable_id' => $staticTimetable->id,
            'options' => ['location' => 'Этаж 3', 'capacity' => 8, 'equipment' => ['projector', 'whiteboard']],
            'resource_config' => ['max_participants' => 8]
        ]);

        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['meeting_room']->id,
            'timetable_id' => $staticTimetable->id,
            'options' => ['location' => 'Этаж 2', 'capacity' => 15, 'equipment' => ['tv', 'conference_phone']],
        ]);

        // Ресурсы для динамического расписания (тренировки)
        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['training']->id,
            'timetable_id' => $dynamicTimetable->id,
            'options' => ['location' => 'Зал А', 'trainer' => 'Иван Петров', 'type' => 'Йога'],
        ]);

        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['training']->id,
            'timetable_id' => $dynamicTimetable->id,
            'options' => ['location' => 'Зал Б', 'trainer' => 'Мария Сидорова', 'type' => 'Пилатес'],
        ]);

        // Ресурсы для динамического расписания (оборудование)
        $resources[] = Resource::create([
            'company_id' => $companyId,
            'resource_type_id' => $resourceTypes['equipment']->id,
            'timetable_id' => $dynamicTimetable->id,
            'options' => ['name' => '3D принтер', 'model' => 'Ultimaker S5', 'specifications' => ['print_volume' => '330×240×300 mm']],
        ]);

        return $resources;
    }

    private function createTestBookings(array $resources, Timetable $staticTimetable, Timetable $dynamicTimetable): array
    {
        $bookings = [];
        $now = now();

        // Анализируем расписания для создания реалистичных бронирований
        $staticSchedule = $staticTimetable->schedule;
        $dynamicSchedule = $dynamicTimetable->schedule;

        foreach ($resources as $resource) {
            $isStatic = $resource->timetable_id === $staticTimetable->id;
            $schedule = $isStatic ? $staticSchedule : $dynamicSchedule;

            // Создаем прошедшие бронирования (2-3 на ресурс)
            $pastBookingsCount = rand(2, 3);
            for ($i = 0; $i < $pastBookingsCount; $i++) {
                $daysAgo = rand(1, 7);
                $hour = rand(9, 16);

                $bookings[] = Booking::create([
                    'company_id' => 1,
                    'resource_id' => $resource->id,
                    'timetable_id' => $resource->timetable_id,
                    'start' => $now->copy()->subDays($daysAgo)->setHour($hour)->setMinute(0),
                    'end' => $now->copy()->subDays($daysAgo)->setHour($hour + 1)->setMinute(0),
                    'status' => 'confirmed',
                    'is_group_booking' => $resource->resourceType->type === 'training',
                ]);
            }

            // Создаем будущие бронирования (1-2 на ресурс)
            $futureBookingsCount = rand(1, 2);
            for ($i = 0; $i < $futureBookingsCount; $i++) {
                $daysAhead = rand(1, 14);
                $hour = rand(9, 16);

                $bookings[] = Booking::create([
                    'company_id' => 1,
                    'resource_id' => $resource->id,
                    'timetable_id' => $resource->timetable_id,
                    'start' => $now->copy()->addDays($daysAhead)->setHour($hour)->setMinute(0),
                    'end' => $now->copy()->addDays($daysAhead)->setHour($hour + 1)->setMinute(0),
                    'status' => rand(0, 1) ? 'confirmed' : 'pending',
                    'is_group_booking' => $resource->resourceType->type === 'training',
                ]);
            }
        }

        return $bookings;
    }
}
