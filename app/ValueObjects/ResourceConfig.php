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
    public ?int $min_advance_time = 0;
    public ?int $cancellation_time = null;
    public ?int $reschedule_time = null;
    public ?int $reminder_time = null;

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
            'min_advance_time' => $this->min_advance_time,
            'cancellation_time' => $this->cancellation_time,
            'reschedule_time' => $this->reschedule_time,
            'reminder_time' => $this->reminder_time,
        ];
    }

    public function isGroupResource(): bool
    {
        return $this->max_participants !== null && $this->max_participants > 1;
    }

    public function requiresConfirmation(): bool
    {
        return $this->require_confirmation === true;
    }

    public function isFixedStrategy(): bool
    {
        return $this->slot_strategy === SlotStrategy::FIXED;
    }

    public function isDynamicStrategy(): bool
    {
        return $this->slot_strategy === SlotStrategy::DYNAMIC;
    }

    public function canCancel(\DateTime $bookingStart): bool
    {
        if ($this->cancellation_time === null) return true;

        // Для строгих ограничений: если cancellation_time = 0, отмена невозможна
        if ($this->cancellation_time === 0) {
            return false;
        }

        $now = new \DateTime();

        // todo: нет учета прошлое/будущее, используется или lt/gt, или true вторым аргументом.
        // ПЕРЕПРОВЕРИТЬ ПРАВИЛЬНОСТЬ!
        $diff = $now->diff($bookingStart);
        $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;

        return $minutes >= $this->cancellation_time;
    }

    public function canReschedule(\DateTime $bookingStart): bool
    {
        if ($this->reschedule_time === null) return true;

        // Для строгих ограничений: если reschedule_time = 0, перенос невозможен
        if ($this->reschedule_time === 0) {
            return false;
        }

        $now = new \DateTime();
        // todo: нет учета прошлое/будущее, используется или lt/gt, или true вторым аргументом.
        // ПЕРЕПРОВЕРИТЬ ПРАВИЛЬНОСТЬ!
        $diff = $now->diff($bookingStart);
        $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;

        return $minutes >= $this->reschedule_time;
    }

    public function shouldSendReminder(\DateTime $bookingStart): bool
    {
        if ($this->reminder_time === null) return false;

        $now = new \DateTime();
        $diff = $now->diff($bookingStart);
        // todo: нет учета прошлое/будущее, используется или lt/gt, или true вторым аргументом.
        // ПЕРЕПРОВЕРИТЬ ПРАВИЛЬНОСТЬ!
        $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;

        return $minutes <= $this->reminder_time;
    }
}
