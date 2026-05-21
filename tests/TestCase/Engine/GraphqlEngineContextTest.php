<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Engine;

use Cake\Core\Container;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use PHPUnit\Framework\TestCase;

final class GraphqlEngineContextTest extends TestCase
{
    public function testContextExposesConfiguredEndpointAndSelectedEngineConfig(): void
    {
        $container = new Container();
        $config = GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => [
                    'queries' => ['App\\Graphql\\UsersQuery'],
                    'types' => ['App\\Model\\Entity\\User'],
                    'cache' => 'default',
                    'debug' => true,
                ],
            ],
        ]);

        $context = new GraphqlEngineContext($container, $config);

        $this->assertSame($container, $context->container());
        $this->assertSame('/api/graphql', $context->path());
        $this->assertSame('Graphqlite', $context->engineName());
        $this->assertSame(
            ['queries' => ['App\\Graphql\\UsersQuery'], 'types' => ['App\\Model\\Entity\\User'], 'cache' => 'default', 'debug' => true],
            $context->engineConfig(),
        );
    }
}
