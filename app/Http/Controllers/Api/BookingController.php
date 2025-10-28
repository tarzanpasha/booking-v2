<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\Booking\BookingService;
use App\Services\Booking\SlotGenerationService;
use App\Actions\CreateBookingAction;
use App\Actions\ConfirmBookingAction;
use App\Actions\CancelBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\RescheduleBookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\GetSlotsRequest;
use App\Http\Requests/CheckAvailabilityRequest;
use App\Http\Requests/GetResourceBookingsRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ResourceResource;
use App\Http\Resources\SlotResource;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private SlotGenerationService $slotService,
        private CreateBookingAction $createBookingAction,
        private ConfirmBookingAction $confirmBookingAction,
        private CancelBookingAction $cancelBookingAction,
        private RescheduleBookingAction $rescheduleBookingAction
    ) {}

    public function getResources(): JsonResponse
    {
        $resources = Resource::with(['company', 'resourceType', 'timetable'])->get();
        return response()->json(ResourceResource::collection($resources));
    }

    public function getAvailableSlots($resourceId, GetSlotsRequest $request): JsonResponse
    {
        $resource = Resource::findOrFail($resourceId);
        $date = $request->get('date', now()->toDateString());
        $count = $request->get('count', 10);
        $onlyToday = $request->get('only_today', true);

        $from = Carbon::parse($date);
        $slots = $this->bookingService->getNextAvailableSlots($resource, $from, $count, $onlyToday);

        return response()->json(SlotResource::collection($slots));
    }

    public function createBooking(CreateBookingRequest $request): JsonResponse
    {
        try {
            $resource = Resource::findOrFail($request->resource_id);

            $booking = $this->createBookingAction->execute(
                $resource,
                $request->start,
                $request->end,
                $request->booker ?? [],
                $request->is_admin ?? false
            );

            return response()->json([
                'data' => new BookingResource($booking),
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
            $booking = $this->confirmBookingAction->execute($id);

            return response()->json([
                'data' => new BookingResource($booking),
                'message' => 'Бронь успешно подтверждена'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function cancelBooking($id, CancelBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->cancelBookingAction->execute(
                $id,
                $request->cancelled_by ?? 'client',
                $request->reason
            );

            return response()->json([
                'data' => new BookingResource($booking),
                'message' => 'Бронь успешно отменена'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function rescheduleBooking($id, RescheduleBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->rescheduleBookingAction->execute(
                $id,
                $request->new_start,
                $request->new_end,
                $request->requested_by ?? 'client'
            );

            return response()->json([
                'data' => new BookingResource($booking),
                'message' => 'Бронь успешно перенесена'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getBookingsForResource($id, GetResourceBookingsRequest $request): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $bookings = $this->bookingService->getBookingsForResourceInRange(
            $resource,
            $request->from,
            $request->to
        );

        return response()->json(BookingResource::collection($bookings));
    }

    public function checkSlotAvailability(CheckAvailabilityRequest $request): JsonResponse
    {
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
