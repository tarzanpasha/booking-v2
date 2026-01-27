<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'resource_id' => $this->resource_id,
            'timetable_id' => $this->timetable_id,
            'is_group_booking' => $this->is_group_booking,
            'start' => $this->start,
            'end' => $this->end,
            'status' => $this->status,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resource' => new ResourceResource($this->whenLoaded('resource')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'timetable' => new TimetableResource($this->whenLoaded('timetable')),
            'bookers' => BookerResource::collection($this->whenLoaded('bookers')),
        ];
    }
}
