<?php

use Illuminate\Support\Facades\Route;
use Siberfx\LaravelBunq\Http\Controllers\WebhookController;

Route::middleware(config('bunq.webhook.middleware', ['api']))
    ->post(config('bunq.webhook.path', 'bunq/webhook').'/{token}', WebhookController::class)
    ->name('bunq.webhook');
