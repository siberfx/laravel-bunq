<?php

namespace Siberfx\LaravelBunq\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Siberfx\LaravelBunq\Support\Money;
use Siberfx\LaravelBunq\Tests\TestCase;

class MoneyTest extends TestCase
{
    public static function decimals(): array
    {
        return [
            'whole number' => ['10', 1000, '10.00'],
            'one decimal' => ['10.5', 1050, '10.50'],
            'two decimals' => ['10.05', 1005, '10.05'],
            'small amount' => ['0.01', 1, '0.01'],
            'integer input' => [7, 700, '7.00'],
        ];
    }

    #[DataProvider('decimals')]
    public function test_from_decimal_converts_to_minor_units(string|int $input, int $minor, string $value): void
    {
        $money = Money::fromDecimal($input, 'EUR');

        $this->assertSame($minor, $money->minor());
        $this->assertSame($value, $money->value());
        $this->assertSame('EUR', $money->currency());
    }

    public function test_it_defaults_to_the_configured_currency(): void
    {
        $this->assertSame('EUR', Money::fromDecimal('1')->currency());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('12,50');
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinor(0);
    }

    public function test_invalid_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('1', 'EURO');
    }
}
