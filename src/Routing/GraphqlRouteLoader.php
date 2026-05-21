<?php
declare(strict_types=1);

namespace CakeGraphQL\Routing;

use Cake\Routing\RouteBuilder;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;

final class GraphqlRouteLoader
{
    public function load(RouteBuilder $routes, GraphqlConfig $config): void
    {
        $routes->registerMiddleware('cakegraphql', GraphqlEndpointMiddleware::class);
        $routes->connect($config->path(), [
            'plugin' => 'CakeGraphQL',
            'controller' => 'Graphql',
            'action' => 'execute',
        ], [
            '_name' => 'graphql',
            '_middleware' => ['cakegraphql'],
        ]);
    }
}
