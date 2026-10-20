<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';

    /**
     * Whether a payment in this status still counts against the order
     * (i.e. blocks another payment attempt).
     */
    public function isActive(): bool
    {
        return $this !== self::Failed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
