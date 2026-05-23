<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Container;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqlEngineInterface;
use CakeGraphQL\Engine\GraphqlEngineRegistry;
use CakeGraphQL\Engine\GraphqliteEngine;
use CakeGraphQL\GraphqlServiceProvider;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;
use CakeGraphQL\CakeGraphQLPlugin;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('Graphql');
    }

    public function testRegistersRuntimeServices(): void
    {
        Configure::write('Graphql', [
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
        $engine = $this->engine();
        $container = new Container();

        (new GraphqlServiceProvider(['Graphqlite' => $engine]))->services($container);

        $this->assertInstanceOf(GraphqlConfig::class, $container->get(GraphqlConfig::class));
        $this->assertInstanceOf(GraphqlEngineContext::class, $container->get(GraphqlEngineContext::class));
        $this->assertInstanceOf(GraphqlEngineRegistry::class, $container->get(GraphqlEngineRegistry::class));
        $this->assertInstanceOf(GraphqlEndpointMiddleware::class, $container->get(GraphqlEndpointMiddleware::class));
    }

    public function testPluginRegistersRuntimeServices(): void
    {
        Configure::write('Graphql', [
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
        $container = new Container();

        (new CakeGraphQLPlugin())->services($container);

        $this->assertInstanceOf(GraphqlConfig::class, $container->get(GraphqlConfig::class));
        $this->assertInstanceOf(GraphqlEndpointMiddleware::class, $container->get(GraphqlEndpointMiddleware::class));
    }

    public function testDefaultServiceProviderRegistersGraphqliteEngine(): void
    {
        Configure::write('Graphql', [
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
        $container = new Container();

        (new GraphqlServiceProvider())->services($container);

        $registry = $container->get(GraphqlEngineRegistry::class);

        $this->assertInstanceOf(GraphqliteEngine::class, $registry->get('Graphqlite'));
    }

    private function engine(): GraphqlEngineInterface
    {
        return new class implements GraphqlEngineInterface {
            public function createMiddleware(GraphqlEngineContext $context): MiddlewareInterface
            {
                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return new Response(status: 200);
                    }
                };
            }
        };
    }
}
