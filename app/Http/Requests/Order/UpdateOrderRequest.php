<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    use OrderPayloadRules {
        messages as payloadMessages;
    }

    private const array UPDATABLE = ['customer', 'items', 'notes', 'status'];

    /**
     * Authorize before validating, so non-owners get a 404 instead of
     * learning anything from validation errors.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->payloadRules(partial: true),
            'status' => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->payloadMessages(),
            'status.enum' => 'The status must be one of: '.implode(', ', OrderStatus::values()).'.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasAny(self::UPDATABLE)) {
                    $validator->errors()->add(
                        'order',
                        'Provide at least one of: '.implode(', ', self::UPDATABLE).'.',
                    );
                }
            },
        ];
    }
}
