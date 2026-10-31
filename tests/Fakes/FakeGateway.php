<?php

namespace Siberfx\LaravelBunq\Tests\Fakes;

use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Support\Counterparty;
use Siberfx\LaravelBunq\Support\Money;

/**
 * Records every call and hands out predictable bunq ids.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var array<int, array{money: Money, counterparty: Counterparty, description: string, reference: ?string}> */
    public array $requests = [];

    /** @var array<int, array{money: Money, counterparty: Counterparty, description: string, reference: ?string}> */
    public array $payments = [];

    /** @var array<int, string> */
    public array $statuses = [];

    public array $cancelled = [];

    private int $nextId = 1000;

    public function requestPayment(Money $money, Counterparty $from, string $description, ?string $reference = null): int
    {
        $this->requests[] = compact('money', 'description', 'reference') + ['counterparty' => $from];

        return $this->nextId++;
    }

    public function sendPayment(Money $money, Counterparty $to, string $description, ?string $reference = null): int
    {
        $this->payments[] = compact('money', 'description', 'reference') + ['counterparty' => $to];

        return $this->nextId++;
    }

    public function requestStatus(int $requestId): string
    {
        return $this->statuses[$requestId] ?? 'PENDING';
    }

    public function cancelRequest(int $requestId): void
    {
        $this->cancelled[] = $requestId;
    }
}
