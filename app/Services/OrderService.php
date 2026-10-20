<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Exceptions\OrderHasPaymentsException;
use App\Exceptions\OrderLockedException;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * @param  array{customer: array<string, string|null>, items: list<array{product_name: string, quantity: int, price: numeric}>, notes?: string|null}  $data
     */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $order = $user->orders()->create([
                ...$this->customerAttributes($data['customer']),
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatus::Pending,
            ]);

            $this->replaceItems($order, $data['items']);

            return $order->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated partial payload (customer, items, notes, status).
     *
     * @throws OrderLockedException
     * @throws InvalidOrderStatusTransitionException
     */
    public function update(Order $order, array $data): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw OrderLockedException::cancelled();
        }

        if (array_key_exists('items', $data) && $order->hasPayments()) {
            throw OrderLockedException::itemsHavePayments();
        }

        if (isset($data['status'])) {
            $this->guardStatusChange($order, OrderStatus::from($data['status']));
        }

        return DB::transaction(function () use ($order, $data) {
            $order->fill(Arr::only($data, ['notes', 'status']));

            if (isset($data['customer'])) {
                $order->fill($this->customerAttributes($data['customer']));
            }

            $order->save();

            if (isset($data['items'])) {
                $this->replaceItems($order, $data['items']);
            }

            return $order->load('items');
        });
    }

    /**
     * @throws OrderHasPaymentsException
     */
    public function delete(Order $order): void
    {
        if ($order->hasPayments()) {
            throw new OrderHasPaymentsException;
        }

        $order->delete();
    }

    private function guardStatusChange(Order $order, OrderStatus $next): void
    {
        if ($next === $order->status) {
            return;
        }

        if (! $order->status->canTransitionTo($next)) {
            throw new InvalidOrderStatusTransitionException($order->status, $next);
        }

        if ($order->hasActivePayment()) {
            throw OrderLockedException::paid();
        }
    }

    /**
     * Swap the order's items for the given ones and recalculate the total.
     *
     * @param  list<array{product_name: string, quantity: int, price: numeric}>  $items
     */
    private function replaceItems(Order $order, array $items): void
    {
        $totalCents = 0;
        $rows = [];

        foreach ($items as $item) {
            $unitCents = Money::toCents($item['price']);
            $lineCents = $unitCents * (int) $item['quantity'];
            $totalCents += $lineCents;

            $rows[] = [
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => Money::fromCents($unitCents),
                'line_total' => Money::fromCents($lineCents),
            ];
        }

        $order->items()->delete();
        $order->items()->createMany($rows);
        $order->update(['total' => Money::fromCents($totalCents)]);
    }

    /**
     * @param  array<string, string|null>  $customer
     * @return array<string, string|null>
     */
    private function customerAttributes(array $customer): array
    {
        $map = [
            'name' => 'customer_name',
            'email' => 'customer_email',
            'phone' => 'customer_phone',
            'address' => 'shipping_address',
        ];

        $attributes = [];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $customer)) {
                $attributes[$column] = $customer[$input];
            }
        }

        return $attributes;
    }
}
