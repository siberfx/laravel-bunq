<?php

namespace Siberfx\LaravelBunq\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Siberfx\LaravelBunq\Events\BunqTransactionUpdated;
use Siberfx\LaravelBunq\Models\BunqTransaction;
use Siberfx\LaravelBunq\Tests\Fixtures\Customer;
use Siberfx\LaravelBunq\Tests\TestCase;
use Siberfx\LaravelBunq\Support\Money;

class WebhookTest extends TestCase
{
    private function pendingRequest(int $remoteId): BunqTransaction
    {
        return Customer::create(['name' => 'Jane', 'email' => 'jane@example.com'])
            ->bunqTransactions()
            ->create([
                'kind' => BunqTransaction::KIND_REQUEST,
                'remote_id' => $remoteId,
                'amount_minor' => 1000,
                'currency' => 'EUR',
                'status' => BunqTransaction::PENDING,
                'description' => 'Test',
            ]);
    }

    private function notification(string $requestStatus, int $id): array
    {
        return [
            'NotificationUrl' => [
                'category' => 'REQUEST',
                'object' => [
                    'RequestInquiry' => ['id' => $id, 'status' => $requestStatus],
                ],
            ],
        ];
    }

    public function test_accepted_request_marks_the_transaction_paid_and_dispatches_an_event(): void
    {
        Event::fake([BunqTransactionUpdated::class]);
        $transaction = $this->pendingRequest(1001);

        $this->postJson('/bunq/webhook/test-token', $this->notification('ACCEPTED', 1001))
            ->assertNoContent();

        $transaction->refresh();
        $this->assertSame(BunqTransaction::PAID, $transaction->status);
        $this->assertNotNull($transaction->paid_at);

        Event::assertDispatched(
            BunqTransactionUpdated::class,
            fn (BunqTransactionUpdated $event) => $event->previousStatus === BunqTransaction::PENDING
                && $event->transaction->is($transaction),
        );
    }

    public function test_rejected_and_revoked_requests_update_the_status(): void
    {
        $rejected = $this->pendingRequest(2001);
        $revoked = $this->pendingRequest(2002);

        $this->postJson('/bunq/webhook/test-token', $this->notification('REJECTED', 2001))->assertNoContent();
        $this->postJson('/bunq/webhook/test-token', $this->notification('REVOKED', 2002))->assertNoContent();

        $this->assertSame(BunqTransaction::FAILED, $rejected->fresh()->status);
        $this->assertSame(BunqTransaction::CANCELED, $revoked->fresh()->status);
    }

    public function test_pending_or_unknown_notifications_change_nothing(): void
    {
        Event::fake([BunqTransactionUpdated::class]);
        $transaction = $this->pendingRequest(3001);

        $this->postJson('/bunq/webhook/test-token', $this->notification('PENDING', 3001))->assertNoContent();
        $this->postJson('/bunq/webhook/test-token', $this->notification('ACCEPTED', 9999))->assertNoContent();

        $this->assertSame(BunqTransaction::PENDING, $transaction->fresh()->status);
        Event::assertNotDispatched(BunqTransactionUpdated::class);
    }

    public function test_wrong_token_is_forbidden(): void
    {
        $transaction = $this->pendingRequest(4001);

        $this->postJson('/bunq/webhook/wrong-token', $this->notification('ACCEPTED', 4001))
            ->assertForbidden();

        $this->assertSame(BunqTransaction::PENDING, $transaction->fresh()->status);
    }

    public function test_webhook_is_forbidden_when_no_token_is_configured(): void
    {
        config(['bunq.webhook.token' => null]);

        $this->postJson('/bunq/webhook/', $this->notification('ACCEPTED', 1))
            ->assertNotFound();

        $this->postJson('/bunq/webhook/anything', $this->notification('ACCEPTED', 1))
            ->assertForbidden();
    }
}
