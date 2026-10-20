<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAsJwt($this->user);
    }

    // ---- create -------------------------------------------------------------

    public function test_order_is_created_with_items_and_a_calculated_total(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer.name', 'Jane Doe')
            ->assertJsonPath('data.customer.email', 'jane@example.com')
            ->assertJsonPath('data.items.0.product_name', 'Keyboard')
            ->assertJsonPath('data.items.0.unit_price', '49.99')
            ->assertJsonPath('data.items.0.line_total', '99.98')
            ->assertJsonPath('data.items.1.line_total', '0.30')
            ->assertJsonPath('data.total', '100.28')
            ->assertJsonPath('data.payments_count', 0);

        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id, 'total' => '100.28']);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_client_cannot_set_status_or_total_on_create(): void
    {
        $this->postJson('/api/v1/orders', [...$this->payload(), 'status' => 'confirmed', 'total' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '100.28');
    }

    public function test_create_validates_required_fields(): void
    {
        $this->postJson('/api/v1/orders', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer', 'customer.name', 'customer.email', 'items']);
    }

    public function test_create_validates_each_item_with_a_readable_message(): void
    {
        $errors = $this->postJson('/api/v1/orders', $this->payload(items: [
            ['product_name' => 'Ok', 'quantity' => 1, 'price' => 10],
            ['product_name' => '', 'quantity' => 0, 'price' => 1.999],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.product_name', 'items.1.quantity', 'items.1.price'])
            ->json('errors');

        $this->assertSame('The quantity of item #2 field must be at least 1.', $errors['items.1.quantity'][0]);
        $this->assertSame('The price of item #2 may have at most 2 decimal places.', $errors['items.1.price'][0]);
    }

    public function test_create_rejects_an_empty_item_list(): void
    {
        $this->postJson('/api/v1/orders', $this->payload(items: []))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    // ---- list -----------------------------------------------------------------

    public function test_orders_are_listed_with_pagination(): void
    {
        Order::factory()->for($this->user)->withItems()->count(3)->create();

        $this->getJson('/api/v1/orders?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta']);
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        Order::factory()->for($this->user)->count(2)->create();
        Order::factory()->for($this->user)->confirmed()->create();
        Order::factory()->for($this->user)->cancelled()->create();

        $this->getJson('/api/v1/orders?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');

        $this->getJson('/api/v1/orders?status=pending')->assertJsonCount(2, 'data');
    }

    public function test_list_rejects_an_unknown_status_filter(): void
    {
        $this->getJson('/api/v1/orders?status=shipped')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'The status filter must be one of: pending, confirmed, cancelled.');
    }

    public function test_list_rejects_an_oversized_page(): void
    {
        $this->getJson('/api/v1/orders?per_page=500')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    public function test_users_only_see_their_own_orders(): void
    {
        Order::factory()->for($this->user)->create();
        Order::factory()->count(2)->create();

        $this->getJson('/api/v1/orders')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    }

    // ---- show -----------------------------------------------------------------

    public function test_order_can_be_viewed(): void
    {
        $order = Order::factory()->for($this->user)->withItems(2)->create();

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_another_users_order_is_not_found(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound()->assertJsonPath('message', 'Order not found.');
        $this->patchJson("/api/v1/orders/{$order->id}", ['notes' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/orders/{$order->id}")->assertNotFound();
        $this->assertModelExists($order);
    }

    public function test_missing_order_is_not_found(): void
    {
        $this->getJson('/api/v1/orders/999')->assertNotFound()->assertJsonPath('message', 'Order not found.');
    }

    // ---- update ---------------------------------------------------------------

    public function test_items_can_be_replaced_and_the_total_is_recalculated(): void
    {
        $order = Order::factory()->for($this->user)->withItems(3)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", [
            'items' => [['product_name' => 'Monitor', 'quantity' => 2, 'price' => 150]],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total', '300.00');

        $this->assertSame(1, $order->items()->count());
    }

    public function test_customer_details_can_be_partially_updated(): void
    {
        $order = Order::factory()->for($this->user)->create(['customer_name' => 'Old', 'customer_email' => 'old@example.com']);

        $this->patchJson("/api/v1/orders/{$order->id}", ['customer' => ['name' => 'New Name'], 'notes' => 'Leave at door'])
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'New Name')
            ->assertJsonPath('data.customer.email', 'old@example.com')
            ->assertJsonPath('data.notes', 'Leave at door');
    }

    public function test_status_can_be_confirmed(): void
    {
        $order = Order::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_confirmed_order_cannot_go_back_to_pending(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'pending'])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'An order cannot move from confirmed to pending. Allowed: cancelled.',
                'error' => 'invalid_status_transition',
            ]);
    }

    public function test_unpaid_confirmed_order_can_be_cancelled(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->failed()->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_update_requires_at_least_one_field(): void
    {
        $order = Order::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    public function test_update_rejects_an_unknown_status(): void
    {
        $order = Order::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelled_order_cannot_be_modified(): void
    {
        $order = Order::factory()->for($this->user)->cancelled()->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'pending'])
            ->assertConflict()
            ->assertJsonPath('error', 'order_locked');
    }

    public function test_items_cannot_change_once_a_payment_exists(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->withItems()->create();
        Payment::factory()->for($order)->failed()->create();

        $this->patchJson("/api/v1/orders/{$order->id}", [
            'items' => [['product_name' => 'X', 'quantity' => 1, 'price' => 1]],
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'Items cannot be changed once a payment has been made for the order.');
    }

    public function test_paid_order_status_cannot_change(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertConflict()
            ->assertJsonPath('message', 'The status of a paid order cannot be changed.');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_paid_order_notes_can_still_be_updated(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->create();

        $this->patchJson("/api/v1/orders/{$order->id}", ['notes' => 'Gift wrap'])->assertOk();
    }

    // ---- delete ---------------------------------------------------------------

    public function test_order_without_payments_can_be_deleted(): void
    {
        $order = Order::factory()->for($this->user)->withItems()->create();

        $this->deleteJson("/api/v1/orders/{$order->id}")->assertNoContent();

        $this->assertModelMissing($order);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_order_with_payments_cannot_be_deleted(): void
    {
        $order = Order::factory()->for($this->user)->confirmed()->create();
        Payment::factory()->for($order)->failed()->create();

        $this->deleteJson("/api/v1/orders/{$order->id}")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Orders with associated payments cannot be deleted.',
                'error' => 'order_has_payments',
            ]);

        $this->assertModelExists($order);
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    private function payload(?array $items = null): array
    {
        return [
            'customer' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '+201000000000',
                'address' => '1 Main St, Cairo',
            ],
            'items' => $items ?? [
                ['product_name' => 'Keyboard', 'quantity' => 2, 'price' => 49.99],
                ['product_name' => 'Sticker', 'quantity' => 3, 'price' => 0.10],
            ],
            'notes' => 'Deliver after 5pm',
        ];
    }
}
