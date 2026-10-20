<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PaypalGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 code recorded on every payment.
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Each key is a payment method clients send as `payment_method`. The
    | `driver` is a class implementing App\Payments\Contracts\PaymentGateway;
    | the whole entry is passed to its constructor as `$config`, so any
    | credential a gateway needs is declared here and read from .env.
    |
    | To add a gateway: write the class, add an entry here, add its keys to
    | .env. Validation, the payment-methods endpoint and processing pick it
    | up automatically. Set `enabled` to false to switch a method off.
    |
    */

    'gateways' => [

        'credit_card' => [
            'driver' => CreditCardGateway::class,
            'label' => 'Credit Card',
            'enabled' => (bool) env('CREDIT_CARD_ENABLED', true),
            'api_key' => env('CREDIT_CARD_API_KEY'),
            'secret' => env('CREDIT_CARD_SECRET'),
        ],

        'paypal' => [
            'driver' => PaypalGateway::class,
            'label' => 'PayPal',
            'enabled' => (bool) env('PAYPAL_ENABLED', true),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        ],

    ],

];
