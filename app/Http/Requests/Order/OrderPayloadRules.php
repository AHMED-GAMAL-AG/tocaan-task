<?php

namespace App\Http\Requests\Order;

/**
 * Field rules shared by creating and updating an order. On update every
 * top-level field becomes optional, but anything sent is validated the same way.
 */
trait OrderPayloadRules
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'customer' => [$presence, 'array:name,email,phone,address'],
            'customer.name' => [$presence, 'string', 'max:255'],
            'customer.email' => [$presence, 'string', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.address' => ['nullable', 'string', 'max:500'],

            'items' => [$presence, 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['array:product_name,quantity,price'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer.name' => 'customer name',
            'customer.email' => 'customer email',
            'customer.phone' => 'customer phone',
            'customer.address' => 'customer address',
            'items.*.product_name' => 'product name of item #:position',
            'items.*.quantity' => 'quantity of item #:position',
            'items.*.price' => 'price of item #:position',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'An order must contain at least one item.',
            'items.*.price.decimal' => 'The :attribute may have at most 2 decimal places.',
        ];
    }
}
