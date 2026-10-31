<?php

namespace Siberfx\LaravelBunq\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Siberfx\LaravelBunq\Webhooks\WebhookHandler;

final class WebhookController
{
    public function __invoke(Request $request, WebhookHandler $handler, string $token): Response
    {
        $expected = (string) config('bunq.webhook.token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 403);

        $handler->handle($request->json()->all());

        return response()->noContent();
    }
}
