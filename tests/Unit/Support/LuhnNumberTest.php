<?php

namespace Tests\Unit\Support;

use App\Rules\LuhnNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LuhnNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function numbers(): array
    {
        return [
            'visa test card' => ['4242424242424242', true],
            'mastercard test card' => ['5555555555554444', true],
            'amex test card' => ['378282246310005', true],
            'last digit off' => ['4242424242424241', false],
            'transposed digits' => ['4242424242424224', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_checksum(string $number, bool $valid): void
    {
        $this->assertSame($valid, LuhnNumber::passes($number));
    }

    public function test_rule_rejects_non_digit_input(): void
    {
        $failed = false;

        (new LuhnNumber)->validate('card_number', '4242-4242-4242-4242', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }
}
