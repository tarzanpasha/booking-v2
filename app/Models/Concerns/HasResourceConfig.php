<?php

namespace App\Models\Concerns;

use App\ValueObjects\ResourceConfig;

trait HasResourceConfig
{
    public function getResourceConfig(): ResourceConfig
    {
        $config = $this->resource_config ?? [];

        if (empty($config) && method_exists($this, 'resourceType')) {
            $config = $this->resourceType->resource_config ?? [];
        }

        return new ResourceConfig($config);
    }

    public function getEffectiveTimetable()
    {
        if ($this->timetable_id) {
            return $this->timetable;
        }

        if (method_exists($this, 'resourceType') && $this->resourceType?->timetable_id) {
            return $this->resourceType->timetable;
        }

        return null;
    }
}
