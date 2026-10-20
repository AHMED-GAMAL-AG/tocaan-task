<?php

namespace Tests\Unit\Payments;

use App\Payments\Exceptions\GatewayNotConfiguredException;
use App\Payments\Exceptions\UnsupportedPaymentMethodException;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PaypalGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Support\FakeGateway;

class PaymentGatewayManagerTest extends TestCase
{
    public function test_it_resolves_the_gateway_registered_for_a_method(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(CreditCardGateway::class, $manager->gateway('credit_card'));
        $this->assertInstanceOf(PaypalGateway::class, $manager->gateway('paypal'));
    }

    public function test_resolved_gateways_are_reused(): void
    {
        $manager = $this->manager();

        $this->assertSame($manager->gateway('paypal'), $manager->gateway('paypal'));
    }

    public function test_a_new_gateway_is_added_through_config_alone(): void
    {
        $manager = $this->manager([
            'fake_wallet' => ['driver' => FakeGateway::class, 'label' => 'Fake Wallet', 'token' => 'abc'],
        ]);

        $this->assertContains('fake_wallet', $manager->methods());
        $this->assertInstanceOf(FakeGateway::class, $manager->gateway('fake_wallet'));
        $this->assertContains(['method' => 'fake_wallet', 'label' => 'Fake Wallet'], $manager->describe());
    }

    public function test_disabled_gateways_are_not_offered(): void
    {
        $manager = $this->manager([
            'paypal' => ['driver' => PaypalGateway::class, 'enabled' => false],
        ]);

        $this->assertSame(['credit_card'], $manager->methods());
        $this->assertFalse($manager->supports('paypal'));

        $this->expectException(UnsupportedPaymentMethodException::class);
        $manager->gateway('paypal');
    }

    public function test_unknown_method_is_rejected(): void
    {
        $this->expectException(UnsupportedPaymentMethodException::class);
        $this->expectExceptionMessage('[bitcoin]');

        $this->manager()->gateway('bitcoin');
    }

    public function test_driver_must_implement_the_contract(): void
    {
        $manager = $this->manager(['broken' => ['driver' => stdClass::class]]);

        $this->expectException(GatewayNotConfiguredException::class);
        $this->expectExceptionMessage('does not implement the PaymentGateway contract');

        $manager->gateway('broken');
    }

    public function test_label_defaults_to_a_headline_of_the_method(): void
    {
        $manager = $this->manager([
            'bank_transfer' => ['driver' => FakeGateway::class, 'token' => 'abc'],
        ]);

        $this->assertContains(['method' => 'bank_transfer', 'label' => 'Bank Transfer'], $manager->describe());
    }

    /**
     * @param  array<string, array<string, mixed>>  $extra
     */
    private function manager(array $extra = []): PaymentGatewayManager
    {
        return new PaymentGatewayManager(new Container, [
            'credit_card' => ['driver' => CreditCardGateway::class, 'label' => 'Credit Card', 'api_key' => 'k', 'secret' => 's'],
            'paypal' => ['driver' => PaypalGateway::class, 'label' => 'PayPal', 'client_id' => 'i', 'client_secret' => 's'],
            ...$extra,
        ]);
    }
}
