<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\GatewayNotConfiguredException;
use App\Payments\Exceptions\UnsupportedPaymentMethodException;
use Illuminate\Contracts\Container\Container;

/**
 * Registry that maps a payment method (e.g. "credit_card") to the gateway
 * strategy configured for it in config/payments.php.
 *
 * Adding a gateway never touches this class: register it in config.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $resolved = [];

    /**
     * @param  array<string, array<string, mixed>>  $gateways  The `payments.gateways` config.
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $gateways,
    ) {}

    /**
     * Payment methods that are registered and enabled.
     *
     * @return list<string>
     */
    public function methods(): array
    {
        return array_keys(array_filter(
            $this->gateways,
            fn (array $gateway) => (bool) ($gateway['enabled'] ?? true),
        ));
    }

    public function supports(string $method): bool
    {
        return in_array($method, $this->methods(), true);
    }

    /**
     * Public description of the enabled methods, for API clients.
     *
     * @return list<array{method: string, label: string}>
     */
    public function describe(): array
    {
        return array_map(fn (string $method) => [
            'method' => $method,
            'label' => (string) ($this->gateways[$method]['label'] ?? str($method)->headline()),
        ], $this->methods());
    }

    /**
     * @throws UnsupportedPaymentMethodException
     * @throws GatewayNotConfiguredException
     */
    public function gateway(string $method): PaymentGateway
    {
        if (! $this->supports($method)) {
            throw new UnsupportedPaymentMethodException($method);
        }

        return $this->resolved[$method] ??= $this->resolve($method, $this->gateways[$method]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolve(string $method, array $config): PaymentGateway
    {
        $driver = (string) ($config['driver'] ?? '');

        if (! is_a($driver, PaymentGateway::class, true)) {
            throw GatewayNotConfiguredException::invalidDriver($method, $driver);
        }

        return $this->container->make($driver, ['config' => $config]);
    }
}
