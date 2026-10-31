<?php

namespace Siberfx\LaravelBunq\Gateway;

use bunq\Exception\BunqException;
use bunq\Model\Generated\Endpoint\PaymentApiObject;
use bunq\Model\Generated\Endpoint\RequestInquiryApiObject;
use bunq\Model\Generated\Object\AmountObject;
use bunq\Model\Generated\Object\PointerObject;
use Siberfx\LaravelBunq\BunqManager;
use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Exceptions\BunqApiException;
use Siberfx\LaravelBunq\Support\Counterparty;
use Siberfx\LaravelBunq\Support\Money;

final class BunqGateway implements PaymentGateway
{
    public function __construct(private readonly BunqManager $manager) {}

    public function requestPayment(Money $money, Counterparty $from, string $description, ?string $reference = null): int
    {
        return $this->call(fn () => RequestInquiryApiObject::create(
            amountInquired: $this->amount($money),
            counterpartyAlias: $this->pointer($from),
            description: $description,
            allowBunqme: false,
            monetaryAccountId: $this->accountId(),
            merchantReference: $reference,
        )->getValue());
    }

    public function sendPayment(Money $money, Counterparty $to, string $description, ?string $reference = null): int
    {
        return $this->call(fn () => PaymentApiObject::create(
            amount: $this->amount($money),
            counterpartyAlias: $this->pointer($to),
            description: $description,
            monetaryAccountId: $this->accountId(),
            merchantReference: $reference,
        )->getValue());
    }

    public function requestStatus(int $requestId): string
    {
        return $this->call(fn () => RequestInquiryApiObject::get(
            requestInquiryId: $requestId,
            monetaryAccountId: $this->accountId(),
        )->getValue()->getStatus());
    }

    public function cancelRequest(int $requestId): void
    {
        $this->call(fn () => RequestInquiryApiObject::update(
            requestInquiryId: $requestId,
            monetaryAccountId: $this->accountId(),
            status: 'REVOKED',
        ));
    }

    private function amount(Money $money): AmountObject
    {
        return new AmountObject($money->value(), $money->currency());
    }

    private function pointer(Counterparty $counterparty): PointerObject
    {
        return new PointerObject($counterparty->type, $counterparty->value, $counterparty->name);
    }

    private function accountId(): int
    {
        $accountId = config('bunq.monetary_account_id');

        if (! $accountId) {
            throw new BunqApiException('Set BUNQ_MONETARY_ACCOUNT_ID (run `php artisan bunq:install` to list your accounts).');
        }

        return (int) $accountId;
    }

    /**
     * Makes sure the SDK context is loaded and turns SDK errors into our own type.
     */
    private function call(callable $operation): mixed
    {
        $this->manager->ready();

        try {
            return $operation();
        } catch (BunqException $exception) {
            throw new BunqApiException($exception->getMessage(), 0, $exception);
        }
    }
}
