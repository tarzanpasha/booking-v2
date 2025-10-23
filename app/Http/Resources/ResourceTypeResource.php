<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'timetable_id' => $this->timetable_id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'options' => $this->options,
            'resource_config' => $this->resource_config,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'timetable' => new TimetableResource($this->whenLoaded('timetable')),
            'resources' => ResourceResource::collection($this->whenLoaded('resources')),
        ];
    }
}
