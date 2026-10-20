<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a demo account with orders in every state.
     *
     * Login: demo@example.com / password
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        Order::factory()->for($user)->withItems(2)->count(2)->create();
        Order::factory()->for($user)->withItems(3)->confirmed()->create();
        Order::factory()->for($user)->withItems(1)->cancelled()->create();

        $paid = Order::factory()->for($user)->withItems(2)->confirmed()->create();
        Payment::factory()->for($paid)->create(['amount' => $paid->refresh()->total]);
    }
}
