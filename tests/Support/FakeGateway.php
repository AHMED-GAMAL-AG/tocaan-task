<?php

namespace Tests\Support;

use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Gateways\AbstractGateway;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;

/**
 * A gateway that exists only in tests, registered purely through config,
 * to prove new gateways plug in without touching application code.
 *
 * Its `outcome` config decides the result: successful, failed, pending or exception.
 */
class FakeGateway extends AbstractGateway
{
    protected array $requiredCredentials = ['token'];

    /**
     * @var list<PaymentRequest>
     */
    public static array $charged = [];

    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'string', 'starts_with:wal_'],
        ];
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        self::$charged[] = $request;

        return match ($this->config('outcome', 'successful')) {
            'failed' => PaymentResult::failed('wallet_empty', ['wallet_id' => $request->detail('wallet_id')]),
            'pending' => PaymentResult::pending('fake_pending_1', ['wallet_id' => $request->detail('wallet_id')]),
            'exception' => throw new PaymentGatewayException('Connection timed out'),
            default => PaymentResult::successful('fake_txn_1', ['wallet_id' => $request->detail('wallet_id')]),
        };
    }
}
