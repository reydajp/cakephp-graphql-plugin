<?php
declare(strict_types=1);

namespace CakeGraphQL;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\ServiceProvider;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqlEngineInterface;
use CakeGraphQL\Engine\GraphqlEngineRegistry;
use CakeGraphQL\Engine\GraphqliteEngine;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;
use CakeGraphQL\Security\CakeAuthenticationService;

final class GraphqlServiceProvider extends ServiceProvider
{
    protected array $provides = [
        GraphqlConfig::class,
        GraphqlEngineContext::class,
        GraphqlEngineRegistry::class,
        GraphqlEndpointMiddleware::class,
        CakeAuthenticationService::class,
    ];

    /**
     * @param array<string, \CakeGraphQL\Engine\GraphqlEngineInterface> $engines
     */
    public function __construct(private readonly array $engines = [])
    {
    }

    public function services(ContainerInterface $container): void
    {
        $container->addShared(GraphqlConfig::class, function (): GraphqlConfig {
            $config = Configure::read('Graphql');
            if (!is_array($config)) {
                $config = [];
            }

            return GraphqlConfig::fromArray($config);
        });

        $container->addShared(GraphqlEngineRegistry::class, function (): GraphqlEngineRegistry {
            /** @var array<string, \CakeGraphQL\Engine\GraphqlEngineInterface> $engines */
            $configuredEngines = [
                'Graphqlite' => new GraphqliteEngine(),
                ...$this->engines,
            ];
            $engines = array_filter(
                $configuredEngines,
                static fn(GraphqlEngineInterface $engine): bool => true,
            );

            return new GraphqlEngineRegistry($engines);
        });

        $container->addShared(GraphqlEngineContext::class, function () use ($container): GraphqlEngineContext {
            return new GraphqlEngineContext($container, $container->get(GraphqlConfig::class));
        });

        $container->addShared(CakeAuthenticationService::class);

        $container->addShared(GraphqlEndpointMiddleware::class, function () use ($container): GraphqlEndpointMiddleware {
            return new GraphqlEndpointMiddleware(
                $container->get(GraphqlConfig::class),
                $container->get(GraphqlEngineContext::class),
                $container->get(GraphqlEngineRegistry::class),
                $container->get(CakeAuthenticationService::class),
            );
        });
    }
}
