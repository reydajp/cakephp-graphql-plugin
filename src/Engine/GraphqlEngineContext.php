<?php
declare(strict_types=1);

namespace CakeGraphQL\Engine;

use Cake\Core\ContainerInterface;
use CakeGraphQL\Configuration\GraphqlConfig;

final class GraphqlEngineContext
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly GraphqlConfig $config,
    ) {
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function path(): string
    {
        return $this->config->path();
    }

    public function engineName(): string
    {
        return $this->config->engine();
    }

    /**
     * @return array<string, mixed>
     */
    public function engineConfig(): array
    {
        return $this->config->selectedEngineConfig();
    }

    public function config(): GraphqlConfig
    {
        return $this->config;
    }
}
