<?php

namespace App\Exceptions;

class OrderLockedException extends BusinessRuleException
{
    public static function cancelled(): self
    {
        return new self('Cancelled orders cannot be modified.');
    }

    public static function itemsHavePayments(): self
    {
        return new self('Items cannot be changed once a payment has been made for the order.');
    }

    public static function paid(): self
    {
        return new self('The status of a paid order cannot be changed.');
    }

    public function errorCode(): string
    {
        return 'order_locked';
    }
}
