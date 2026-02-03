<?php

namespace Tests\Feature\Booking;

use App\Services\Booking\BookingService;
use App\Services\Booking\SlotGenerationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

class TimetableSlotGenerationTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private BookingService $bookingService;
    private SlotGenerationService $slotService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
        $this->slotService = app(SlotGenerationService::class);
    }

    /**
     * STATIC timetable + FIXED slot_strategy:
     * - слоты не должны попадать на перерыв
     * - касание границ перерыва должно быть разрешено
     * - booking на слот, совпадающий с перерывом, должен быть запрещён (через fixed slots validation)
     */
    public function test_static_fixed_slots_skip_break_and_allow_touching_boundaries(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Рабочее время 09:00-12:00, перерыв 10:00-10:30
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'wednesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'thursday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'friday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'saturday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'sunday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $date = $this->dt('2026-02-03 00:00:00');
        $slots = $this->slotService->generateSlotsForDate($resource, $date);

        // Проверяем, что слот 10:00-10:30 (перерыв) НЕ сгенерирован,
        // но слоты, "касающиеся" границ перерыва, есть:
        // 09:30-10:00 (конец = breakStart) и 10:30-11:00 (начало = breakEnd)
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
            '2026-02-03 11:00:00|2026-02-03 11:30:00',
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($slots));

        // Слот 09:30-10:00 должен считаться доступным (касание перерыва разрешено)
        $this->assertTrue($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 09:30:00'),
            $this->dt('2026-02-03 10:00:00')
        ));

        // Попытка забронировать "ровно перерыв" 10:00-10:30:
        // для fixed стратегии это должно падать как "не соответствует доступным слотам"
        $u = User::factory()->create();

        $this->expectExceptionMessage('Выбранное время не соответствует доступным слотам');
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 10:00:00'),
            $this->dt('2026-02-03 10:30:00'),
            $u,
            false
        );
    }

    /**
     * STATIC timetable + FIXED slot_strategy:
     * - стык с началом/концом рабочего дня
     * - слот должен начинаться ровно в start и может заканчиваться ровно в end
     * - слот не должен выходить за границу end
     */
    public function test_static_fixed_slots_day_boundaries(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'tuesday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'wednesday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'thursday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'friday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'saturday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
                'sunday' => ['working_hours' => ['start' => '09:00', 'end' => '10:00'], 'breaks' => []],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed_edges', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Должен быть ровно один слот 09:00-10:00
        $this->assertSame(['2026-02-03 09:00:00|2026-02-03 10:00:00'], $this->slotPairs($slots));

        // Бронь на этот слот должна быть возможной
        $u = User::factory()->create();
        $b = $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 09:00:00'),
            $this->dt('2026-02-03 10:00:00'),
            $u,
            false
        );

        $this->assertSame('confirmed', $b->status);

        // В 10:00 начинать слот нельзя (это конец рабочего времени)
        $this->expectExceptionMessage('Выбранное время не соответствует доступным слотам');
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 10:00:00'),
            $this->dt('2026-02-03 11:00:00'),
            $u,
            false
        );
    }

    /**
     * STATIC timetable + FIXED slot_strategy:
     * Доп.кейсы, которые часто забывают:
     * - перерыв начинается в начале дня => первый слот пропадает
     * - перерыв в конце дня => последний слот пропадает
     * - перерыв полностью накрывает рабочее время => слотов нет
     */
    public function test_static_fixed_slots_breaks_at_edges_and_full_coverage(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // 1) Перерыв в начале дня 09:00-09:30 при рабочем 09:00-11:00
        $timetable1 = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'wednesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'thursday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'friday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'saturday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
                'sunday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '09:00', 'end' => '09:30']],
                ],
            ],
        ]);

        $rt1 = $this->createResourceType($company, $timetable1, 'static_fixed_break_start', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $r1 = $this->createResource($company, $rt1, $timetable1);

        $slots1 = $this->slotService->generateSlotsForDate($r1, $this->dt('2026-02-03 00:00:00'));

        // 09:00-09:30 должен исчезнуть (перерыв), первый слот станет 09:30-10:00
        $this->assertSame([
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:00:00|2026-02-03 10:30:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
        ], $this->slotPairs($slots1));

        // 2) Перерыв в конце дня 10:30-11:00 при рабочем 09:00-11:00
        $timetable2 = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'wednesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'thursday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'friday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'saturday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
                'sunday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                    'breaks' => [['start' => '10:30', 'end' => '11:00']],
                ],
            ],
        ]);

        $rt2 = $this->createResourceType($company, $timetable2, 'static_fixed_break_end', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);
        $r2 = $this->createResource($company, $rt2, $timetable2);

        $slots2 = $this->slotService->generateSlotsForDate($r2, $this->dt('2026-02-03 00:00:00'));

        // Последний слот 10:30-11:00 должен исчезнуть
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:00:00|2026-02-03 10:30:00',
        ], $this->slotPairs($slots2));

        // 3) Перерыв полностью накрывает рабочее время => слотов нет
        $timetable3 = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'wednesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'thursday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'friday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'saturday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
                'sunday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [['start' => '09:00', 'end' => '10:00']],
                ],
            ],
        ]);

        $rt3 = $this->createResourceType($company, $timetable3, 'static_fixed_full_break', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);
        $r3 = $this->createResource($company, $rt3, $timetable3);

        $slots3 = $this->slotService->generateSlotsForDate($r3, $this->dt('2026-02-03 00:00:00'));
        $this->assertSame([], $this->slotPairs($slots3));
    }

    /**
     * STATIC timetable + DYNAMIC slot_strategy:
     * Проверяем "админ мог занять кусок другим временем" (неровное время)
     * и что динамическая генерация учитывает:
     * - брони (pending/confirmed)
     * - перерывы
     */
    public function test_static_dynamic_slots_respect_breaks_and_irregular_admin_booking(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'wednesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'thursday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'friday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'saturday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
                'sunday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [['start' => '10:00', 'end' => '10:30']],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_dynamic', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'dynamic',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);
        $admin = User::factory()->create();

        // Админ занимает кусок "неровным" временем, который режет потенциальные слоты
        // 09:40-10:10 (пересекает и рабочее время, и начало перерыва)
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 09:40:00'),
            $this->dt('2026-02-03 10:10:00'),
            $admin,
            true // admin
        );

        // Для dynamic slot_strategy генерация должна учитывать booking и break
        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Ожидаем:
        // - из 09:00-09:40 только 09:00-09:30 (09:30-10:00 не влезает из-за брони)
        // - после брони и после перерыва остаётся период 10:30-12:00 -> три слота по 30
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
            '2026-02-03 11:00:00|2026-02-03 11:30:00',
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($slots));

        // Проверка "слот уже занят другим диапазоном":
        // диапазон 09:30-10:00 пересекается с admin booking 09:40-10:10 -> недоступно
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 09:30:00'),
            $this->dt('2026-02-03 10:00:00')
        ));

        // Диапазон 10:10-10:30 попадает на перерыв -> недоступно
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:10:00'),
            $this->dt('2026-02-03 10:30:00')
        ));

        // Диапазон 10:30-10:45 уже после перерыва и вне брони -> доступно
        $this->assertTrue($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:30:00'),
            $this->dt('2026-02-03 10:45:00')
        ));
    }

    /**
     * STATIC timetable + FIXED slot_strategy:
     * Проверяем, что список "доступных слотов" (getNextAvailableSlots)
     * отфильтровывает слоты, пересекающиеся с нерегулярной бронью.
     *
     * Важно: generateSlotsForDate() в fixed стратегии НЕ смотрит на bookings,
     * но getNextAvailableSlots() проверяет доступность через isSlotAvailable().
     */
    public function test_static_fixed_get_next_available_slots_excludes_slots_overlapped_by_irregular_booking(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'monday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'tuesday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'wednesday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'thursday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'friday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'saturday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
                'sunday' => ['working_hours' => ['start' => '09:00', 'end' => '12:00'], 'breaks' => []],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed_next', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $admin = User::factory()->create();

        // Админ занял кусок 09:30-10:15 (неровно по слотам)
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 09:30:00'),
            $this->dt('2026-02-03 10:15:00'),
            $admin,
            true
        );

        // Теперь "доступные слоты" должны исключить:
        // - 09:30-10:00 (пересекается)
        // - 10:00-10:30 (пересекается, т.к. booking до 10:15)
        $available = $this->slotService->getNextAvailableSlots(
            $resource,
            $this->dt('2026-02-03 00:00:00'),
            10,
            true
        );

        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
            '2026-02-03 11:00:00|2026-02-03 11:30:00',
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($available));
    }

    /**
     * DYNAMIC timetable (type=dynamic) + FIXED slot_strategy:
     * те же проверки, но рабочие часы берутся из schedule['dates']['MM-DD'].
     */
    public function test_dynamic_timetable_fixed_slots_breaks_and_edges(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Для даты 02-03 задаём рабочее время и перерыв
        $timetable = $this->createDynamicTimetable($company, [
            '02-03' => [
                'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                'breaks' => [['start' => '10:00', 'end' => '10:30']],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'dynamic_fixed', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
            '2026-02-03 11:00:00|2026-02-03 11:30:00',
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($slots));

        // Дата, которой нет в dynamic schedule => слотов нет
        $slotsMissing = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-04 00:00:00'));
        $this->assertSame([], $this->slotPairs($slotsMissing));
    }

    /**
     * DYNAMIC timetable + DYNAMIC slot_strategy:
     * проверяем, что брони режут доступные периоды + перерыв вырезается.
     */
    public function test_dynamic_timetable_dynamic_slots_respect_breaks_and_irregular_booking(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        $timetable = $this->createDynamicTimetable($company, [
            '02-03' => [
                'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                'breaks' => [['start' => '10:00', 'end' => '10:30']],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'dynamic_dynamic', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'dynamic',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $admin = User::factory()->create();

        // Админ занимает 11:10-11:50 (неровно)
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 11:10:00'),
            $this->dt('2026-02-03 11:50:00'),
            $admin,
            true
        );

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // До перерыва: 09:00-09:30, 09:30-10:00
        // После перерыва: 10:30-11:00, 11:00-11:30 (НО! он пересечётся с 11:10-11:50 => должен пропасть),
        // дальше период после брони: 11:50-12:00 слишком мал для 30 минут => слотов нет
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00',
        ], $this->slotPairs($slots));
    }

    // --------------------------
    // Helpers
    // --------------------------

    /**
     * Возвращает удобный список "start|end" для сравнения ожидаемых слотов.
     */
    private function slotPairs(array $slots): array
    {
        return array_values(array_map(
            fn ($s) => ($s['start'] ?? '???') . '|' . ($s['end'] ?? '???'),
            $slots
        ));
    }


    /**
     * Кейс: несколько перерывов в одном дне (не пересекаются).
     *
     * Что проверяем:
     * - fixed-слоты не генерируются внутри любого из перерывов
     * - "стык" между перерывами разрешён (слот может начинаться ровно в конце перерыва и
     *   заканчиваться ровно в начале следующего перерыва)
     * - BookingService корректно запрещает пересечения с любым из перерывов
     */
    public function test_static_fixed_multiple_breaks_skip_all_and_allow_slot_between_breaks(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Вторник, рабочее 09:00-12:00, два перерыва 10:00-10:30 и 11:00-11:30
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [
                        ['start' => '10:00', 'end' => '10:30'],
                        ['start' => '11:00', 'end' => '11:30'],
                    ],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed_multi_breaks', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Ожидаем слоты:
        // 09:00-09:30, 09:30-10:00, (10:00-10:30 перерыв), 10:30-11:00, (11:00-11:30 перерыв), 11:30-12:00
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 10:30:00|2026-02-03 11:00:00', // "между" перерывами — допустимо
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($slots));

        // Слот ровно между перерывами должен считаться доступным
        $this->assertTrue($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:30:00'),
            $this->dt('2026-02-03 11:00:00')
        ));

        // Диапазон, пересекающий первый перерыв, должен быть недоступен
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:15:00'),
            $this->dt('2026-02-03 10:45:00')
        ));

        // Диапазон, равный перерыву, тоже должен быть недоступен
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:00:00'),
            $this->dt('2026-02-03 10:30:00')
        ));
    }

    /**
     * Кейс: несколько перерывов, в т.ч. НЕ отсортированные и ПЕРЕСЕКАЮЩИЕСЯ.
     *
     * Что проверяем:
     * - итоговое "вырезание" должно соответствовать объединению перерывов (union),
     *   даже если breaks приходят в произвольном порядке и перекрывают друг друга.
     *
     * Важно: этот тест ловит баги "дыр" в расписании из-за порядка breaks.
     */
    public function test_static_fixed_overlapping_and_unsorted_breaks_behave_as_union(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Рабочее 09:00-12:00.
        // Перерывы (нарочно неотсортированы и пересекаются):
        // - 11:00-11:30
        // - 10:30-11:00
        // - 10:00-10:45
        // Их union: 10:00-11:30
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [
                        ['start' => '11:00', 'end' => '11:30'],
                        ['start' => '10:30', 'end' => '11:00'],
                        ['start' => '10:00', 'end' => '10:45'],
                    ],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed_overlap_breaks', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // До union перерывов: 09:00-10:00 -> два слота
        // После union перерывов: 11:30-12:00 -> один слот
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
            '2026-02-03 11:30:00|2026-02-03 12:00:00',
        ], $this->slotPairs($slots));
    }

    /**
     * Кейс: slot_strategy = dynamic + несколько перерывов + "неровная" админская бронь.
     *
     * Что проверяем:
     * - динамическая генерация корректно вырезает несколько break-окон
     * - динамическая генерация корректно вырезает произвольную (неровную) бронь,
     *   которая может "резать" потенциальные слоты
     * - слот не должен пересекать НИ один break, НИ бронь
     */
    public function test_static_dynamic_slots_multiple_breaks_and_irregular_booking(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Рабочее 09:00-12:00, два перерыва:
        // - 09:30-09:45
        // - 10:15-10:30
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '12:00'],
                    'breaks' => [
                        ['start' => '09:30', 'end' => '09:45'],
                        ['start' => '10:15', 'end' => '10:30'],
                    ],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_dynamic_multi_breaks', [
            'slot_duration_minutes' => 15,
            'slot_strategy' => 'dynamic',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $admin = User::factory()->create();

        // Админ занимает "неровный" кусок 11:05-11:20
        $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 11:05:00'),
            $this->dt('2026-02-03 11:20:00'),
            $admin,
            true
        );

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Ожидаемые 15-мин слоты (без break-окон и без админ-брони):
        // 09:00-09:15, 09:15-09:30, (09:30-09:45 break)
        // 09:45-10:00, 10:00-10:15, (10:15-10:30 break)
        // 10:30-10:45, 10:45-11:00, (11:00-11:15 не влезает из-за брони 11:05-11:20)
        // (11:15-11:30 тоже не влезает)
        // 11:20-11:35, 11:35-11:50 (11:50-12:05 не влезает)
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:15:00',
            '2026-02-03 09:15:00|2026-02-03 09:30:00',
            '2026-02-03 09:45:00|2026-02-03 10:00:00',
            '2026-02-03 10:00:00|2026-02-03 10:15:00',
            '2026-02-03 10:30:00|2026-02-03 10:45:00',
            '2026-02-03 10:45:00|2026-02-03 11:00:00',
            '2026-02-03 11:20:00|2026-02-03 11:35:00',
            '2026-02-03 11:35:00|2026-02-03 11:50:00',
        ], $this->slotPairs($slots));

        // Санити: диапазон, пересекающий break, недоступен
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 10:10:00'),
            $this->dt('2026-02-03 10:20:00')
        ));

        // Санити: диапазон, пересекающий админ-бронь, недоступен
        $this->assertFalse($this->bookingService->isTimeRangeAvailable(
            $resource,
            $this->dt('2026-02-03 11:00:00'),
            $this->dt('2026-02-03 11:15:00')
        ));
    }

    /**
     * Кейс: Timetable type = dynamic + несколько перерывов.
     *
     * Что проверяем:
     * - выборка расписания по ключу "MM-DD"
     * - корректная генерация fixed слотов с несколькими break-окнами
     */
    public function test_dynamic_timetable_fixed_multiple_breaks(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Для 02-03: рабочее 09:00-11:00, два перерыва 09:30-10:00 и 10:30-11:00
        $timetable = $this->createDynamicTimetable($company, [
            '02-03' => [
                'working_hours' => ['start' => '09:00', 'end' => '11:00'],
                'breaks' => [
                    ['start' => '09:30', 'end' => '10:00'],
                    ['start' => '10:30', 'end' => '11:00'],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'dynamic_fixed_multi_breaks', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Остаются слоты: 09:00-09:30 и 10:00-10:30
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 10:00:00|2026-02-03 10:30:00',
        ], $this->slotPairs($slots));
    }

    /**
     * Кейс: "грязные" breaks, которые часто прилетают из интеграций.
     *
     * Что проверяем:
     * - break-элементы без start/end должны игнорироваться (не падать и не ломать слоты)
     * - breaks полностью ВНЕ рабочего времени не должны влиять на слоты
     *
     * (Важное замечание: фикс-генератор breaks парсит без try/catch, поэтому мы НЕ используем
     *  некорректный формат времени — только "отсутствующие ключи", которые безопасно игнорируются.)
     */
    public function test_static_fixed_ignores_invalid_break_entries_and_breaks_outside_working_hours(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);

        // Рабочее 09:00-10:00, slot 30 минут.
        // Перерывы:
        // - 08:00-08:30 (полностью до рабочего времени) => не влияет
        // - 10:00-10:30 (начинается ровно в конце рабочего времени) => не влияет
        // - 2 "битых" элемента (без start/end) => должны быть проигнорированы
        $timetable = $this->createStaticTimetable($company, [
            'days' => [
                'tuesday' => [
                    'working_hours' => ['start' => '09:00', 'end' => '10:00'],
                    'breaks' => [
                        ['start' => '08:00', 'end' => '08:30'],
                        ['start' => '10:00', 'end' => '10:30'],
                        ['foo' => 'bar'],
                        ['start' => '09:15'], // нет end -> игнор
                    ],
                ],
            ],
        ]);

        $resourceType = $this->createResourceType($company, $timetable, 'static_fixed_invalid_breaks', [
            'slot_duration_minutes' => 30,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $slots = $this->slotService->generateSlotsForDate($resource, $this->dt('2026-02-03 00:00:00'));

        // Ничто не должно сломаться, ожидаем стандартные два слота
        $this->assertSame([
            '2026-02-03 09:00:00|2026-02-03 09:30:00',
            '2026-02-03 09:30:00|2026-02-03 10:00:00',
        ], $this->slotPairs($slots));
    }

}
