<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingRescheduled;
use App\Models\Bookable;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

class SingleBookingLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    public function test_single_booking_lifecycle_create_cancel_reschedule_and_admin_override(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'single_service', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'max_participants' => null, // одиночная
            'min_advance_time' => 0,
            'cancellation_time' => null,
            'reschedule_time' => null,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $start = $this->dt('2026-02-03 10:00:00');
        $end   = $this->dt('2026-02-03 11:00:00');

        Event::fake([BookingCreated::class, BookingCancelled::class, BookingRescheduled::class]);

        // 1) Создалась в нужный слот -> слот недоступен
        $booking = $this->bookingService->createBooking($resource, $start, $end, $u1, false);
        $this->assertSame(BookingStatus::CONFIRMED->value, $booking->status);
        $this->assertFalse($this->bookingService->isRangeAvailable($resource, $start, $end));

        Event::assertDispatched(BookingCreated::class);

        // 2) Отменилась -> слот снова доступен
        $cancelled = $this->bookingService->cancelBooking($booking->id, 'client', $u1, 'test cancel');
        $cancelled->refresh();

        $this->assertSame(BookingStatus::CANCELLED_BY_CLIENT->value, $cancelled->status);
        $this->assertTrue($this->bookingService->isRangeAvailable($resource, $start, $end));

        Event::assertDispatched(BookingCancelled::class);

        // pivot статус у юзера тоже должен обновиться
        $pivot = Bookable::query()
            ->where('booking_id', $cancelled->id)
            ->where('bookable_id', $u1->id)
            ->where('bookable_type', $u1::class)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertSame(BookingStatus::CANCELLED_BY_CLIENT->value, $pivot->status);

        // 3) Создалась новая бронь в тот же слот
        $booking2 = $this->bookingService->createBooking($resource, $start, $end, $u2, false);
        $this->assertSame(BookingStatus::CONFIRMED->value, $booking2->status);
        $this->assertFalse($this->bookingService->isRangeAvailable($resource, $start, $end));

        // 4) Перенеслась пользователем (в указанный слот)
        $newStart = '2026-02-03 11:00:00';
        $newEnd   = '2026-02-03 12:00:00';

        $booking2r = $this->bookingService->rescheduleBooking($booking2->id, $newStart, $newEnd, 'client');
        $booking2r->refresh();

        $this->assertSame('2026-02-03 11:00:00', $booking2r->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-03 12:00:00', $booking2r->end->format('Y-m-d H:i:s'));

        Event::assertDispatched(BookingRescheduled::class);

        // 5) Перенеслась админом на время с наложением
        // создадим третью бронь на 14-15
        $blockStart = $this->dt('2026-02-03 14:00:00');
        $blockEnd   = $this->dt('2026-02-03 15:00:00');
        $blockBooking = $this->bookingService->createBooking($resource, $blockStart, $blockEnd, $u1, false);
        $this->assertSame(BookingStatus::CONFIRMED->value, $blockBooking->status);

        // теперь переносим booking2r админом на 14-15, даже если занято
        $booking2r2 = $this->bookingService->rescheduleBooking(
            $booking2r->id,
            '2026-02-03 14:00:00',
            '2026-02-03 15:00:00',
            'admin'
        );
        $booking2r2->refresh();
        $this->assertSame('2026-02-03 14:00:00', $booking2r2->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-03 15:00:00', $booking2r2->end->format('Y-m-d H:i:s'));

        // 6) Админ отменил (событие, слот освобождается)
        // сначала отменим блокирующую бронь, чтобы слот реально стал свободным
        $this->bookingService->cancelBooking($blockBooking->id, 'admin', $u1, 'admin cancel block');

        $this->assertTrue($this->bookingService->isRangeAvailable($resource, $blockStart, $blockEnd) === false, 'slot still blocked by booking2 before final cancel');

        $this->bookingService->cancelBooking($booking2r2->id, 'admin', $u2, 'admin cancel main');
        $this->assertTrue($this->bookingService->isRangeAvailable($resource, $blockStart, $blockEnd), 'after both cancels slot must be free');
    }

    public function test_single_booking_require_confirmation_pending_then_confirm(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'single_confirmed', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => true,
            'max_participants' => null,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = User::factory()->create();

        $start = $this->dt('2026-02-03 10:00:00');
        $end   = $this->dt('2026-02-03 11:00:00');

        $booking = $this->bookingService->createBooking($resource, $start, $end, $u1, false);

        $this->assertSame(BookingStatus::PENDING->value, $booking->status);
        $this->assertFalse($this->bookingService->isRangeAvailable($resource, $start, $end), 'pending should block slot');

        // подтверждаем
        $confirmed = $this->bookingService->confirmBooking($booking->id, $u1);
        $this->assertSame(BookingStatus::CONFIRMED->value, $confirmed->status);
    }

    public function test_client_cannot_book_in_the_past_when_min_advance_time_zero(): void
    {
        $this->freezeNow('2026-02-03 10:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'single_past', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'min_advance_time' => 0, // строго "только будущее"
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);
        $u1 = User::factory()->create();

        $start = $this->dt('2026-02-03 09:00:00'); // прошлое относительно now()
        $end   = $this->dt('2026-02-03 10:00:00');

        $this->expectExceptionMessage('Бронирование невозможно для прошедшего времени');
        $this->bookingService->createBooking($resource, $start, $end, $u1, false);
    }

    public function test_single_booking_after_cancel_creates_new_booking_and_does_not_reuse_cancelled_record(): void
    {
        $this->freezeNow('2026-02-03 08:00:00');

        $company = $this->createCompany(1);
        $timetable = $this->createStaticTimetable($company);

        $resourceType = $this->createResourceType($company, $timetable, 'single_service', [
            'slot_duration_minutes' => 60,
            'slot_strategy' => 'fixed',
            'require_confirmation' => false,
            'max_participants' => null,
            'min_advance_time' => 0,
        ]);

        $resource = $this->createResource($company, $resourceType, $timetable);

        $u1 = \App\Models\User::factory()->create();
        $u2 = \App\Models\User::factory()->create();

        $start = $this->dt('2026-02-03 10:00:00');
        $end   = $this->dt('2026-02-03 11:00:00');

        $b1 = $this->bookingService->createBooking($resource, $start, $end, $u1, false);
        $this->bookingService->cancelBooking($b1->id, 'client', $u1, 'cancel');

        $b2 = $this->bookingService->createBooking($resource, $start, $end, $u2, false);

        $this->assertNotSame($b1->id, $b2->id, 'must create a NEW booking, not reuse cancelled one');
        $this->assertSame(\App\Enums\BookingStatus::CONFIRMED->value, $b2->status);
    }
}
