<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

class InvalidOrderStatusTransitionException extends BusinessRuleException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        $allowed = array_map(fn (OrderStatus $status) => $status->value, $from->allowedTransitions());

        parent::__construct(sprintf(
            'An order cannot move from %s to %s. Allowed: %s.',
            $from->value,
            $to->value,
            $allowed === [] ? 'none' : implode(', ', $allowed),
        ));
    }

    public function errorCode(): string
    {
        return 'invalid_status_transition';
    }
}
