<?php

namespace Tests\Unit\Payments;

use App\Enums\PaymentStatus;
use App\Payments\Exceptions\GatewayNotConfiguredException;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\PaymentRequest;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreditCardGatewayTest extends TestCase
{
    private const array CONFIG = ['api_key' => 'key', 'secret' => 'secret'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_valid_card_is_charged_successfully(): void
    {
        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Successful, $result->status);
        $this->assertStringStartsWith('ch_', $result->transactionReference);
        $this->assertNull($result->failureReason);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function decliningCards(): array
    {
        return [
            'generic decline' => [CreditCardGateway::CARD_DECLINED, 'card_declined'],
            'insufficient funds' => [CreditCardGateway::CARD_INSUFFICIENT_FUNDS, 'insufficient_funds'],
        ];
    }

    #[DataProvider('decliningCards')]
    public function test_test_cards_are_declined_with_a_reason(string $card, string $reason): void
    {
        $result = $this->gateway()->charge($this->request(['card_number' => $card]));

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame($reason, $result->failureReason);
        $this->assertNull($result->transactionReference);
    }

    public function test_expired_card_is_declined(): void
    {
        $result = $this->gateway()->charge($this->request(['expiry_month' => 9, 'expiry_year' => 2026]));

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('expired_card', $result->failureReason);
    }

    public function test_card_expiring_this_month_is_still_valid(): void
    {
        $result = $this->gateway()->charge($this->request(['expiry_month' => 10, 'expiry_year' => 2026]));

        $this->assertTrue($result->isSuccessful());
    }

    public function test_only_non_sensitive_card_data_is_returned(): void
    {
        $result = $this->gateway()->charge($this->request());

        $this->assertSame(['brand' => 'visa', 'last4' => '4242', 'card_holder' => 'Jane Doe'], $result->meta);
        $this->assertStringNotContainsString('4242424242424242', json_encode($result->meta));
        $this->assertArrayNotHasKey('cvv', $result->meta);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function brands(): array
    {
        return [
            'visa' => ['4242424242424242', 'visa'],
            'mastercard' => ['5555555555554444', 'mastercard'],
            'amex' => ['378282246310005', 'amex'],
            'unknown' => ['6011111111111117', 'unknown'],
        ];
    }

    #[DataProvider('brands')]
    public function test_card_brand_is_detected(string $card, string $brand): void
    {
        $result = $this->gateway()->charge($this->request(['card_number' => $card]));

        $this->assertSame($brand, $result->meta['brand']);
    }

    public function test_it_refuses_to_build_without_credentials(): void
    {
        $this->expectException(GatewayNotConfiguredException::class);
        $this->expectExceptionMessage('CreditCardGateway');
        $this->expectExceptionMessage('secret');

        new CreditCardGateway(['api_key' => 'key', 'secret' => '']);
    }

    public function test_it_declares_validation_rules_for_its_details(): void
    {
        $this->assertSame(
            ['card_number', 'card_holder', 'expiry_month', 'expiry_year', 'cvv'],
            array_keys($this->gateway()->rules()),
        );
    }

    private function gateway(): CreditCardGateway
    {
        return new CreditCardGateway(self::CONFIG);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function request(array $overrides = []): PaymentRequest
    {
        return new PaymentRequest(
            paymentId: 1,
            orderId: 1,
            amount: '99.90',
            currency: 'USD',
            customerEmail: 'jane@example.com',
            details: [
                'card_number' => '4242424242424242',
                'card_holder' => 'Jane Doe',
                'expiry_month' => 12,
                'expiry_year' => 2030,
                'cvv' => '123',
                ...$overrides,
            ],
        );
    }
}
