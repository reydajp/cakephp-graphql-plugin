<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Routing;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\RouteBuilder;
use Cake\Routing\RouteCollection;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Exception\GraphqlConfigurationException;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;
use CakeGraphQL\Plugin;
use CakeGraphQL\Routing\GraphqlRouteLoader;
use PHPUnit\Framework\TestCase;

final class GraphqlRouteLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('Graphql');
    }

    public function testRegistersConfiguredEndpointRoute(): void
    {
        $collection = new RouteCollection();
        $routes = new RouteBuilder($collection, '/');
        $config = GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);

        (new GraphqlRouteLoader())->load($routes, $config);

        $params = $collection->parseRequest(new ServerRequest([
            'url' => '/api/graphql',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]));

        $this->assertSame('CakeGraphQL', $params['plugin']);
        $this->assertSame('Graphql', $params['controller']);
        $this->assertSame('execute', $params['action']);
        $this->assertSame([GraphqlEndpointMiddleware::class], $collection->getMiddleware($params['_middleware']));
    }

    public function testDoesNotRegisterUnconfiguredEndpointRoute(): void
    {
        $collection = new RouteCollection();
        $routes = new RouteBuilder($collection, '/');
        $config = GraphqlConfig::fromArray([
            'path' => '/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);

        (new GraphqlRouteLoader())->load($routes, $config);

        $this->expectException(MissingRouteException::class);

        $collection->parseRequest(new ServerRequest([
            'url' => '/api/graphql',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]));
    }

    public function testPluginLoadsRouteFromCakeConfiguration(): void
    {
        Configure::write('Graphql', [
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
        $collection = new RouteCollection();
        $routes = new RouteBuilder($collection, '/');

        (new Plugin())->routes($routes);

        $params = $collection->parseRequest(new ServerRequest([
            'url' => '/api/graphql',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]));

        $this->assertSame('CakeGraphQL', $params['plugin']);
        $this->assertSame('Graphql', $params['controller']);
        $this->assertSame('execute', $params['action']);
    }

    public function testPluginRejectsMissingPathThroughConfigurationValidation(): void
    {
        Configure::write('Graphql', [
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);

        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.path must be a non-empty string.');

        (new Plugin())->routes(new RouteBuilder(new RouteCollection(), '/'));
    }
}
