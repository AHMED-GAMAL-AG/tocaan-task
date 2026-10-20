<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAsJwt($this->user);
    }

    public function test_all_payments_of_the_user_are_listed_with_pagination(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->failed()->count(2)->create();
        Payment::factory()->for($order)->create();
        Payment::factory()->count(2)->create(); // someone else's

        $this->getJson('/api/v1/payments?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'order_id', 'method', 'status', 'amount', 'currency', 'transaction_reference', 'failure_reason', 'details', 'processed_at', 'created_at']], 'links', 'meta']);
    }

    public function test_payments_can_be_filtered(): void
    {
        $first = Order::factory()->for($this->user)->confirmed()->create();
        $second = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($first)->failed()->create();
        Payment::factory()->for($first)->create();
        Payment::factory()->for($second)->paypal()->create();

        $this->getJson('/api/v1/payments?status=failed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'failed');
        $this->getJson('/api/v1/payments?method=paypal')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_id', $second->id);
        $this->getJson("/api/v1/payments?order_id={$first->id}")->assertJsonCount(2, 'data');
    }

    public function test_payment_filters_are_validated(): void
    {
        $this->getJson('/api/v1/payments?status=refunded&order_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'order_id']);
    }

    public function test_payments_of_a_single_order_are_listed(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        $other = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->failed()->create();
        Payment::factory()->for($order)->create();
        Payment::factory()->for($other)->create();

        $this->getJson("/api/v1/orders/{$order->id}/payments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_id', $order->id)
            ->assertJsonPath('data.1.order_id', $order->id);
    }

    public function test_payments_of_another_users_order_are_not_found(): void
    {
        $order = Order::factory()->confirmed()->create();
        Payment::factory()->for($order)->create();

        $this->getJson("/api/v1/orders/{$order->id}/payments")->assertNotFound();
    }

    public function test_single_payment_can_be_viewed(): void
    {
        $payment = Payment::factory()->for(Order::factory()->for($this->user)->confirmed())->create();

        $this->getJson("/api/v1/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.transaction_reference', $payment->transaction_reference);
    }

    public function test_another_users_payment_is_not_found(): void
    {
        $payment = Payment::factory()->create();

        $this->getJson("/api/v1/payments/{$payment->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Payment not found.');
    }

    public function test_enabled_payment_methods_are_listed(): void
    {
        $this->getJson('/api/v1/payment-methods')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['method' => 'credit_card', 'label' => 'Credit Card'],
                ['method' => 'paypal', 'label' => 'PayPal'],
            ]]);
    }
}
