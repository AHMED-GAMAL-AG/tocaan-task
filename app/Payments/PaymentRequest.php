<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * Everything a gateway needs to charge a payment, decoupled from Eloquent.
 */
final readonly class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $details  Gateway-specific, already validated by the gateway's rules().
     */
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public string $amount,
        public string $currency,
        public string $customerEmail,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function fromPayment(Payment $payment, array $details): self
    {
        return new self(
            paymentId: $payment->id,
            orderId: $payment->order_id,
            amount: $payment->amount,
            currency: $payment->currency,
            customerEmail: $payment->order->customer_email,
            details: $details,
        );
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }
}
