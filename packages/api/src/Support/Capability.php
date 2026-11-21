<?php

namespace KusikusiCMS\Api\Support;

/**
 * One package entry of the capabilities endpoint.
 */
final readonly class Capability
{
    /**
     * @param  array<int, string>  $features
     */
    public function __construct(
        public string $name,
        public string $version,
        public array $features = [],
    ) {}

    /**
     * @return array{name: string, version: string, features: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'features' => array_values($this->features),
        ];
    }
}
