<?php

namespace Siberfx\LaravelBunq;

use bunq\Context\ApiContext;
use bunq\Context\BunqContext;
use bunq\Exception\BunqException;
use bunq\Model\Generated\Endpoint\MonetaryAccountBankApiObject;
use bunq\Util\BunqEnumApiEnvironmentType;
use bunq\Util\InstallationUtil;
use Siberfx\LaravelBunq\Exceptions\BunqApiException;

/**
 * Owns the bunq SDK context: installs it, restores it from disk and keeps the
 * session alive. The SDK keeps its context in static state, so this class
 * makes sure it is loaded before any API call.
 */
final class BunqManager
{
    private ?ApiContext $context = null;

    public function __construct(private readonly string $contextPath) {}

    public function contextPath(): string
    {
        return $this->contextPath;
    }

    public function isInstalled(): bool
    {
        return is_file($this->contextPath);
    }

    public function install(string $environment, ?string $apiKey = null): void
    {
        $this->ensureDirectoryExists();

        try {
            InstallationUtil::automaticInstall(
                new BunqEnumApiEnvironmentType(strtoupper($environment)),
                $this->contextPath,
                $apiKey,
            );
        } catch (BunqException $exception) {
            throw new BunqApiException($exception->getMessage(), 0, $exception);
        }

        $this->context = null;
    }

    /**
     * Load the stored context and refresh the session when it has expired.
     */
    public function ready(): void
    {
        if (! $this->isInstalled()) {
            throw new BunqApiException(
                "bunq is not installed. Run `php artisan bunq:install` first (expected context at [{$this->contextPath}])."
            );
        }

        try {
            if ($this->context === null) {
                $this->context = ApiContext::restore($this->contextPath);
                BunqContext::loadApiContext($this->context);
            }

            if ($this->context->ensureSessionActive()) {
                $this->context->save($this->contextPath);
            }
        } catch (BunqException $exception) {
            throw new BunqApiException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<int, array{id: int, description: string, currency: string, status: string}>
     */
    public function monetaryAccounts(): array
    {
        $this->ready();

        try {
            $accounts = MonetaryAccountBankApiObject::listing()->getValue();
        } catch (BunqException $exception) {
            throw new BunqApiException($exception->getMessage(), 0, $exception);
        }

        return array_map(fn (MonetaryAccountBankApiObject $account) => [
            'id' => $account->getId(),
            'description' => $account->getDescription(),
            'currency' => $account->getCurrency(),
            'status' => $account->getStatus(),
        ], $accounts);
    }

    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->contextPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new BunqApiException("Could not create the bunq context directory [{$directory}].");
        }
    }
}
