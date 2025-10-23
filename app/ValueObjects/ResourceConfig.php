<?php

namespace App\ValueObjects;

use App\Enums\SlotStrategy;
use InvalidArgumentException;

class ResourceConfig
{
    public ?bool $require_confirmation = false;
    public ?int $slot_duration_minutes = 60;
    public ?int $max_participants = null;
    public ?SlotStrategy $slot_strategy = SlotStrategy::FIXED;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->setProperty($key, $value);
            }
        }

        $this->validate();
    }

    private function setProperty(string $key, $value): void
    {
        if ($key === 'slot_strategy') {
            $this->slot_strategy = is_string($value) ? SlotStrategy::from($value) : $value;
        } else {
            $this->$key = $value;
        }
    }

    private function validate(): void
    {
        if ($this->slot_duration_minutes !== null && $this->slot_duration_minutes <= 0) {
            throw new InvalidArgumentException('Slot duration must be positive');
        }

        if ($this->max_participants !== null && $this->max_participants < 0) {
            throw new InvalidArgumentException('Max participants cannot be negative');
        }
    }

    public function toArray(): array
    {
        return [
            'require_confirmation' => $this->require_confirmation,
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'max_participants' => $this->max_participants,
            'slot_strategy' => $this->slot_strategy->value,
        ];
    }

    public function isFixedStrategy(): bool
    {
        return $this->slot_strategy === SlotStrategy::FIXED;
    }

    public function isDinamicStrategy(): bool
    {
        return $this->slot_strategy === SlotStrategy::DINAMIC;
    }

    public function getSlotStrategy(): SlotStrategy
    {
        return $this->slot_strategy;
    }

    public function getSlotDurationHours(): float
    {
        return $this->slot_duration_minutes / 60;
    }

    public function requiresConfirmation(): bool
    {
        return $this->require_confirmation === true;
    }

    public function hasMaxParticipants(): bool
    {
        return $this->max_participants !== null;
    }
}
