<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base for exceptions that carry their own HTTP status and a stable,
 * machine-readable error code. Laravel calls render() automatically.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->errorCode(),
        ], $this->status());
    }
}
