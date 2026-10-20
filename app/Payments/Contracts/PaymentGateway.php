<?php

namespace App\Payments\Contracts;

use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;

/**
 * Strategy contract every payment gateway implements.
 *
 * A gateway owns two things: the shape of the payment details it needs
 * (validation rules) and the logic of charging them. Everything else -
 * order rules, persistence, HTTP - lives outside the gateway.
 */
interface PaymentGateway
{
    /**
     * Validation rules for the gateway-specific `payment_details` payload,
     * keyed by field name relative to `payment_details`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Custom validation messages for those rules, keyed like Laravel's
     * messages() but relative to `payment_details` (e.g. "cvv.regex").
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Charge the payment and report the outcome.
     *
     * Business declines (card declined, insufficient funds, ...) are
     * returned as a failed result. Throw a PaymentGatewayException only
     * when the gateway itself could not be reached or answered garbage.
     *
     * @throws PaymentGatewayException
     */
    public function charge(PaymentRequest $request): PaymentResult;
}
