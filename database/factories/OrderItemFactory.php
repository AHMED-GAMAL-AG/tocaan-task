<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitCents = fake()->numberBetween(100, 20000);

        return [
            'order_id' => Order::factory(),
            'product_name' => fake()->words(asText: true),
            'quantity' => $quantity,
            'unit_price' => Money::fromCents($unitCents),
            'line_total' => Money::fromCents($unitCents * $quantity),
        ];
    }
}
