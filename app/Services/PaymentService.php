<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\OrderAlreadyPaidException;
use App\Exceptions\OrderNotPayableException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * Process a payment for an order through the gateway registered for $method.
     *
     * The payment row is written as pending (under a row lock, so two
     * concurrent requests cannot both pass the business rules) before the
     * gateway is called, and is updated with the gateway's outcome after.
     *
     * @param  array<string, mixed>  $details  Gateway-specific payload, validated against the gateway's rules().
     *
     * @throws OrderNotPayableException
     * @throws OrderAlreadyPaidException
     */
    public function process(Order $order, string $method, array $details = []): Payment
    {
        // Resolve first: an unusable gateway must fail before anything is written.
        $gateway = $this->gateways->gateway($method);

        $payment = $this->openPayment($order, $method);

        try {
            $result = $gateway->charge(PaymentRequest::fromPayment($payment, $details));
        } catch (PaymentGatewayException $e) {
            Log::warning('Payment gateway error', ['payment_id' => $payment->id, 'method' => $method, 'error' => $e->getMessage()]);

            $result = PaymentResult::failed('gateway_error');
        } catch (Throwable $e) {
            $this->record($payment, PaymentResult::failed('internal_error'));

            throw $e;
        }

        return $this->record($payment, $result);
    }

    private function openPayment(Order $order, string $method): Payment
    {
        return DB::transaction(function () use ($order, $method) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->status->isPayable()) {
                throw new OrderNotPayableException($locked->status);
            }

            if ($locked->hasActivePayment()) {
                throw new OrderAlreadyPaidException;
            }

            return $locked->payments()->create([
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount' => $locked->total,
                'currency' => config('payments.currency'),
            ]);
        });
    }

    private function record(Payment $payment, PaymentResult $result): Payment
    {
        $payment->update([
            'status' => $result->status,
            'transaction_reference' => $result->transactionReference,
            'failure_reason' => $result->failureReason,
            'meta' => $result->meta ?: null,
            'processed_at' => now(),
        ]);

        return $payment;
    }
}
