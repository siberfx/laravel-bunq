<?php

namespace Siberfx\LaravelBunq\Console;

use Illuminate\Console\Command;
use Siberfx\LaravelBunq\BunqManager;
use Siberfx\LaravelBunq\Exceptions\BunqApiException;

class InstallCommand extends Command
{
    protected $signature = 'bunq:install
        {--environment= : SANDBOX or PRODUCTION (defaults to BUNQ_ENVIRONMENT)}
        {--api-key= : bunq API key (required for PRODUCTION)}';

    protected $description = 'Create the bunq API context and list your monetary accounts';

    public function handle(BunqManager $bunq): int
    {
        $environment = strtoupper((string) ($this->option('environment') ?: config('bunq.environment', 'SANDBOX')));
        $apiKey = $this->option('api-key') ?: config('bunq.api_key');

        try {
            $bunq->install($environment, $apiKey ?: null);
            $accounts = $bunq->monetaryAccounts();
        } catch (BunqApiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("bunq {$environment} context saved to {$bunq->contextPath()}");

        if ($accounts === []) {
            $this->warn('No monetary accounts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Description', 'Currency', 'Status'],
            array_map(fn (array $account) => [
                $account['id'],
                $account['description'],
                $account['currency'],
                $account['status'],
            ], $accounts),
        );

        $this->line('Set BUNQ_MONETARY_ACCOUNT_ID to the account you want to use.');

        return self::SUCCESS;
    }
}
