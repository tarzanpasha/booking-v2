<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'start' => $this['start']->toDateTimeString(),
            'end' => $this['end']->toDateTimeString(),
            'duration_minutes' => $this['start']->diffInMinutes($this['end']),
        ];
    }
}
