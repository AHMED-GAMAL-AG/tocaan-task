<?php

namespace App\Exceptions;

class OrderAlreadyPaidException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('This order already has a successful or pending payment.');
    }

    public function errorCode(): string
    {
        return 'order_already_paid';
    }
}
