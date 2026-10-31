<?php

namespace Siberfx\LaravelBunq\Support;

use InvalidArgumentException;

/**
 * Who money is requested from or sent to. Maps to a bunq alias (pointer).
 */
final class Counterparty
{
    public const EMAIL = 'EMAIL';

    public const PHONE_NUMBER = 'PHONE_NUMBER';

    public const IBAN = 'IBAN';

    private function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly ?string $name = null,
    ) {}

    public static function email(string $email): self
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid bunq email alias [{$email}].");
        }

        return new self(self::EMAIL, $email);
    }

    /**
     * E.123 format without spaces, e.g. +31421123456.
     */
    public static function phone(string $phone): self
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            throw new InvalidArgumentException("Invalid bunq phone alias [{$phone}]. Use the +countrycode format.");
        }

        return new self(self::PHONE_NUMBER, $phone);
    }

    /**
     * bunq requires the account holder name for IBAN aliases.
     */
    public static function iban(string $iban, string $name): self
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            throw new InvalidArgumentException("Invalid IBAN [{$iban}].");
        }

        return new self(self::IBAN, $iban, $name);
    }
}
