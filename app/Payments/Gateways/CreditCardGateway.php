<?php

namespace App\Payments\Gateways;

use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;
use App\Rules\LuhnNumber;
use Illuminate\Support\Carbon;

/**
 * Simulated card processor.
 *
 * Outcomes are driven by well-known test card numbers so behaviour is
 * deterministic in tests and in Postman.
 */
class CreditCardGateway extends AbstractGateway
{
    public const string CARD_DECLINED = '4000000000000002';

    public const string CARD_INSUFFICIENT_FUNDS = '4000000000009995';

    protected array $requiredCredentials = ['api_key', 'secret'];

    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'regex:/^\d{12,19}$/', new LuhnNumber],
            'card_holder' => ['required', 'string', 'max:255'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'digits:4'],
            'cvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.regex' => 'The card number must be 12 to 19 digits, without spaces or dashes.',
            'cvv.regex' => 'The CVV must be 3 or 4 digits.',
        ];
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        $number = (string) $request->detail('card_number');

        // Only non-sensitive card data is ever handed back for storage.
        $meta = [
            'brand' => $this->brand($number),
            'last4' => substr($number, -4),
            'card_holder' => $request->detail('card_holder'),
        ];

        if ($this->isExpired((int) $request->detail('expiry_month'), (int) $request->detail('expiry_year'))) {
            return PaymentResult::failed('expired_card', $meta);
        }

        return match ($number) {
            self::CARD_DECLINED => PaymentResult::failed('card_declined', $meta),
            self::CARD_INSUFFICIENT_FUNDS => PaymentResult::failed('insufficient_funds', $meta),
            default => PaymentResult::successful($this->reference('ch'), $meta),
        };
    }

    private function isExpired(int $month, int $year): bool
    {
        return Carbon::create($year, $month)->endOfMonth()->isPast();
    }

    private function brand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4') => 'visa',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $number) => 'mastercard',
            (bool) preg_match('/^3[47]/', $number) => 'amex',
            default => 'unknown',
        };
    }
}
