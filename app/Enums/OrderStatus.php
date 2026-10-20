<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * The statuses this status may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return $next === $this || in_array($next, $this->allowedTransitions(), true);
    }

    public function isPayable(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
