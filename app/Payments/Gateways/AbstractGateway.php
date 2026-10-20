<?php

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\GatewayNotConfiguredException;
use Illuminate\Support\Str;

/**
 * Optional base class: config access, credential checks and reference
 * generation. A gateway may implement PaymentGateway directly instead.
 */
abstract class AbstractGateway implements PaymentGateway
{
    /**
     * Config keys that must be non-empty for the gateway to be usable.
     *
     * @var list<string>
     */
    protected array $requiredCredentials = [];

    /**
     * @param  array<string, mixed>  $config  This gateway's entry from config/payments.php.
     */
    public function __construct(protected readonly array $config)
    {
        $this->ensureConfigured();
    }

    public function messages(): array
    {
        return [];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function reference(string $prefix): string
    {
        return $prefix.'_'.Str::upper(Str::random(20));
    }

    /**
     * @throws GatewayNotConfiguredException
     */
    private function ensureConfigured(): void
    {
        $missing = array_values(array_filter(
            $this->requiredCredentials,
            fn (string $key) => blank($this->config($key)),
        ));

        if ($missing !== []) {
            throw GatewayNotConfiguredException::missingCredentials(static::class, $missing);
        }
    }
}
