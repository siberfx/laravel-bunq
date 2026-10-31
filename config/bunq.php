<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | SANDBOX or PRODUCTION. In SANDBOX, `php artisan bunq:install` creates a
    | sandbox user for you, so no API key is needed.
    |
    */

    'environment' => env('BUNQ_ENVIRONMENT', 'SANDBOX'),

    /*
    | Your bunq API key. Only needed during `bunq:install` (required for
    | PRODUCTION). It is not used after the context file has been created.
    */

    'api_key' => env('BUNQ_API_KEY'),

    /*
    | Where the SDK stores its installation and session context. Keep this
    | file out of version control and readable only by your app user.
    */

    'context_path' => env('BUNQ_CONTEXT_PATH', storage_path('app/bunq/bunq.conf')),

    /*
    | The bunq monetary account (ID) that payment requests are created on and
    | payouts are sent from. Find it with `php artisan bunq:install`.
    */

    'monetary_account_id' => env('BUNQ_MONETARY_ACCOUNT_ID'),

    /*
    | Default currency for amounts created with Money::fromDecimal().
    */

    'currency' => env('BUNQ_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap these for your own classes if you need to extend the defaults.
    |
    */

    'models' => [
        'transaction' => \Siberfx\LaravelBunq\Models\BunqTransaction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | bunq calls this URL when a payment request is accepted or rejected.
    | Register the URL with bunq as:
    |
    |     route('bunq.webhook', ['token' => config('bunq.webhook.token')])
    |
    | bunq does not sign its callbacks, so the random token in the URL is what
    | authenticates them. Generate one with `openssl rand -hex 32`.
    |
    */

    'webhook' => [
        'enabled' => env('BUNQ_WEBHOOK_ENABLED', true),
        'path' => env('BUNQ_WEBHOOK_PATH', 'bunq/webhook'),
        'token' => env('BUNQ_WEBHOOK_TOKEN'),
        'middleware' => ['api'],
    ],

];
