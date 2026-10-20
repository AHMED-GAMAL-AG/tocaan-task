<?php

namespace Tests\Unit\Payments;

use App\Enums\PaymentStatus;
use App\Payments\Exceptions\GatewayNotConfiguredException;
use App\Payments\Gateways\PaypalGateway;
use App\Payments\PaymentRequest;
use PHPUnit\Framework\TestCase;

class PaypalGatewayTest extends TestCase
{
    public function test_payer_is_charged_successfully(): void
    {
        $result = $this->gateway()->charge($this->request('buyer@example.com'));

        $this->assertSame(PaymentStatus::Successful, $result->status);
        $this->assertStringStartsWith('PAYID_', $result->transactionReference);
        $this->assertSame(['payer_email' => 'buyer@example.com', 'mode' => 'live'], $result->meta);
    }

    public function test_declined_payer_fails(): void
    {
        $result = $this->gateway()->charge($this->request('Declined.buyer@example.com'));

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('payer_declined', $result->failureReason);
    }

    public function test_it_refuses_to_build_without_credentials(): void
    {
        $this->expectException(GatewayNotConfiguredException::class);
        $this->expectExceptionMessage('client_id, client_secret');

        new PaypalGateway([]);
    }

    private function gateway(): PaypalGateway
    {
        return new PaypalGateway(['client_id' => 'id', 'client_secret' => 'secret', 'mode' => 'live']);
    }

    private function request(string $email): PaymentRequest
    {
        return new PaymentRequest(1, 1, '10.00', 'USD', 'jane@example.com', ['paypal_email' => $email]);
    }
}
