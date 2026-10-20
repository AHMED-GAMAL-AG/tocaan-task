<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('+1##########'),
            'shipping_address' => fake()->address(),
            'status' => OrderStatus::Pending,
            'total' => 0,
            'notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => OrderStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }

    /**
     * Attach items and keep the order total consistent with them.
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $items = OrderItem::factory()->count($count)->for($order)->create();

            $order->update([
                'total' => Money::fromCents($items->sum(fn (OrderItem $item) => Money::toCents($item->line_total))),
            ]);
        });
    }
}
