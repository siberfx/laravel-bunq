<?php

namespace Siberfx\LaravelBunq\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Siberfx\LaravelBunq\Support\Money;

/**
 * A payment request (kind "request") or a payout (kind "payment") tied to a billable model.
 *
 * @property int $id
 * @property string $billable_type
 * @property int $billable_id
 * @property string $kind
 * @property int $remote_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string $description
 * @property string|null $reference
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class BunqTransaction extends Model
{
    public const KIND_REQUEST = 'request';

    public const KIND_PAYMENT = 'payment';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const CANCELED = 'canceled';

    protected $table = 'bunq_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'remote_id' => 'integer',
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function money(): Money
    {
        return Money::fromMinor($this->amount_minor, $this->currency);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
