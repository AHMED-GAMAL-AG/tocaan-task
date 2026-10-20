<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends PaginatedRequest
{
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'The status filter must be one of: '.implode(', ', OrderStatus::values()).'.',
        ];
    }

    public function status(): ?OrderStatus
    {
        return $this->enum('status', OrderStatus::class);
    }
}
