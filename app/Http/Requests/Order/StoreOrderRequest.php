<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    use OrderPayloadRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->payloadRules(partial: false);
    }
}
