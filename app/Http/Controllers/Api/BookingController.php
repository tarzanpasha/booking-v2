<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\Booking\BookingService;
use App\Services\Booking\SlotGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private SlotGenerationService $slotService
    ) {}

    public function getResources(): JsonResponse
    {
        $resources = Resource::with(['company', 'resourceType', 'timetable'])
            ->get()
            ->map(function ($resource) {
                $config = $resource->getResourceConfig();
                return [
                    'id' => $resource->id,
                    'name' => $resource->resourceType->name ?? 'Unknown',
                    'type' => $resource->resourceType->type ?? 'unknown',
                    'company_id' => $resource->company_id,
                    'config' => $config->toArray(),
                    'timetable' => $resource->getEffectiveTimetable(),
                ];
            });

        return response()->json($resources);
    }

    public function getAvailableSlots($resourceId, Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'sometimes|date',
            'count' => 'sometimes|integer|min:1|max:50',
            'only_today' => 'sometimes|boolean'
        ]);

        $resource = Resource::findOrFail($resourceId);
        $date = $request->get('date', now()->toDateString());
        $count = $request->get('count', 10);
        $onlyToday = $request->get('only_today', true);

        $from = Carbon::parse($date);
        $slots = $this->bookingService->getNextAvailableSlots($resource, $from, $count, $onlyToday);

        $formattedSlots = array_map(function ($slot) {
            return [
                'start' => $slot['start']->toDateTimeString(),
                'end' => $slot['end']->toDateTimeString(),
            ];
        }, $slots);

        return response()->json($formattedSlots);
    }

    public function createBooking(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'booker' => 'sometimes|array',
            'booker.external_id' => 'sometimes|string',
            'booker.type' => 'sometimes|string',
            'booker.name' => 'sometimes|string',
            'booker.email' => 'sometimes|email',
            'booker.phone' => 'sometimes|string',
            'is_admin' => 'sometimes|boolean'
        ]);

        try {
            $resource = Resource::findOrFail($request->resource_id);
            $booking = $this->bookingService->createBooking(
                $resource,
                $request->start,
                $request->end,
                $request->booker ?? [],
                $request->is_admin ?? false
            );

            return response()->json([
                'id' => $booking->id,
                'status' => $booking->status,
                'message' => $booking->status === 'pending'
                    ? 'Бронь создана и ожидает подтверждения'
                    : 'Бронь успешно создана и подтверждена'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function confirmBooking($id): JsonResponse
    {
        try {
            $booking = $this->bookingService->confirmBooking($id);

            return response()->json([
                'id' => $booking->id,
                'status' => $booking->status,
                'message' => 'Бронь успешно подтверждена'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function cancelBooking($id, Request $request): JsonResponse
    {
        $request->validate([
            'cancelled_by' => 'sometimes|in:client,admin',
            'reason' => 'sometimes|string|max:255'
        ]);

        try {
            $booking = $this->bookingService->cancelBooking(
                $id,
                $request->cancelled_by ?? 'client',
                $request->reason
            );

            return response()->json([
                'id' => $booking->id,
                'status' => $booking->status,
                'message' => 'Бронь успешно отменена'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function rescheduleBooking($id, Request $request): JsonResponse
    {
        $request->validate([
            'new_start' => 'required|date',
            'new_end' => 'required|date|after:new_start',
            'requested_by' => 'sometimes|in:client,admin'
        ]);

        try {
            $booking = $this->bookingService->rescheduleBooking(
                $id,
                $request->new_start,
                $request->new_end,
                $request->requested_by ?? 'client'
            );

            return response()->json([
                'id' => $booking->id,
                'status' => $booking->status,
                'message' => 'Бронь успешно перенесена',
                'start' => $booking->start,
                'end' => $booking->end
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getBookingsForResource($id, Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after:from'
        ]);

        $resource = Resource::findOrFail($id);
        $bookings = $this->bookingService->getBookingsForResourceInRange(
            $resource,
            $request->from,
            $request->to
        );

        return response()->json($bookings);
    }

    public function checkSlotAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'slots' => 'sometimes|integer|min:1'
        ]);

        $resource = Resource::findOrFail($request->resource_id);

        if ($request->has('slots')) {
            $available = $this->bookingService->isSlotAvailable(
                $resource,
                $request->start,
                $request->slots
            );
        } else {
            $available = $this->bookingService->isRangeAvailable(
                $resource,
                Carbon::parse($request->start),
                Carbon::parse($request->end)
            );
        }

        return response()->json([
            'available' => $available,
            'message' => $available
                ? 'Слот доступен для бронирования'
                : 'Слот уже занят или недоступен'
        ]);
    }
}
