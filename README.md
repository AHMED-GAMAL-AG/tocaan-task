# Order & Payment Management API

A Laravel 13 REST API for managing orders and payments, secured with JWT. Payment
gateways are strategies behind one interface, so adding a gateway means writing
one class and one config entry. No controller, service, route or validation
code changes.

- **Stack:** PHP 8.3+, Laravel 13, `php-open-source-saver/jwt-auth`, SQLite by default (any Laravel-supported database works)
- **Docs:** Postman collection in [`docs/postman`](docs/postman). Every example response in it was recorded from the running API.
- **Tests:** 108 PHPUnit tests (unit + feature), covering payment processing and gateway logic in depth

---

## Contents

1. [Setup](#setup)
2. [Running the tests](#running-the-tests)
3. [API overview](#api-overview)
4. [Payment gateway extensibility](#payment-gateway-extensibility)
5. [Business rules](#business-rules)
6. [Error format](#error-format)
7. [Project structure](#project-structure)
8. [Assumptions and notes](#assumptions-and-notes)

---

## Setup

```bash
git clone <repo-url> order-payment-api
cd order-payment-api

composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret            # writes JWT_SECRET to .env

touch database/database.sqlite    # Windows: type nul > database\database.sqlite
php artisan migrate --seed        # seeds demo@example.com / password

php artisan serve                 # http://127.0.0.1:8000
```

The API lives under `http://127.0.0.1:8000/api/v1`. With Laravel Herd or Valet,
`http://<folder-name>.test/api/v1` works without `php artisan serve`.

To use MySQL or PostgreSQL instead, set `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`,
`DB_USERNAME` and `DB_PASSWORD` in `.env` before migrating.

### Gateway credentials

Gateways read their credentials from `.env` through `config/payments.php`.
`.env.example` ships sandbox values so everything works out of the box:

```dotenv
PAYMENT_CURRENCY=USD

CREDIT_CARD_ENABLED=true
CREDIT_CARD_API_KEY=sandbox_cc_key
CREDIT_CARD_SECRET=sandbox_cc_secret

PAYPAL_ENABLED=true
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=sandbox_paypal_client_id
PAYPAL_CLIENT_SECRET=sandbox_paypal_client_secret
```

A gateway whose required credentials are empty refuses to start. Requests for it
answer `503 gateway_not_configured` and no payment row is written.

### Postman

1. Import `docs/postman/Order-Payment-API.postman_collection.json` (and optionally
   `docs/postman/Local.postman_environment.json`).
2. Set the `base_url` variable if your server is not `http://127.0.0.1:8000/api/v1`.
3. Run **Authentication › Login** (demo account) or **Register**. The JWT is saved to
   `token` automatically and sent as a Bearer token by every other request.
4. **Create order** saves `order_id`; **Process payment** saves `payment_id`.

Each request has a description (fields, rules, simulated outcomes) and saved
success **and** error examples.

---

## Running the tests

```bash
php artisan test
```

Tests run against in-memory SQLite (configured in `phpunit.xml`), so they need no setup.

| Suite | What it covers |
|---|---|
| `tests/Unit/Payments/CreditCardGatewayTest` | approvals, each decline reason, expiry handling, brand detection, only last 4 digits returned, credential checks |
| `tests/Unit/Payments/PaypalGatewayTest` | approval, decline, credential checks |
| `tests/Unit/Payments/PaymentGatewayManagerTest` | resolving strategies, registering a new gateway via config only, disabled gateways, bad drivers, unknown methods |
| `tests/Unit/OrderStatusTest`, `Support/*` | status lifecycle, cent-based money maths, Luhn check |
| `tests/Feature/Auth` | register, login, me, refresh (old token revoked), logout (token revoked), bad and missing tokens |
| `tests/Feature/Orders` | create with server-side totals, validation messages, pagination, status filter, ownership (404), updates, transitions, locks, delete rules |
| `tests/Feature/Payments/ProcessPaymentTest` | card and PayPal payments, declines recorded as failed, retries, confirmed-only rule, double-payment guard, gateway-specific validation, card data never stored |
| `tests/Feature/Payments/PaymentGatewayExtensibilityTest` | a test-only gateway registered **purely through config**, driven end-to-end over HTTP: its own rules, pending results, outages, disabled state, missing credentials |
| `tests/Feature/Payments/ViewPaymentsTest` | listing (all / per order), filters, single payment, ownership, payment-methods endpoint |

Code style is PSR-12 via Laravel Pint: `vendor/bin/pint --test`.

---

## API overview

All endpoints are prefixed with `/api/v1`. Everything except register, login and
refresh requires `Authorization: Bearer <token>`.

### Authentication

| Method | Endpoint | Description | Success |
|---|---|---|---|
| POST | `/auth/register` | Create an account, returns a token | 201 |
| POST | `/auth/login` | Exchange credentials for a token | 200 |
| GET | `/auth/me` | Current user | 200 |
| POST | `/auth/refresh` | New token for the current one (also works for an expired token, within `JWT_REFRESH_TTL`); the old one is blacklisted | 200 |
| POST | `/auth/logout` | Blacklist the current token | 204 |

### Orders

| Method | Endpoint | Description | Success |
|---|---|---|---|
| GET | `/orders?status=&per_page=&page=` | Paginated list of your orders, optional status filter | 200 |
| POST | `/orders` | Create an order; totals are calculated server-side | 201 |
| GET | `/orders/{id}` | One order with items | 200 |
| PATCH/PUT | `/orders/{id}` | Partial update: `customer`, `items`, `notes`, `status` | 200 |
| DELETE | `/orders/{id}` | Delete (only without payments) | 204 |

Create payload:

```json
{
    "customer": { "name": "Jane Doe", "email": "jane@example.com", "phone": "+201000000000", "address": "12 Nile St, Cairo" },
    "items": [
        { "product_name": "Mechanical keyboard", "quantity": 2, "price": 49.99 },
        { "product_name": "USB-C cable", "quantity": 3, "price": 7.50 }
    ],
    "notes": "Deliver after 5pm"
}
```

### Payments

| Method | Endpoint | Description | Success |
|---|---|---|---|
| GET | `/payment-methods` | Enabled payment methods (from the gateway registry) | 200 |
| POST | `/orders/{id}/payments` | Process a payment for the order | 201 |
| GET | `/orders/{id}/payments?status=&method=` | Payments of one order (paginated) | 200 |
| GET | `/payments?status=&method=&order_id=` | All your payments (paginated) | 200 |
| GET | `/payments/{id}` | One payment | 200 |

Process payload:

```json
{
    "payment_method": "credit_card",
    "payment_details": {
        "card_number": "4242424242424242",
        "card_holder": "Jane Doe",
        "expiry_month": 12,
        "expiry_year": 2030,
        "cvv": "123"
    }
}
```

```json
{ "payment_method": "paypal", "payment_details": { "paypal_email": "buyer@example.com" } }
```

#### Simulated gateway outcomes

The gateways are simulations driven by test inputs, so behaviour is deterministic:

| Gateway | Input | Result |
|---|---|---|
| credit_card | `4242424242424242`, or any other Luhn-valid number | `successful` |
| credit_card | `4000000000000002` | `failed` · `card_declined` |
| credit_card | `4000000000009995` | `failed` · `insufficient_funds` |
| credit_card | expiry month/year in the past | `failed` · `expired_card` |
| paypal | any email | `successful` |
| paypal | email starting with `declined` | `failed` · `payer_declined` |

A processed attempt returns **201** whether it succeeded or failed, because the
payment is recorded either way. `data.status` tells you which. See
[Assumptions](#assumptions-and-notes).

### Pagination

List endpoints accept `per_page` (1-100, default 15) and `page`, and return Laravel's
standard `data` / `links` / `meta` envelope.

---

## Payment gateway extensibility

### How it works

```
POST /orders/{id}/payments  { payment_method: "paypal", payment_details: {...} }
        │
        ▼
ProcessPaymentRequest ── asks the registry which methods exist, then merges the
        │                selected gateway's own rules() and messages() into validation
        ▼
PaymentService ───────── enforces order rules, writes a pending Payment under a row lock,
        │                then calls the gateway, then records the PaymentResult
        ▼
PaymentGatewayManager ── maps "paypal" → config('payments.gateways.paypal.driver'),
        │                builds it with its config entry, caches the instance
        ▼
PaymentGateway (strategy)
   ├── CreditCardGateway
   ├── PaypalGateway
   └── <your gateway>
```

Pieces, all in `app/Payments`:

| Piece | Role |
|---|---|
| `Contracts/PaymentGateway` | The strategy interface: `rules()`, `messages()`, `charge(PaymentRequest): PaymentResult` |
| `Gateways/AbstractGateway` | Optional base: holds the gateway's config, refuses to build if `$requiredCredentials` are empty, generates references |
| `PaymentRequest` | Immutable input for a charge (amount, currency, order, customer email, validated details); no Eloquent inside the gateway |
| `PaymentResult` | Immutable outcome: `successful()`, `failed($reason)`, `pending()` (for async gateways) plus non-sensitive `meta` |
| `PaymentGatewayManager` | Registry built from `config/payments.php`; knows which methods are enabled and resolves their strategy |
| `Exceptions/PaymentGatewayException` | Thrown by a gateway when the provider is unreachable; the payment is recorded as `failed` · `gateway_error` |

Because the gateway declares its own validation, the request layer stays generic.
The registry drives the list of accepted `payment_method` values, the
`/payment-methods` endpoint and processing, so none of them change when a gateway is added.

### Adding a new gateway (example: Stripe)

**1. Write the strategy** in `app/Payments/Gateways/StripeGateway.php`:

```php
<?php

namespace App\Payments\Gateways;

use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StripeGateway extends AbstractGateway
{
    // The gateway refuses to build (503 to the client) if these are empty.
    protected array $requiredCredentials = ['secret_key'];

    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'string', 'starts_with:pm_'],
        ];
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        try {
            $response = Http::withToken($this->config('secret_key'))
                ->asForm()
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => Money::toCents($request->amount),
                    'currency' => strtolower($request->currency),
                    'payment_method' => $request->detail('payment_method_id'),
                    'confirm' => 'true',
                    'metadata[order_id]' => $request->orderId,
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException($e->getMessage(), previous: $e);
        }

        return $response->successful()
            ? PaymentResult::successful($response->json('id'))
            : PaymentResult::failed($response->json('error.code', 'stripe_error'));
    }
}
```

**2. Register it** in `config/payments.php`:

```php
'stripe' => [
    'driver' => \App\Payments\Gateways\StripeGateway::class,
    'label' => 'Stripe',
    'enabled' => (bool) env('STRIPE_ENABLED', true),
    'secret_key' => env('STRIPE_SECRET_KEY'),
],
```

**3. Add the credentials** to `.env` (and placeholders to `.env.example`):

```dotenv
STRIPE_SECRET_KEY=sk_test_...
```

Done. `payment_method: "stripe"` is now accepted, its `payment_method_id` field is
validated, it appears in `GET /payment-methods`, and payments go through the same
order rules, locking and recording as every other gateway.

**4. Test it.** Unit-test the class directly (see `CreditCardGatewayTest`), faking
HTTP with `Http::fake()`. `tests/Feature/Payments/PaymentGatewayExtensibilityTest`
shows the end-to-end pattern: register a gateway in config and drive it over HTTP.

To switch a gateway off without deleting code, set its `*_ENABLED` variable to `false`.

**Storing gateway config in the database instead of `.env`:** the manager only needs
an array of gateway entries. Bind `PaymentGatewayManager` in `AppServiceProvider`
with entries read from a `payment_gateways` table (ideally with encrypted credential
columns) instead of `config('payments.gateways')`. Nothing else changes.

---

## Business rules

| Rule | Enforcement |
|---|---|
| Payments only for **confirmed** orders | `PaymentService` → 409 `order_not_payable` |
| One active payment per order: no new attempt while a payment is `successful` or `pending`; `failed` attempts can be retried | `PaymentService` (under `lockForUpdate`) → 409 `order_already_paid` |
| Payment amount = order total (client cannot set it) | `PaymentService` |
| Orders with **any** payment cannot be deleted | `OrderService` → 409 `order_has_payments`, and a `restrictOnDelete` foreign key |
| Status lifecycle: `pending → confirmed \| cancelled`, `confirmed → cancelled`; `cancelled` is final | `OrderStatus` enum → 409 `invalid_status_transition` |
| Cancelled orders cannot be modified | 409 `order_locked` |
| Items cannot change once the order has any payment, since the total would no longer match | 409 `order_locked` |
| Status cannot change once a payment is successful or pending | 409 `order_locked` |
| New orders are always `pending`; totals are always computed by the server | `OrderService` |
| Users only see their own orders and payments | Policies → 404 (not 403, so IDs cannot be probed) |

---

## Error format

| Status | When | Body |
|---|---|---|
| 401 | Missing, invalid, expired or blacklisted token; wrong login | `{ "message": "...", "error": "invalid_credentials" \| "invalid_token" }` (or just `message` for `Unauthenticated.`) |
| 404 | Unknown resource, or one owned by another user | `{ "message": "Order not found." }` |
| 409 | Business rule violated | `{ "message": "...", "error": "order_not_payable" }` |
| 422 | Validation failed | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 429 | Rate limited: 60 req/min per user; 10 req/min per IP on register/login/refresh | `{ "message": "Too Many Attempts." }` |
| 503 | Gateway misconfigured | `{ "message": "...", "error": "gateway_not_configured" }` |

Validation messages name the exact field. For example, `items.1.price` reads
*"The price of item #2 may have at most 2 decimal places."*, and gateway messages read
*"The CVV must be 3 or 4 digits."*

---

## Project structure

```
app/
├── Enums/                     OrderStatus (lifecycle), PaymentStatus
├── Exceptions/                ApiException → BusinessRuleException (409) → one class per rule
├── Http/
│   ├── Controllers/Api/V1/    thin controllers: validate → service → resource
│   ├── Requests/              validation + authorization (PaginatedRequest base, shared order rules trait)
│   └── Resources/             JSON shapes
├── Models/                    Order, OrderItem, Payment, User (JWTSubject)
├── Payments/                  the gateway strategy layer (see above)
├── Policies/                  ownership checks (404 on foreign resources)
├── Rules/LuhnNumber.php
├── Services/                  OrderService, PaymentService (business rules + transactions)
└── Support/Money.php          integer-cent arithmetic
config/payments.php            gateway registry + credentials
docs/postman/                  collection + environment
```

---

## Assumptions and notes

- **Customer details vs. account.** The authenticated user *owns* the order; the
  `customer` block (name, email, phone, address) is the buyer/shipping contact
  captured on the order, which may differ from the account holder.
- **Failed payment returns 201.** Processing creates a payment record whichever way it
  goes. A decline is a valid outcome, not a client error, so the response is `201`
  with `status: "failed"` and a `failure_reason`. Business-rule refusals, where nothing
  is created, are `409`.
- **Pending payments.** Payments are written as `pending` before the gateway is called
  and updated with its outcome. The contract also lets asynchronous gateways return
  `pending`; settling those later via a webhook endpoint is out of scope.
- **Gateways are simulated.** No real money moves; outcomes come from the test inputs above.
- **Card data.** Card number and CVV are validated and passed to the gateway, but only
  brand, last 4 digits and holder name are stored. In production, card capture belongs
  on the client side (e.g. Stripe Elements) so raw card numbers never reach this API.
- **Money** is stored as `DECIMAL(12,2)` and computed in integer cents. Amounts are
  returned as strings (`"122.48"`) to avoid float precision loss in clients.
  Single currency, configured by `PAYMENT_CURRENCY`.
- **API versioning.** Routes live under `/api/v1` so breaking changes can ship as `v2`.
- **Concurrency.** The payment rules run inside a transaction with `lockForUpdate()` on the
  order, so two simultaneous requests cannot both pass the "already paid" check
  (effective on MySQL/PostgreSQL; SQLite serializes writes anyway).
