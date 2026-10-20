<?php

namespace App\Support;

/**
 * Money arithmetic in integer cents, so totals never pick up float drift
 * (0.1 + 0.2 style errors).
 */
final class Money
{
    public static function toCents(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
