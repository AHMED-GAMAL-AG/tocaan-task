<?php

namespace App\Payments\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedPaymentMethodException extends ApiException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Payment method [%s] is not supported.', $method));
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function errorCode(): string
    {
        return 'unsupported_payment_method';
    }
}
