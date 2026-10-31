<?php

namespace Siberfx\LaravelBunq\Tests\Feature;

use LogicException;
use Siberfx\LaravelBunq\Models\BunqTransaction;
use Siberfx\LaravelBunq\Support\Counterparty;
use Siberfx\LaravelBunq\Support\Money;
use Siberfx\LaravelBunq\Tests\Fixtures\Customer;
use Siberfx\LaravelBunq\Tests\TestCase;

class BillableTest extends TestCase
{
    public function test_charge_creates_a_pending_request_for_the_customer(): void
    {
        $customer = Customer::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $transaction = $customer->bunqCharge(Money::fromDecimal('25.50'), 'Pro plan', 'order-1');

        $this->assertSame(BunqTransaction::KIND_REQUEST, $transaction->kind);
        $this->assertSame(BunqTransaction::PENDING, $transaction->status);
        $this->assertSame(2550, $transaction->amount_minor);
        $this->assertSame('EUR', $transaction->currency);
        $this->assertSame('order-1', $transaction->reference);
        $this->assertSame($customer->getKey(), $transaction->billable_id);
        $this->assertSame(Customer::class, $transaction->billable_type);

        $sent = $this->gateway()->requests[0];
        $this->assertSame('25.50', $sent['money']->value());
        $this->assertSame('EMAIL', $sent['counterparty']->type);
        $this->assertSame('jane@example.com', $sent['counterparty']->value);
    }

    public function test_payout_is_recorded_as_paid(): void
    {
        $customer = Customer::create(['name' => 'Jane']);

        $transaction = $customer->bunqPayout(
            Money::fromDecimal('10'),
            Counterparty::iban('NL91ABNA0417164300', 'Jane Doe'),
            'Refund',
        );

        $this->assertSame(BunqTransaction::KIND_PAYMENT, $transaction->kind);
        $this->assertTrue($transaction->isPaid());
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('IBAN', $this->gateway()->payments[0]['counterparty']->type);
    }

    public function test_charge_needs_a_counterparty(): void
    {
        $customer = Customer::create(['name' => 'No email']);

        $this->expectException(LogicException::class);

        $customer->bunqCharge(Money::fromDecimal('5'), 'Nothing to bill');
    }

    public function test_counterparty_can_be_overridden(): void
    {
        $customer = new class extends Customer
        {
            public function bunqCounterparty(): ?Counterparty
            {
                return Counterparty::phone('+31 42 112 3456');
            }
        };
        $customer->forceFill(['name' => 'Phone only'])->save();

        $customer->bunqCharge(Money::fromDecimal('5'), 'Phone billing');

        $this->assertSame('PHONE_NUMBER', $this->gateway()->requests[0]['counterparty']->type);
        $this->assertSame('+31421123456', $this->gateway()->requests[0]['counterparty']->value);
    }
}
