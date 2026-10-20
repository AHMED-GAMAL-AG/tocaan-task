<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\CreditCardGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAsJwt($this->user);
    }

    public function test_confirmed_order_is_paid_by_credit_card(): void
    {
        $order = $this->confirmedOrder();

        $response = $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment());

        $response->assertCreated()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.method', 'credit_card')
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.amount', $order->total)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.failure_reason', null)
            ->assertJsonPath('data.details', ['brand' => 'visa', 'last4' => '4242', 'card_holder' => 'Jane Doe']);

        $this->assertStringStartsWith('ch_', $response->json('data.transaction_reference'));
        $this->assertNotNull($response->json('data.processed_at'));
    }

    public function test_full_card_number_and_cvv_are_never_stored(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())->assertCreated();

        $stored = json_encode(Payment::sole()->getAttributes());
        $this->assertStringNotContainsString('4242424242424242', $stored);
        $this->assertStringNotContainsString('"cvv"', $stored);
    }

    public function test_confirmed_order_is_paid_by_paypal(): void
    {
        $order = $this->confirmedOrder();

        $response = $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => 'paypal',
            'payment_details' => ['paypal_email' => 'buyer@example.com'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.method', 'paypal')
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.details.payer_email', 'buyer@example.com');

        $this->assertStringStartsWith('PAYID_', $response->json('data.transaction_reference'));
    }

    public function test_declined_card_is_recorded_as_a_failed_payment(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment(['card_number' => CreditCardGateway::CARD_DECLINED]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'card_declined')
            ->assertJsonPath('data.transaction_reference', null);

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    }

    public function test_order_can_be_retried_after_a_failed_payment(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment(['card_number' => CreditCardGateway::CARD_INSUFFICIENT_FUNDS]))
            ->assertJsonPath('data.status', 'failed');

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertCreated()
            ->assertJsonPath('data.status', 'successful');

        $this->assertSame(2, $order->payments()->count());
    }

    public function test_pending_order_cannot_be_paid(): void
    {
        $order = Order::factory()->for($this->user)->withItems()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Payments can only be processed for confirmed orders. This order is pending.',
                'error' => 'order_not_payable',
            ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        $order = Order::factory()->for($this->user)->withItems()->cancelled()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertConflict()
            ->assertJsonPath('error', 'order_not_payable');
    }

    public function test_order_cannot_be_paid_twice(): void
    {
        $order = $this->confirmedOrder();
        Payment::factory()->for($order)->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertConflict()
            ->assertJsonPath('error', 'order_already_paid');

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_order_with_a_pending_payment_cannot_be_paid_again(): void
    {
        $order = $this->confirmedOrder();
        Payment::factory()->for($order)->pending()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertConflict()
            ->assertJsonPath('error', 'order_already_paid');
    }

    public function test_payment_method_is_required_and_must_be_supported(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);

        $this->postJson("/api/v1/orders/{$order->id}/payments", ['payment_method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', 'The selected payment method is not supported. Supported methods: credit_card, paypal.');
    }

    public function test_gateway_specific_details_are_validated(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", ['payment_method' => 'credit_card'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_details',
                'payment_details.card_number',
                'payment_details.card_holder',
                'payment_details.expiry_month',
                'payment_details.expiry_year',
                'payment_details.cvv',
            ]);
    }

    public function test_card_number_must_pass_the_luhn_check(): void
    {
        $order = $this->confirmedOrder();

        $errors = $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment(['card_number' => '4242424242424241']))
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame(['The card number is not a valid card number.'], $errors['payment_details.card_number']);
    }

    public function test_gateway_supplies_its_own_validation_messages(): void
    {
        $order = $this->confirmedOrder();

        $errors = $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment(['card_number' => '4242 4242', 'cvv' => '12']))
            ->assertUnprocessable()
            ->json('errors');

        $this->assertContains('The card number must be 12 to 19 digits, without spaces or dashes.', $errors['payment_details.card_number']);
        $this->assertSame(['The CVV must be 3 or 4 digits.'], $errors['payment_details.cvv']);
    }

    public function test_paypal_requires_a_payer_email(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => 'paypal',
            'payment_details' => ['paypal_email' => 'nope'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_details.paypal_email']);
    }

    public function test_another_users_order_cannot_be_paid(): void
    {
        $order = Order::factory()->confirmed()->withItems()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_amount_is_taken_from_the_order_not_the_client(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/api/v1/orders/{$order->id}/payments", [...$this->cardPayment(), 'amount' => '0.01'])
            ->assertCreated()
            ->assertJsonPath('data.amount', $order->total);
    }

    public function test_payment_is_stored_with_its_final_status(): void
    {
        $order = $this->confirmedOrder();

        $id = $this->postJson("/api/v1/orders/{$order->id}/payments", $this->cardPayment())->json('data.id');

        $this->assertSame(PaymentStatus::Successful, Payment::find($id)->status);
    }

    private function confirmedOrder(): Order
    {
        return Order::factory()->for($this->user)->confirmed()->withItems(2)->create()->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function cardPayment(array $overrides = []): array
    {
        return [
            'payment_method' => 'credit_card',
            'payment_details' => [
                'card_number' => '4242424242424242',
                'card_holder' => 'Jane Doe',
                'expiry_month' => 12,
                'expiry_year' => (int) date('Y') + 3,
                'cvv' => '123',
                ...$overrides,
            ],
        ];
    }
}
