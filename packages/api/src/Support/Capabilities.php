<?php

namespace KusikusiCMS\Api\Support;

/**
 * Registry of the packages that contribute to the API (bound as a singleton).
 * Registering the same name twice replaces the earlier entry.
 */
final class Capabilities
{
    /** @var array<string, Capability> */
    private array $capabilities = [];

    public function register(Capability $capability): void
    {
        $this->capabilities[$capability->name] = $capability;
    }

    public function has(string $name): bool
    {
        return isset($this->capabilities[$name]);
    }

    /**
     * @return array<int, Capability> In registration order.
     */
    public function all(): array
    {
        return array_values($this->capabilities);
    }
}
