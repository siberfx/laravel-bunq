<?php

namespace Siberfx\LaravelBunq\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Siberfx\LaravelBunq\Models\BunqTransaction;

/**
 * Dispatched when a bunq webhook changes a transaction's status.
 */
class BunqTransactionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BunqTransaction $transaction,
        public readonly string $previousStatus,
    ) {}
}
