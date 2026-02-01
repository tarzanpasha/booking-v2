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
