<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->confirmed(),
            'method' => 'credit_card',
            'status' => PaymentStatus::Successful,
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'transaction_reference' => 'ch_'.Str::upper(Str::random(20)),
            'failure_reason' => null,
            'meta' => ['brand' => 'visa', 'last4' => '4242'],
            'processed_at' => now(),
        ];
    }

    public function failed(string $reason = 'card_declined'): static
    {
        return $this->state([
            'status' => PaymentStatus::Failed,
            'transaction_reference' => null,
            'failure_reason' => $reason,
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => PaymentStatus::Pending,
            'processed_at' => null,
        ]);
    }

    public function paypal(): static
    {
        return $this->state([
            'method' => 'paypal',
            'transaction_reference' => 'PAYID_'.Str::upper(Str::random(20)),
            'meta' => ['payer_email' => fake()->safeEmail(), 'mode' => 'sandbox'],
        ]);
    }
}
