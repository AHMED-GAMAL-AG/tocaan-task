<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

class OrderNotPayableException extends BusinessRuleException
{
    public function __construct(OrderStatus $status)
    {
        parent::__construct(sprintf(
            'Payments can only be processed for confirmed orders. This order is %s.',
            $status->value,
        ));
    }

    public function errorCode(): string
    {
        return 'order_not_payable';
    }
}
