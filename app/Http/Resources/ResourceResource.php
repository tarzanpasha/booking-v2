<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'timetable_id' => $this->timetable_id,
            'resource_type_id' => $this->resource_type_id,
            'options' => $this->options,
            'payload' => $this->payload,
            'resource_config' => $this->resource_config,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'timetable' => new TimetableResource($this->whenLoaded('timetable')),
            'resource_type' => new ResourceTypeResource($this->whenLoaded('resourceType')),
        ];
    }
}
