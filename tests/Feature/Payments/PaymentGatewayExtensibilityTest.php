<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * Registers a brand-new gateway through config only and drives it through
 * the real HTTP endpoints: validation, processing, listing and failure paths.
 */
class PaymentGatewayExtensibilityTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        FakeGateway::$charged = [];

        $user = User::factory()->create();
        $this->order = Order::factory()->for($user)->confirmed()->withItems()->create()->refresh();
        $this->actingAsJwt($user);
    }

    public function test_a_config_registered_gateway_is_offered_and_charged(): void
    {
        $this->registerFakeGateway();

        $this->getJson('/api/v1/payment-methods')
            ->assertJsonFragment(['method' => 'fake_wallet', 'label' => 'Fake Wallet']);

        $this->postJson("/api/v1/orders/{$this->order->id}/payments", [
            'payment_method' => 'fake_wallet',
            'payment_details' => ['wallet_id' => 'wal_123', 'ignored' => 'dropped'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'fake_wallet')
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.transaction_reference', 'fake_txn_1')
            ->assertJsonPath('data.details', ['wallet_id' => 'wal_123']);

        $this->assertCount(1, FakeGateway::$charged);
        $request = FakeGateway::$charged[0];
        $this->assertSame($this->order->id, $request->orderId);
        $this->assertSame($this->order->total, $request->amount);
        $this->assertSame($this->order->customer_email, $request->customerEmail);
        $this->assertSame(['wallet_id' => 'wal_123'], $request->details, 'Only fields declared in rules() reach the gateway.');
    }

    public function test_the_new_gateways_own_rules_are_enforced(): void
    {
        $this->registerFakeGateway();

        $errors = $this->postJson("/api/v1/orders/{$this->order->id}/payments", [
            'payment_method' => 'fake_wallet',
            'payment_details' => ['wallet_id' => 'abc'],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame(['The wallet id field must start with one of the following: wal_.'], $errors['payment_details.wallet_id']);
        $this->assertSame([], FakeGateway::$charged);
    }

    public function test_a_pending_result_is_recorded_and_blocks_another_attempt(): void
    {
        $this->registerFakeGateway(['outcome' => 'pending']);

        $this->pay()->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->pay()->assertConflict()->assertJsonPath('error', 'order_already_paid');
    }

    public function test_a_gateway_outage_is_recorded_as_a_failed_payment(): void
    {
        $this->registerFakeGateway(['outcome' => 'exception']);

        $this->pay()
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'gateway_error');
    }

    public function test_a_disabled_gateway_is_rejected(): void
    {
        $this->registerFakeGateway(['enabled' => false]);

        $this->pay()->assertUnprocessable()->assertJsonValidationErrors(['payment_method']);
        $this->getJson('/api/v1/payment-methods')->assertJsonMissing(['method' => 'fake_wallet']);
    }

    public function test_a_gateway_missing_credentials_is_unavailable_and_writes_nothing(): void
    {
        $this->registerFakeGateway(['token' => null]);

        $this->pay()
            ->assertServiceUnavailable()
            ->assertJsonPath('error', 'gateway_not_configured');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_credentials_are_hidden_from_clients_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->registerFakeGateway(['token' => null]);

        $this->pay()->assertExactJson([
            'message' => 'This payment method is temporarily unavailable.',
            'error' => 'gateway_not_configured',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function registerFakeGateway(array $overrides = []): void
    {
        config(['payments.gateways.fake_wallet' => [
            'driver' => FakeGateway::class,
            'label' => 'Fake Wallet',
            'token' => 'secret-token',
            ...$overrides,
        ]]);
    }

    private function pay(): TestResponse
    {
        return $this->postJson("/api/v1/orders/{$this->order->id}/payments", [
            'payment_method' => 'fake_wallet',
            'payment_details' => ['wallet_id' => 'wal_123'],
        ]);
    }
}
