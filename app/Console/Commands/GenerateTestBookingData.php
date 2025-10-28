<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateTestBookingData extends Command
{
    protected $signature = 'booking:generate-test-data {--company-id=}';
    protected $description = 'Generate test data for booking system';

    public function handle(): void
    {
        $companyId = $this->option('company-id') ?? 1;

        $this->info("🏢 Создание тестовых данных для компании {$companyId}...");

        // Используем DB facade для создания данных, чтобы избежать проблем с порядком загрузки моделей
        $company = $this->createCompany($companyId);
        $staticTimetable = $this->createStaticTimetable($companyId);
        $dynamicTimetable = $this->createDynamicTimetable($companyId);

        $this->info("✅ Расписания созданы");

        $employeeType = $this->createResourceType($companyId, $staticTimetable, [
            'type' => 'employee',
            'name' => 'Сотрудник',
            'description' => 'Персональные консультации',
            'resource_config' => json_encode([
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'require_confirmation' => false,
                'min_advance_time' => 60,
                'cancellation_time' => 120,
                'reschedule_time' => 240,
            ])
        ]);

        $roomType = $this->createResourceType($companyId, $staticTimetable, [
            'type' => 'meeting_room',
            'name' => 'Переговорная',
            'description' => 'Комната для встреч',
            'resource_config' => json_encode([
                'slot_duration_minutes' => 30,
                'slot_strategy' => 'dinamic',
                'require_confirmation' => true,
                'max_participants' => 10,
                'min_advance_time' => 30,
            ])
        ]);

        $trainingType = $this->createResourceType($companyId, $dynamicTimetable, [
            'type' => 'training',
            'name' => 'Групповая тренировка',
            'description' => 'Групповые занятия',
            'resource_config' => json_encode([
                'slot_duration_minutes' => 90,
                'slot_strategy' => 'fixed',
                'require_confirmation' => false,
                'max_participants' => 20,
                'min_advance_time' => 1440,
            ])
        ]);

        $this->info("✅ Типы ресурсов созданы");

        $resources = [
            $this->createResource($companyId, $employeeType, [
                'options' => json_encode(['specialization' => 'Парикмахер']),
                'resource_config' => json_encode(['slot_duration_minutes' => 45])
            ]),

            $this->createResource($companyId, $employeeType, [
                'options' => json_encode(['specialization' => 'Массажист']),
            ]),

            $this->createResource($companyId, $roomType, [
                'options' => json_encode(['location' => 'Этаж 3', 'capacity' => 8]),
                'resource_config' => json_encode(['max_participants' => 8])
            ]),

            $this->createResource($companyId, $roomType, [
                'options' => json_encode(['location' => 'Этаж 2', 'capacity' => 15]),
            ]),

            $this->createResource($companyId, $trainingType, $dynamicTimetable),
        ];

        $this->info("✅ Ресурсы созданы");

        $this->createTestBookings($resources);

        $this->info("🎉 Тестовые данные успешно созданы!");

        $bookingsCount = DB::table('bookings')->count();
        $this->info("📊 Статистика:");
        $this->info("   - Компаний: 1");
        $this->info("   - Расписаний: 2");
        $this->info("   - Типов ресурсов: 3");
        $this->info("   - Ресурсов: " . count($resources));
        $this->info("   - Бронирований: " . $bookingsCount);
    }

    private function createCompany(int $companyId): int
    {
        $exists = DB::table('companies')->where('id', $companyId)->exists();

        if (!$exists) {
            DB::table('companies')->insert([
                'id' => $companyId,
                'name' => 'Test Company ' . $companyId,
                'description' => 'Test company for booking system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $companyId;
    }

    private function createStaticTimetable(int $companyId): int
    {
        $id = DB::table('timetables')->insertGetId([
            'company_id' => $companyId,
            'type' => 'static',
            'schedule' => json_encode([
                'days' => [
                    'monday' => [
                        'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                        'breaks' => [['start' => '13:00', 'end' => '14:00']]
                    ],
                    'tuesday' => [
                        'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                        'breaks' => [['start' => '13:00', 'end' => '14:00']]
                    ],
                    'wednesday' => [
                        'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                        'breaks' => [['start' => '13:00', 'end' => '14:00']]
                    ],
                    'thursday' => [
                        'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                        'breaks' => [['start' => '13:00', 'end' => '14:00']]
                    ],
                    'friday' => [
                        'working_hours' => ['start' => '09:00', 'end' => '17:00'],
                        'breaks' => [['start' => '13:00', 'end' => '14:00']]
                    ],
                    'saturday' => null,
                    'sunday' => null,
                ],
                'holidays' => ['01-01', '01-07', '03-08', '05-01', '05-09', '06-12', '11-04']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createDynamicTimetable(int $companyId): int
    {
        $id = DB::table('timetables')->insertGetId([
            'company_id' => $companyId,
            'type' => 'dinamic',
            'schedule' => json_encode([
                'dates' => [
                    now()->format('m-d') => [
                        'working_hours' => ['start' => '10:00', 'end' => '20:00'],
                        'breaks' => [['start' => '14:00', 'end' => '15:00']]
                    ],
                    now()->addDay()->format('m-d') => [
                        'working_hours' => ['start' => '08:00', 'end' => '16:00'],
                        'breaks' => []
                    ],
                ]
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createResourceType(int $companyId, int $timetableId, array $data): int
    {
        $id = DB::table('resource_types')->insertGetId(array_merge([
            'company_id' => $companyId,
            'timetable_id' => $timetableId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $data));

        return $id;
    }

    private function createResource(int $companyId, int $typeId, $data = null, $timetableId = null): int
    {
        $resourceData = [
            'company_id' => $companyId,
            'resource_type_id' => $typeId,
            'timetable_id' => $timetableId,
            'options' => json_encode([]),
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (is_array($data)) {
            $resourceData = array_merge($resourceData, $data);
        }

        $id = DB::table('resources')->insertGetId($resourceData);
        return $id;
    }

    private function createTestBookings(array $resourceIds): void
    {
        $now = now();

        foreach ($resourceIds as $resourceId) {
            DB::table('bookings')->insert([
                [
                    'company_id' => 1,
                    'resource_id' => $resourceId,
                    'timetable_id' => null,
                    'start' => $now->copy()->subDays(2)->setHour(10)->setMinute(0),
                    'end' => $now->copy()->subDays(2)->setHour(11)->setMinute(0),
                    'status' => 'confirmed',
                    'is_group_booking' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'company_id' => 1,
                    'resource_id' => $resourceId,
                    'timetable_id' => null,
                    'start' => $now->copy()->subDay()->setHour(14)->setMinute(0),
                    'end' => $now->copy()->subDay()->setHour(15)->setMinute(0),
                    'status' => 'confirmed',
                    'is_group_booking' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // Добавляем несколько дополнительных бронирований
        DB::table('bookings')->insert([
            [
                'company_id' => 1,
                'resource_id' => $resourceIds[0],
                'timetable_id' => null,
                'start' => $now->copy()->addHours(2)->setMinute(0),
                'end' => $now->copy()->addHours(3)->setMinute(0),
                'status' => 'confirmed',
                'is_group_booking' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 1,
                'resource_id' => $resourceIds[2],
                'timetable_id' => null,
                'start' => $now->copy()->addDays(1)->setHour(11)->setMinute(0),
                'end' => $now->copy()->addDays(1)->setHour(12)->setMinute(0),
                'status' => 'pending',
                'is_group_booking' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
