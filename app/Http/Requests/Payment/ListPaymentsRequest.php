<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentStatus;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends PaginatedRequest
{
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
            'method' => ['sometimes', 'string', 'max:50'],
            'order_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'The status filter must be one of: '.implode(', ', PaymentStatus::values()).'.',
        ];
    }

    /**
     * @return array{status?: string, method?: string, order_id?: int}
     */
    public function filters(): array
    {
        return $this->safe()->only(['status', 'method', 'order_id']);
    }
}
