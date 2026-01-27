<?php

use Illuminate\Support\Facades\Route;

// =============================================
// Booking System Routes - Core Entities
// =============================================

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\ResourceTypeController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TimetableImportController;

// Core Entities CRUD
Route::apiResource('companies', CompanyController::class);
Route::apiResource('timetables', TimetableController::class);
Route::apiResource('resource-types', ResourceTypeController::class);
Route::apiResource('resources', ResourceController::class);

// Additional timetable routes
Route::prefix('timetables/{timetable}')->group(function () {
    Route::post('/attach-resource', [TimetableController::class, 'attachResource']);
    Route::post('/detach-resource', [TimetableController::class, 'detachResource']);
    Route::post('/attach-resource-type', [TimetableController::class, 'attachResourceType']);
    Route::post('/detach-resource-type', [TimetableController::class, 'detachResourceType']);
});

// =============================================
// Booking System Routes - Booking Functionality
// =============================================

Route::prefix('booking')->group(function () {
    Route::get('/resources', [BookingController::class, 'getResources']);
    Route::get('/{resource}/slots', [BookingController::class, 'getAvailableSlots']);
    Route::post('/create', [BookingController::class, 'createBooking']);
    Route::post('/{id}/confirm', [BookingController::class, 'confirmBooking']);
    Route::post('/{id}/cancel', [BookingController::class, 'cancelBooking']);
    Route::post('/{id}/reschedule', [BookingController::class, 'rescheduleBooking']);
    Route::get('/resource/{id}/bookings', [BookingController::class, 'getBookingsForResource']);
    Route::get('/check', [BookingController::class, 'checkSlotAvailability']);
});

// =============================================
// Timetable Import Routes
// =============================================

Route::prefix('timetables')->group(function () {
    Route::post('/import-json', [TimetableImportController::class, 'importFromJson']);
    Route::post('/import-file', [TimetableImportController::class, 'importFromFile']);
});

