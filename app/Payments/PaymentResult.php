<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

/**
 * The outcome a gateway reports back for a single charge attempt.
 */
final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $meta  Non-sensitive data worth keeping (never full card numbers or secrets).
     */
    private function __construct(
        public PaymentStatus $status,
        public ?string $transactionReference = null,
        public ?string $failureReason = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function successful(string $transactionReference, array $meta = []): self
    {
        return new self(PaymentStatus::Successful, $transactionReference, null, $meta);
    }

    /**
     * For asynchronous gateways that confirm the charge later (e.g. via webhook).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function pending(string $transactionReference, array $meta = []): self
    {
        return new self(PaymentStatus::Pending, $transactionReference, null, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failed(string $reason, array $meta = [], ?string $transactionReference = null): self
    {
        return new self(PaymentStatus::Failed, $transactionReference, $reason, $meta);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }
}
