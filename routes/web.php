<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {


    $config = new \App\ValueObjects\ResourceConfig([
        "min_advance_time" => 60
    ]);

    $now = now(); // 18:14
    $start = \Carbon\Carbon::parse("2026-02-03 19:20:00 Europe/Moscow");



    // Проверка минимального времени для бронирования
    if ($config->min_advance_time > 0) {
        $minutesUntilStart = $now->diffInMinutes($start, false); // false чтобы получить отрицательное значение для прошедшего времени

        if ($minutesUntilStart < $config->min_advance_time) {
            throw new \Exception('Бронирование возможно только за ' . $config->min_advance_time . ' минут до начала. До начала осталось: ' . $minutesUntilStart . ' минут');
        }
    }

    dd($minutesUntilStart ?? $config);

    /**
     * @var \App\Models\Resource $resource
     */
    $resource = \App\Models\Resource::first();
    /**
     * @var \App\Services\Booking\BookingService $bs
     * @var \App\Services\Booking\BookingService $bookingService
     */
    $bs = $bookingService = app(\App\Services\Booking\BookingService::class);

    $booker = \App\Models\User::first();

    $r = $bs->getNextAvailableSlots(
        $resource,
        now()->startOfDay(),
        1000
    );

    dd($r);
//
//    $booking = $bookingService->createBooking(
//        $resource,
//        now()->startOfDay()->addHours(10),
//        now()->startOfDay()->addHours(10)->addMinutes(15),
//        $booker,
//        true
//    );
//
//    dd($booking);

    dump($resource, $resource->getResourceConfig());

    dump($r[0] ?? null, $r[1] ?? null, $r[2] ?? null);
    dd($r);

});
