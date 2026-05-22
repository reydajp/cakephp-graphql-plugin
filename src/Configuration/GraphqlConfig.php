<?php
declare(strict_types=1);

namespace CakeGraphQL\Configuration;

use CakeGraphQL\Exception\GraphqlConfigurationException;

final readonly class GraphqlConfig
{
    /**
     * @param array<string, mixed> $engines
     */
    private function __construct(
        private readonly string $path,
        private readonly string $engine,
        private readonly bool $authenticated,
        private readonly array $engines,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $path = $config['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new GraphqlConfigurationException('Graphql.path must be a non-empty string.');
        }

        if (!str_starts_with($path, '/')) {
            throw new GraphqlConfigurationException('Graphql.path must start with "/".');
        }

        $engine = $config['engine'] ?? null;
        if (!is_string($engine) || $engine === '') {
            throw new GraphqlConfigurationException('Graphql.engine must be a non-empty string.');
        }

        $engines = $config['engines'] ?? null;
        if (!is_array($engines)) {
            throw new GraphqlConfigurationException('Graphql.engines must be an array.');
        }

        if (!array_key_exists($engine, $engines) || !is_array($engines[$engine])) {
            throw new GraphqlConfigurationException(
                sprintf('Graphql.engines.%s must be configured for the selected engine.', $engine),
            );
        }

        $authenticated = $config['authenticated'] ?? true;
        if (!is_bool($authenticated)) {
            throw new GraphqlConfigurationException('Graphql.authenticated must be a boolean.');
        }

        return new self($path, $engine, $authenticated, $engines);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function engine(): string
    {
        return $this->engine;
    }

    public function authenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * @return array<string, mixed>
     */
    public function selectedEngineConfig(): array
    {
        return $this->engines[$this->engine];
    }

    /**
     * @return array<string, mixed>
     */
    public function engines(): array
    {
        return $this->engines;
    }
}
