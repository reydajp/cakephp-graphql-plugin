<?php
declare(strict_types=1);

namespace CakeGraphQL\Engine;

use CakeGraphQL\Exception\GraphqlConfigurationException;

final class GraphqlEngineRegistry
{
    /**
     * @param array<string, \CakeGraphQL\Engine\GraphqlEngineInterface> $engines
     */
    public function __construct(private readonly array $engines)
    {
    }

    public function get(string $name): GraphqlEngineInterface
    {
        if (!isset($this->engines[$name])) {
            throw new GraphqlConfigurationException(sprintf('GraphQL engine "%s" is not registered.', $name));
        }

        return $this->engines[$name];
    }

    public function getForContext(GraphqlEngineContext $context): GraphqlEngineInterface
    {
        return $this->get($context->engineName());
    }
}
