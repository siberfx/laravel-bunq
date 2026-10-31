# Changelog

All notable changes to this project are documented here. This project follows [Semantic Versioning](https://semver.org).

## [Unreleased]

## [1.0.1] - 2026-10-31

### Added
- Use cases section in the README: SaaS invoice payment, order refunds, affiliate payouts, expiring unpaid requests and hybrid billing with Cashier.

### Fixed
- PHP version badge in the README now uses the Packagist dependency badge endpoint, since the old `php-v` endpoint no longer resolves.

## [1.0.0] - 2026-10-31

### Added
- bunq gateway on top of `bunq/sdk_php` ^1.28: payment requests, payouts, request status and cancellation.
- `HasBunqPayments` trait with `bunqCharge()`, `bunqPayout()` and `bunqTransactions()`, designed to sit next to Cashier's `Billable`.
- `bunq_transactions` table, `BunqTransaction` model and `Money` / `Counterparty` value objects.
- Webhook endpoint protected by a secret token, with `BunqTransactionUpdated` events.
- `bunq:install` command that creates the SDK context and lists monetary accounts.
- Laravel 13 support, PHP 8.3+.
