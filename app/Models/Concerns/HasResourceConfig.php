<?php

namespace App\Models\Concerns;

use App\ValueObjects\ResourceConfig;
use Illuminate\Support\Arr;

trait HasResourceConfig
{
    public function getResourceConfig(): ResourceConfig
    {
        $config = $this->resource_config ?? [];

        if (method_exists($this, 'resourceType')) {
            $parentConfig = $this->resourceType->resource_config ?? [];
            $config = $this->mergeConfig($parentConfig, $config);
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

    private function isAssoc(array $array): bool
    {
        // PHP 8.1+ (если доступно)
        if (function_exists('array_is_list')) {
            return !array_is_list($array);
        }

        // Fallback для старых PHP
        $i = 0;
        foreach (array_keys($array) as $k) {
            if ($k !== $i++) {
                return true;
            }
        }
        return false;
    }

    private function mergeConfig(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && $this->isAssoc($base[$key])
                && $this->isAssoc($value)
            ) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
