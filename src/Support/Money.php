<?php

namespace Siberfx\LaravelBunq\Support;

use InvalidArgumentException;

/**
 * An immutable amount in minor units (cents), so no float rounding happens.
 */
final class Money
{
    private function __construct(
        private readonly int $minor,
        private readonly string $currency,
    ) {}

    public static function fromMinor(int $minor, ?string $currency = null): self
    {
        if ($minor <= 0) {
            throw new InvalidArgumentException('Money amount must be greater than zero.');
        }

        return new self($minor, self::normalizeCurrency($currency));
    }

    /**
     * Accepts "10", "10.5" or "10.50". Pass strings, not floats, to avoid
     * binary floating point surprises.
     */
    public static function fromDecimal(string|int $amount, ?string $currency = null): self
    {
        $value = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("Invalid money amount [{$value}].");
        }

        $minor = ((int) $matches[1]) * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');

        return self::fromMinor($minor, $currency);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * The decimal string bunq expects, e.g. "10.50".
     */
    public function value(): string
    {
        return sprintf('%d.%02d', intdiv($this->minor, 100), $this->minor % 100);
    }

    public function __toString(): string
    {
        return $this->value().' '.$this->currency;
    }

    private static function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper($currency ?? config('bunq.currency', 'EUR'));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}].");
        }

        return $currency;
    }
}
