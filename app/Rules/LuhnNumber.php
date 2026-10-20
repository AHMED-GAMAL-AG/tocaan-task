<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks a card number against the Luhn (mod 10) checksum.
 */
class LuhnNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ctype_digit($value) || ! self::passes($value)) {
            $fail('The :attribute is not a valid card number.');
        }
    }

    public static function passes(string $number): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
