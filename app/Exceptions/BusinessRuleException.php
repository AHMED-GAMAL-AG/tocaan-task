<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * The request is valid, but the current state of the resource forbids it.
 */
abstract class BusinessRuleException extends ApiException
{
    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
