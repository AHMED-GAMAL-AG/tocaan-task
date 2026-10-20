<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * @return array<string, array{OrderStatus, OrderStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'pending -> confirmed' => [OrderStatus::Pending, OrderStatus::Confirmed, true],
            'pending -> cancelled' => [OrderStatus::Pending, OrderStatus::Cancelled, true],
            'confirmed -> pending' => [OrderStatus::Confirmed, OrderStatus::Pending, false],
            'confirmed -> cancelled' => [OrderStatus::Confirmed, OrderStatus::Cancelled, true],
            'cancelled -> pending' => [OrderStatus::Cancelled, OrderStatus::Pending, false],
            'cancelled -> confirmed' => [OrderStatus::Cancelled, OrderStatus::Confirmed, false],
            'same status is a no-op' => [OrderStatus::Cancelled, OrderStatus::Cancelled, true],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transitions(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_only_confirmed_orders_are_payable(): void
    {
        $this->assertTrue(OrderStatus::Confirmed->isPayable());
        $this->assertFalse(OrderStatus::Pending->isPayable());
        $this->assertFalse(OrderStatus::Cancelled->isPayable());
    }
}
