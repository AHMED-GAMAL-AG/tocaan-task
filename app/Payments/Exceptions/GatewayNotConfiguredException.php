<?php

namespace App\Payments\Exceptions;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway exists in config but cannot be used (bad driver class or
 * missing credentials). This is an operator problem, not a client one.
 */
class GatewayNotConfiguredException extends ApiException
{
    /**
     * @param  list<string>  $keys
     */
    public static function missingCredentials(string $gateway, array $keys): self
    {
        return new self(sprintf(
            'Payment gateway [%s] is missing configuration: %s.',
            class_basename($gateway),
            implode(', ', $keys),
        ));
    }

    public static function invalidDriver(string $method, string $driver): self
    {
        return new self(sprintf(
            'Payment method [%s] is mapped to [%s], which does not implement the PaymentGateway contract.',
            $method,
            $driver,
        ));
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function errorCode(): string
    {
        return 'gateway_not_configured';
    }

    public function render(): JsonResponse
    {
        // Do not leak config details to API clients outside debug mode.
        return response()->json([
            'message' => config('app.debug') ? $this->getMessage() : 'This payment method is temporarily unavailable.',
            'error' => $this->errorCode(),
        ], $this->status());
    }
}
