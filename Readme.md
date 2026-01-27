Сейчас список функций для создании брони испольузют сервис, а не Action.
подготовлю их список:

BookingService - основной сервис бронирования в папке app/Services

1)Получить доступные слоты:
$slots = $this->bookingService->getNextAvailableSlots($resource, $from, $count, $onlyToday);

2)создать бронь

$booking = $this->bookingService->createBooking(
$resource,
$request->start,
$request->end,
$request->booker ?? [],  (здесь долен быть экземпляр модели User или аналоги)
$request->is_admin ?? false
);

3)подтверждение брони 

$booking = $this->bookingService->confirmBooking($id);

4)отмена брони

$booking = $this->bookingService->cancelBooking(
$id,
$request->cancelled_by ?? 'client',
$request->booker,
$request->reason
);

5)проверка доступности слота

$available = $this->bookingService->isSlotAvailable(
$resource,
$request->start,
$request->slots
);

6)доступность проверки диапазона

$available = $this->bookingService->isRangeAvailable(
$resource,
Carbon::parse($request->start),
Carbon::parse($request->end)
);

7)перенос брони (пока на этапе тестироания и доработки:

$booking = $this->rescheduleBookingAction->execute(
$id,
$request->new_start,
$request->new_end,
$request->requested_by ?? 'client'
);

8)Получение броней для ресурса в диапазоне
(доработки требует подключение связей с клиентами)

$bookings = $this->bookingService->getBookingsForResourceInRange(
$resource,
$request->from,
$request->to
);
