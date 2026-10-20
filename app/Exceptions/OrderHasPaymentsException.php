<?php

namespace App\Exceptions;

class OrderHasPaymentsException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Orders with associated payments cannot be deleted.');
    }

    public function errorCode(): string
    {
        return 'order_has_payments';
    }
}
