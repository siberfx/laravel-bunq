# Laravel bunq

[![Latest Version on Packagist](https://img.shields.io/packagist/v/siberfx/laravel-bunq.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/siberfx/laravel-bunq)
[![Total Downloads](https://img.shields.io/packagist/dt/siberfx/laravel-bunq.svg?style=flat-square&v=2)](https://packagist.org/packages/siberfx/laravel-bunq)
[![PHP Version](https://img.shields.io/packagist/dependency-v/siberfx/laravel-bunq/php?style=flat-square&logo=php)](https://packagist.org/packages/siberfx/laravel-bunq)
[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Tests](https://img.shields.io/github/actions/workflow/status/siberfx/laravel-bunq/tests.yml?branch=main&style=flat-square&label=tests&logo=github)](https://github.com/siberfx/laravel-bunq/actions/workflows/tests.yml)
[![Cashier Compatible](https://img.shields.io/badge/Cashier-compatible-635BFF?style=flat-square)](#cashier-compatibility)
[![bunq SDK](https://img.shields.io/badge/bunq%20SDK-1.28-00C1A1?style=flat-square)](https://github.com/bunq/sdk_php)
[![License](https://img.shields.io/packagist/l/siberfx/laravel-bunq.svg?style=flat-square&v=2)](LICENSE)

A **bunq payment gateway for Laravel 13+**. Request money from customers, pay out to bank accounts, and track every payment in your database. Status changes arrive through bunq webhooks and are published as Laravel events.

It is built on the official [`bunq/sdk_php`](https://github.com/bunq/sdk_php) and includes a **Cashier-style billable trait**, so you can use bunq alongside [Laravel Cashier](https://laravel.com/docs/billing) on the same user model.

## Features

- **Payment requests**: `bunqCharge()` asks a customer to pay you through bunq and stays `pending` until they accept.
- **Payouts**: `bunqPayout()` sends money to an email, phone number or IBAN (refunds, partner payouts).
- **Transaction ledger**: every request and payout is stored in `bunq_transactions`, polymorphic on your billable model.
- **Webhooks**: a token-protected endpoint updates statuses (`ACCEPTED`, `REJECTED`, `REVOKED`, `EXPIRED`) and fires `BunqTransactionUpdated`.
- **Safe money handling**: amounts are stored as integer minor units. No floats.
- **Session management**: the SDK session is restored from disk and refreshed automatically.
- **Testable**: the gateway is an interface. Bind a fake in your tests and nothing touches the network.
- **Sandbox first**: `php artisan bunq:install` creates a sandbox user and lists your accounts.

## Use cases

**Invoice payment for a SaaS plan.** A customer upgrades and is asked to pay by bunq. The plan activates only once bunq confirms the payment.

```php
$transaction = $user->bunqCharge(Money::fromDecimal('49.00'), 'Pro plan, November', reference: 'plan-pro-2026-11');

// Later, in your webhook listener:
if ($event->transaction->isPaid()) {
    $event->transaction->billable->activateSubscription();
}
```

**Refunding an order.** A refund goes to the buyer's IBAN. Your app records it in `bunq_transactions` next to the original charge.

```php
$seller->bunqPayout(
    Money::fromDecimal('18.90'),
    Counterparty::iban($buyer->iban, $buyer->name),
    'Refund for order 1042',
);
```

**Paying affiliates or partners by email.** Commissions go to anyone with a bunq account, without collecting their bank details first.

```php
$affiliate->bunqPayout(
    Money::fromDecimal('12.00'),
    Counterparty::email($affiliate->email),
    'Commission, October',
);
```

**Expiring unpaid requests.** A scheduled job cancels requests that the customer never accepted, so they stop appearing in the customer's bunq app.

```php
use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Models\BunqTransaction;

$gateway = app(PaymentGateway::class);

BunqTransaction::query()
    ->where('kind', 'request')
    ->where('status', 'pending')
    ->where('created_at', '<', now()->subDays(7))
    ->each(fn ($transaction) => $gateway->cancelRequest($transaction->remote_id));
```

**Hybrid billing with Cashier.** Subscriptions stay on Stripe through Cashier, while one-off add-ons are collected by bunq. See [Cashier compatibility](#cashier-compatibility).

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.3, 8.4 or 8.5 |
| Laravel | 13.x |
| bunq SDK | `bunq/sdk_php` ^1.28 (installed automatically) |
| PHP extensions | `curl`, `json`, `mbstring` |

## Installation

```bash
composer require siberfx/laravel-bunq
```

The service provider is auto-discovered. Publish the config and migration, then run the migration:

```bash
php artisan vendor:publish --tag=bunq-config
php artisan vendor:publish --tag=bunq-migrations
php artisan migrate
```

## Configuration

Add your settings to `.env`:

```dotenv
BUNQ_ENVIRONMENT=SANDBOX            # or PRODUCTION
BUNQ_API_KEY=                       # only needed for bunq:install (required in PRODUCTION)
BUNQ_MONETARY_ACCOUNT_ID=           # set after running bunq:install
BUNQ_CURRENCY=EUR
BUNQ_CONTEXT_PATH=                  # optional, defaults to storage/app/bunq/bunq.conf

BUNQ_WEBHOOK_ENABLED=true
BUNQ_WEBHOOK_PATH=bunq/webhook
BUNQ_WEBHOOK_TOKEN=                 # generate with: openssl rand -hex 32
```

The full config is in [`config/bunq.php`](config/bunq.php).

### 1. Install the bunq context

```bash
php artisan bunq:install
```

- **SANDBOX**: creates a sandbox user automatically. No API key needed.
- **PRODUCTION**: `php artisan bunq:install --environment=PRODUCTION --api-key=YOUR_KEY`

The command saves the SDK context to `BUNQ_CONTEXT_PATH` and prints your monetary accounts, so you can copy the ID you want into `BUNQ_MONETARY_ACCOUNT_ID`.

> **Keep the context file private.** It contains your bunq session keys. Do not commit it.

## Usage

### Billing a customer

Add the trait to any model that represents a payer. The model needs an `email` attribute, or you can override `bunqCounterparty()`.

```php
use Siberfx\LaravelBunq\Concerns\HasBunqPayments;

class User extends Authenticatable
{
    use HasBunqPayments;
}
```

Request money:

```php
use Siberfx\LaravelBunq\Support\Money;

$transaction = $user->bunqCharge(
    Money::fromDecimal('25.50'),     // 25.50 EUR
    'Pro plan, October',             // shown to the payer in bunq
    reference: 'invoice-1042',       // optional merchant reference
);

$transaction->status; // "pending" until the payer accepts
```

Read the history:

```php
$user->bunqTransactions; // latest first, polymorphic
```

### Paying out

```php
use Siberfx\LaravelBunq\Support\Counterparty;

$user->bunqPayout(
    Money::fromDecimal('10.00'),
    Counterparty::iban('NL91ABNA0417164300', 'Jane Doe'),   // IBAN aliases need a name
    'Refund for order 1042',
);
```

Counterparties can be an email, a phone number or an IBAN:

```php
Counterparty::email('jane@example.com');
Counterparty::phone('+31 42 112 3456');      // spaces are stripped
Counterparty::iban('NL91 ABNA 0417 1643 00', 'Jane Doe');
```

### Using the gateway directly

Everything is also available through the `PaymentGateway` contract, for code that is not tied to a model:

```php
use Siberfx\LaravelBunq\Contracts\PaymentGateway;

$gateway = app(PaymentGateway::class);

$requestId = $gateway->requestPayment(Money::fromDecimal('5'), Counterparty::email('jane@example.com'), 'Coffee');
$status    = $gateway->requestStatus($requestId);   // PENDING, ACCEPTED, REJECTED, REVOKED
$gateway->cancelRequest($requestId);
```

### Webhooks

bunq calls your app when a request changes state. Register the URL in bunq with:

```php
route('bunq.webhook', ['token' => config('bunq.webhook.token')]);
// https://your-app.test/bunq/webhook/{token}
```

The endpoint returns `403` when the token does not match, and it is disabled entirely when no token is configured. bunq does not sign its callbacks, so **keep the token secret and use HTTPS**.

Listen for status changes:

```php
use Siberfx\LaravelBunq\Events\BunqTransactionUpdated;

Event::listen(function (BunqTransactionUpdated $event) {
    if ($event->transaction->isPaid()) {
        $event->transaction->billable->activateSubscription();
    }
});
```

Or use a listener class and register it in your `EventServiceProvider`/`AppServiceProvider`.

#### Webhook middleware and CSRF

The webhook route uses the `api` middleware group by default, so it is not subject to CSRF checks. If you change `bunq.webhook.middleware`, make sure the route is excluded from request forgery protection.

### Swapping the gateway

The gateway is bound with `bindIf`, so your own implementation wins:

```php
$this->app->bind(PaymentGateway::class, MyGateway::class);
```

## Cashier compatibility

[Laravel Cashier](https://laravel.com/docs/billing) is built for Stripe and Paddle. bunq is not a Cashier driver, so this package does not plug into Cashier's internals. Instead it works **next to** Cashier:

- The `HasBunqPayments` trait uses `bunq`-prefixed method names (`bunqCharge()`, `bunqPayout()`, `bunqTransactions()`), so it never clashes with Cashier's `charge()`, `subscription()` or `invoices()`.
- A model can use Cashier's `Billable` trait and `HasBunqPayments` together. Stripe handles subscriptions while bunq collects one-off or SEPA payments.

```php
use Laravel\Cashier\Billable;
use Siberfx\LaravelBunq\Concerns\HasBunqPayments;

class User extends Authenticatable
{
    use Billable, HasBunqPayments;
}

$user->newSubscription('default', 'price_monthly')->create($paymentMethod); // Stripe
$user->bunqCharge(Money::fromDecimal('99.00'), 'Annual upgrade');          // bunq
```

Cashier is an optional dependency. Install it only if you need it.

## Testing

Your tests should not call bunq. Bind a fake gateway:

```php
use Siberfx\LaravelBunq\Contracts\PaymentGateway;

$this->app->instance(PaymentGateway::class, new class implements PaymentGateway {
    // record calls and return fake ids
});
```

To run this package's own suite:

```bash
composer install
composer test
```

The suite covers money handling, the billable trait, webhook state changes, token checks and event dispatch. It runs on SQLite in memory and makes no network calls.

## Data model

| Column | Type | Notes |
| --- | --- | --- |
| `billable_type`, `billable_id` | morph | Your payer model |
| `kind` | string | `request` or `payment` |
| `remote_id` | bigint | bunq request-inquiry or payment id; unique per `kind` |
| `amount_minor` | bigint | Amount in minor units (cents) |
| `currency` | char(3) | ISO 4217 |
| `status` | string | `pending`, `paid`, `failed` or `canceled` |
| `description`, `reference` | string | `reference` is your merchant reference |
| `metadata` | json | Free for your own use |
| `paid_at` | timestamp | Set when the status becomes `paid` |

## Notes and limitations

- **Recurring billing is not included.** bunq scheduled payments are not wrapped yet. Use Cashier with Stripe for subscriptions.
- **bunq rate limits and session expiry** are handled by the SDK. Heavy workloads should go through a queue.
- **Webhook payloads** are parsed from the `NotificationUrl.object` shape documented by bunq. Check your sandbox traffic once after setup.
- **PHP 8.4+ deprecation notices.** The bunq SDK (1.28) still declares implicitly nullable parameters, which PHP 8.4 and later report as deprecations. They are harmless but can clutter logs. Silence them with `error_reporting(E_ALL & ~E_DEPRECATED)` in environments where that is acceptable.
- This package is not affiliated with or endorsed by bunq B.V.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Pull requests are welcome. Please add tests for behaviour changes and run `composer test` before opening one.

## Security

If you find a security issue, please email **info@siberfx.com** instead of opening a public issue.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
