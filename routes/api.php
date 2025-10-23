<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Booking System Routes
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\ResourceTypeController;
use App\Http\Controllers\ResourceController;

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
