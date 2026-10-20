<?php

namespace App\Payments\Gateways;

use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;

/**
 * Simulated PayPal checkout.
 *
 * A payer whose email starts with "declined" is refused, any other
 * payer is charged successfully.
 */
class PaypalGateway extends AbstractGateway
{
    protected array $requiredCredentials = ['client_id', 'client_secret'];

    public function rules(): array
    {
        return [
            'paypal_email' => ['required', 'email', 'max:255'],
        ];
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        $email = (string) $request->detail('paypal_email');

        $meta = [
            'payer_email' => $email,
            'mode' => $this->config('mode', 'sandbox'),
        ];

        if (str_starts_with(strtolower($email), 'declined')) {
            return PaymentResult::failed('payer_declined', $meta);
        }

        return PaymentResult::successful($this->reference('PAYID'), $meta);
    }
}
