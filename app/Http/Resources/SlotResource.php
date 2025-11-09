<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'start' => $this->resource['start'],
            'end' => $this->resource['end'],
            'duration_minutes' => $this->resource['duration_minutes'],
        ];
    }
}
