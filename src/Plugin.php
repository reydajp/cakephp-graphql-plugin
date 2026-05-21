<?php
declare(strict_types=1);

namespace CakeGraphQL;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use CakeGraphQL\Command\BakeGraphqlQueryCommand;
use Cake\Routing\RouteBuilder;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Routing\GraphqlRouteLoader;

final class Plugin extends BasePlugin
{
    public function routes(RouteBuilder $routes): void
    {
        $config = Configure::read('Graphql');
        if (!is_array($config)) {
            $config = [];
        }

        (new GraphqlRouteLoader())->load($routes, GraphqlConfig::fromArray($config));
    }

    public function services(ContainerInterface $container): void
    {
        (new GraphqlServiceProvider())->services($container);
    }

    public function console(CommandCollection $commands): CommandCollection
    {
        return $commands->add('bake graphql query', BakeGraphqlQueryCommand::class);
    }
}
