<?php

namespace Siberfx\LaravelBunq\Contracts;

use Siberfx\LaravelBunq\Support\Counterparty;
use Siberfx\LaravelBunq\Support\Money;

/**
 * The payment operations the rest of the package depends on. The bunq SDK
 * implementation is BunqGateway; tests bind a fake instead.
 */
interface PaymentGateway
{
    /**
     * Ask a counterparty to pay you. Returns the bunq request-inquiry id.
     */
    public function requestPayment(Money $money, Counterparty $from, string $description, ?string $reference = null): int;

    /**
     * Send money from your account to a counterparty. Returns the bunq payment id.
     */
    public function sendPayment(Money $money, Counterparty $to, string $description, ?string $reference = null): int;

    /**
     * The current bunq status of a request, e.g. PENDING, ACCEPTED, REJECTED, REVOKED.
     */
    public function requestStatus(int $requestId): string;

    /**
     * Revoke an open payment request.
     */
    public function cancelRequest(int $requestId): void;
}
