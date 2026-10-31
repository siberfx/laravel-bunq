<?php

namespace Siberfx\LaravelBunq\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;
use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Models\BunqTransaction;
use Siberfx\LaravelBunq\Support\Counterparty;
use Siberfx\LaravelBunq\Support\Money;

/**
 * Cashier-style billing for bunq. Add it next to Cashier's Billable trait
 * when you need both. Method names are prefixed with "bunq" so they never
 * clash with Cashier's charge() and friends.
 *
 *   $user->bunqCharge(Money::fromDecimal('25.00'), 'Pro plan');   // asks the user to pay
 *   $user->bunqPayout(Money::fromDecimal('10.00'), Counterparty::iban(...), 'Refund');
 */
trait HasBunqPayments
{
    public function bunqTransactions(): MorphMany
    {
        return $this->morphMany(BunqTransaction::class, 'billable')->latest();
    }

    /**
     * Where bunq should send requests for this model. Override it to use a
     * phone number or IBAN instead of the email attribute.
     */
    public function bunqCounterparty(): ?Counterparty
    {
        $email = $this->getAttribute('email');

        return filled($email) ? Counterparty::email($email) : null;
    }

    /**
     * Request money from this model. The transaction stays pending until the
     * bunq webhook reports that the request was accepted.
     */
    public function bunqCharge(Money $money, string $description, ?string $reference = null): BunqTransaction
    {
        $counterparty = $this->bunqCounterparty()
            ?? throw new LogicException('bunqCharge() needs a counterparty. Set an email attribute or override bunqCounterparty().');

        $requestId = app(PaymentGateway::class)->requestPayment($money, $counterparty, $description, $reference);

        return $this->bunqTransactions()->create([
            'kind' => BunqTransaction::KIND_REQUEST,
            'remote_id' => $requestId,
            'amount_minor' => $money->minor(),
            'currency' => $money->currency(),
            'status' => BunqTransaction::PENDING,
            'description' => $description,
            'reference' => $reference,
        ]);
    }

    /**
     * Send money from your account to a counterparty (refunds, payouts). bunq
     * moves the money straight away, so the transaction is recorded as paid.
     */
    public function bunqPayout(Money $money, Counterparty $to, string $description, ?string $reference = null): BunqTransaction
    {
        $paymentId = app(PaymentGateway::class)->sendPayment($money, $to, $description, $reference);

        return $this->bunqTransactions()->create([
            'kind' => BunqTransaction::KIND_PAYMENT,
            'remote_id' => $paymentId,
            'amount_minor' => $money->minor(),
            'currency' => $money->currency(),
            'status' => BunqTransaction::PAID,
            'description' => $description,
            'reference' => $reference,
            'paid_at' => now(),
        ]);
    }
}
