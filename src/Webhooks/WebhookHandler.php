<?php

namespace Siberfx\LaravelBunq\Webhooks;

use Siberfx\LaravelBunq\Events\BunqTransactionUpdated;
use Siberfx\LaravelBunq\Models\BunqTransaction;

/**
 * Applies a bunq notification to the matching transaction.
 */
final class WebhookHandler
{
    /**
     * bunq request statuses we act on. Anything else (e.g. PENDING) is ignored.
     */
    private const REQUEST_STATUS_MAP = [
        'ACCEPTED' => BunqTransaction::PAID,
        'REJECTED' => BunqTransaction::FAILED,
        'EXPIRED' => BunqTransaction::FAILED,
        'REVOKED' => BunqTransaction::CANCELED,
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?BunqTransaction
    {
        $object = $payload['NotificationUrl']['object'] ?? $payload['object'] ?? [];

        if (isset($object['RequestInquiry'])) {
            $kind = BunqTransaction::KIND_REQUEST;
            $data = $object['RequestInquiry'];
            $status = self::REQUEST_STATUS_MAP[$data['status'] ?? ''] ?? null;
        } elseif (isset($object['Payment'])) {
            // bunq payments are final once they exist.
            $kind = BunqTransaction::KIND_PAYMENT;
            $data = $object['Payment'];
            $status = BunqTransaction::PAID;
        } else {
            return null;
        }

        if (! isset($data['id'], $status)) {
            return null;
        }

        $transaction = BunqTransaction::query()
            ->where('kind', $kind)
            ->where('remote_id', $data['id'])
            ->first();

        if ($transaction === null || $transaction->status === $status) {
            return $transaction;
        }

        $previousStatus = $transaction->status;

        $transaction->forceFill([
            'status' => $status,
            'paid_at' => $status === BunqTransaction::PAID ? now() : null,
        ])->save();

        BunqTransactionUpdated::dispatch($transaction, $previousStatus);

        return $transaction;
    }
}
