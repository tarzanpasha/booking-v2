<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingRescheduled;
use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

class GroupBookingLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    public function test_group_booking_capacity_join_leave_free_place_and_last_leave_keeps_record_but_frees_slot(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        // max_participants = 3 => групповая бронь
        $resourceType = $this->createResourceType($company, $timetable, 'group_service', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'max_participants' => 3,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $u4 = User::factory()->create();

        $start = $this->dt('2026-02-03 10:00:00');
        $end   = $this->dt('2026-02-03 11:00:00');

        Event::fake([BookingCreated::class, BookingCancelled::class, BookingRescheduled::class]);

        // Создалась
        $b1 = $this->bookingService->createBooking($resource, $start, $end, $u1, false);
        $this->assertTrue($b1->is_group_booking);
        $this->assertSame(BookingStatus::CONFIRMED->value, $b1->status);

        // В слот могут напихаться ещё столько, сколько в лимитах (до 3)
        $b2 = $this->bookingService->createBooking($resource, $start, $end, $u2, false);
        $b3 = $this->bookingService->createBooking($resource, $start, $end, $u3, false);

        // ВАЖНОЕ ожидание: при присоединении к группе НЕ должна создаваться новая Booking-запись
        $this->assertSame(1, Booking::query()->count(), 'group slot must have exactly 1 booking record');
        $this->assertSame($b1->id, $b2->id);
        $this->assertSame($b1->id, $b3->id);

        // 4-й сверх лимита должен получить REJECTED и не создать новую booking-запись
        $b4 = $this->bookingService->createBooking($resource, $start, $end, $u4, false);
        $this->assertSame(1, Booking::query()->count(), 'still must be 1 booking record');

        $this->assertBookableStatus($b1->id, $u4, BookingStatus::REJECTED->value);

        // Один отвалился - появилось место
        $this->bookingService->cancelBooking($b1->id, 'client', $u2, 'leave');
        $this->assertBookableStatus($b1->id, $u2, BookingStatus::CANCELLED_BY_CLIENT->value);

        // теперь u4 должен суметь войти (место освободилось)
        $this->bookingService->createBooking($resource, $start, $end, $u4, false);
        $this->assertBookableStatus($b1->id, $u4, BookingStatus::CONFIRMED->value);

        // Отвалились все -> booking запись остаётся, но слот должен стать свободным (т.к. booking статус уходит в cancelled)
        $this->bookingService->cancelBooking($b1->id, 'client', $u1, 'leave');
        $this->bookingService->cancelBooking($b1->id, 'client', $u3, 'leave');
        $this->bookingService->cancelBooking($b1->id, 'client', $u4, 'leave');

        $b1->refresh();

        // запись остаётся в bookings
        $this->assertNotNull(Booking::find($b1->id), 'booking record must still exist for history');

        // слот должен стать свободным: cancelled booking не должен блокировать доступность
        $this->assertTrue(
            $this->bookingService->isRangeAvailable($resource, $start, $end),
            'after last participant left, slot should become available'
        );
    }

    public function test_group_booking_reschedule_by_admin_dispatches_event_and_keeps_participants(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'group_resched', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'max_participants' => 5,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $start = $this->dt('2026-02-03 10:00:00');
        $end   = $this->dt('2026-02-03 11:00:00');

        Event::fake([BookingRescheduled::class]);

        $b = $this->bookingService->createBooking($resource, $start, $end, $u1, false);
        $this->bookingService->createBooking($resource, $start, $end, $u2, false);

        // Перенесли (уведомление всем): минимально — событие BookingRescheduled
        $rescheduled = $this->bookingService->rescheduleBooking(
            $b->id,
            '2026-02-03 12:00:00',
            '2026-02-03 13:00:00',
            'admin'
        );

        $rescheduled->refresh();
        $this->assertSame('2026-02-03 12:00:00', $rescheduled->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-03 13:00:00', $rescheduled->end->format('Y-m-d H:i:s'));

        Event::assertDispatched(BookingRescheduled::class);

        // Участники должны остаться привязаны
        $this->assertBookableExists($rescheduled->id, $u1);
        $this->assertBookableExists($rescheduled->id, $u2);
    }

    /**
     * Дополнение (важный кейс): клиент не может переносить групповую бронь
     */
    public function test_group_booking_client_cannot_reschedule(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'group_client_resched', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'max_participants' => 3,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = User::factory()->create();

        $b = $this->bookingService->createBooking(
            $resource,
            $this->dt('2026-02-03 10:00:00'),
            $this->dt('2026-02-03 11:00:00'),
            $u1,
            false
        );

        $this->expectExceptionMessage('Невозможно перенести групповую бронь не админу');
        $this->bookingService->rescheduleBooking($b->id, '2026-02-03 11:00:00', '2026-02-03 12:00:00', 'client');
    }

    private function assertBookableStatus(int $bookingId, User $user, string $expectedStatus): void
    {
        $pivot = Bookable::query()
            ->where('booking_id', $bookingId)
            ->where('bookable_id', $user->id)
            ->where('bookable_type', $user::class)
            ->first();

        $this->assertNotNull($pivot, 'bookable pivot must exist');
        $this->assertSame($expectedStatus, $pivot->status);
    }

    private function assertBookableExists(int $bookingId, User $user): void
    {
        $exists = Bookable::query()
            ->where('booking_id', $bookingId)
            ->where('bookable_id', $user->id)
            ->where('bookable_type', $user::class)
            ->exists();

        $this->assertTrue($exists, 'bookable pivot must exist');
    }
}
