<?php

namespace App\Http\Requests\Payment;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates `payment_method` against the registered gateways, then merges in
 * the selected gateway's own rules and messages for `payment_details`.
 * A new gateway therefore brings its validation with it.
 */
class ProcessPaymentRequest extends FormRequest
{
    private const string DETAILS = 'payment_details';

    public function authorize(): Response
    {
        return Gate::inspect('pay', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $gatewayRules = $this->selectedGateway()?->rules() ?? [];

        return [
            'payment_method' => ['required', 'string', Rule::in($this->gateways()->methods())],
            self::DETAILS => [$gatewayRules === [] ? 'sometimes' : 'required', 'array'],
            ...$this->prefixed($gatewayRules),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'The selected payment method is not supported. Supported methods: '
                .implode(', ', $this->gateways()->methods()).'.',
            ...$this->prefixed($this->selectedGateway()?->messages() ?? []),
        ];
    }

    /**
     * "payment_details.card_number" reads as "card number" in messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = array_keys($this->selectedGateway()?->rules() ?? []);

        return $this->prefixed(array_combine($fields, str_replace('_', ' ', $fields)));
    }

    public function paymentMethod(): string
    {
        return $this->validated('payment_method');
    }

    /**
     * Only the fields the gateway declared, nothing extra the client sent.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->validated(self::DETAILS, []);
    }

    private function selectedGateway(): ?PaymentGateway
    {
        $method = $this->input('payment_method');

        if (! is_string($method) || ! $this->gateways()->supports($method)) {
            return null;
        }

        return $this->gateways()->gateway($method);
    }

    /**
     * @template T
     *
     * @param  array<string, T>  $values
     * @return array<string, T>
     */
    private function prefixed(array $values): array
    {
        $prefixed = [];

        foreach ($values as $key => $value) {
            $prefixed[self::DETAILS.'.'.$key] = $value;
        }

        return $prefixed;
    }

    private function gateways(): PaymentGatewayManager
    {
        return $this->container->make(PaymentGatewayManager::class);
    }
}
