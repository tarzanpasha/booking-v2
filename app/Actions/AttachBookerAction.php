<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Booker;

class AttachBookerAction
{
    public function execute(Booking $booking, array $bookerData): void
    {
        $booker = Booker::firstOrCreate(
            [
                'external_id' => $bookerData['external_id'] ?? null,
                'type' => $bookerData['type'] ?? 'client'
            ],
            [
                'name' => $bookerData['name'] ?? null,
                'email' => $bookerData['email'] ?? null,
                'phone' => $bookerData['phone'] ?? null,
                'metadata' => $bookerData['metadata'] ?? null,
            ]
        );

        // Прикрепляем booker к бронированию через связь многие-ко-многим
        $booking->bookers()->syncWithoutDetaching([$booker->id]);
    }
}
