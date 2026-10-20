<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_to_and_from_cents(): void
    {
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame(1999, Money::toCents(19.99));
        $this->assertSame(500, Money::toCents(5));
        $this->assertSame('19.99', Money::fromCents(1999));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('1234567.00', Money::fromCents(123456700));
    }

    public function test_summing_in_cents_avoids_float_drift(): void
    {
        $this->assertNotSame(0.3, 0.1 + 0.2);
        $this->assertSame('0.30', Money::fromCents(Money::toCents(0.1) + Money::toCents(0.2)));
    }
}
