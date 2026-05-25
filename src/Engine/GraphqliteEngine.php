<?php
declare(strict_types=1);

namespace CakeGraphQL\Engine;

use Cake\Cache\Cache;
use CakeGraphQL\Exception\GraphqlConfigurationException;
use CakeGraphQL\Security\CakeAuthenticationService;
use GraphQL\Error\DebugFlag;
use GraphQL\Validator\Rules\QueryDepth;
use Psr\Http\Server\MiddlewareInterface;
use TheCodingMachine\GraphQLite\Containers\BasicAutoWiringContainer;
use TheCodingMachine\GraphQLite\Discovery\StaticClassFinder;
use TheCodingMachine\GraphQLite\Http\Psr15GraphQLMiddlewareBuilder;
use TheCodingMachine\GraphQLite\SchemaFactory;
use Throwable;

final readonly class GraphqliteEngine implements GraphqlEngineInterface
{
    public function createMiddleware(GraphqlEngineContext $context): MiddlewareInterface
    {
        $config = $context->engineConfig();
        $queries = $this->classList($config, 'queries', required: true);
        $types = $this->classList($config, 'types', required: false);
        $classes = array_values(array_unique([...$queries, ...$types]));
        $cacheName = $this->cacheName($config);
        $debug = $this->debug($config);
        $maxDepth = $this->optionalNonNegativeInt($config, 'maxDepth');
        $maxComplexity = $this->optionalNonNegativeInt($config, 'maxComplexity');

        try {
            $container = new BasicAutoWiringContainer($context->container());
            $schemaFactory = new SchemaFactory(Cache::pool($cacheName), $container);
            $schemaFactory->setFinder(new StaticClassFinder($classes));
            $this->configureAuthenticationService($schemaFactory, $context);

            foreach ($this->namespaces($classes) as $namespace) {
                $schemaFactory->addNamespace($namespace);
            }

            $debug ? $schemaFactory->devMode() : $schemaFactory->prodMode();

            $builder = new Psr15GraphQLMiddlewareBuilder($schemaFactory->createSchema());
            $builder->setUrl($context->path());
            $builder->getConfig()->setDebugFlag(
                $debug ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : DebugFlag::NONE,
            );
            if ($maxDepth !== null) {
                $builder->addValidationRule(new QueryDepth($maxDepth));
            }
            if ($maxComplexity !== null) {
                $builder->limitQueryComplexity($maxComplexity);
            }

            return $builder->createMiddleware();
        } catch (GraphqlConfigurationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GraphqlConfigurationException(
                'Unable to create GraphQLite middleware: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    private function configureAuthenticationService(SchemaFactory $schemaFactory, GraphqlEngineContext $context): void
    {
        if (!$context->container()->has(CakeAuthenticationService::class)) {
            return;
        }

        $authenticationService = $context->container()->get(CakeAuthenticationService::class);
        if ($authenticationService instanceof CakeAuthenticationService) {
            $schemaFactory->setAuthenticationService($authenticationService);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return list<class-string>
     */
    private function classList(array $config, string $key, bool $required): array
    {
        $classes = $config[$key] ?? [];
        if (!is_array($classes)) {
            throw new GraphqlConfigurationException(sprintf('Graphql.engines.Graphqlite.%s must be an array.', $key));
        }

        if ($required && $classes === []) {
            throw new GraphqlConfigurationException(
                sprintf('Graphql.engines.Graphqlite.%s must contain at least one class.', $key),
            );
        }

        $validated = [];
        foreach ($classes as $class) {
            if (!is_string($class) || $class === '') {
                throw new GraphqlConfigurationException(
                    sprintf('Graphql.engines.Graphqlite.%s must contain class-name strings.', $key),
                );
            }

            if (!class_exists($class)) {
                $label = $key === 'queries' ? 'query' : 'type';
                throw new GraphqlConfigurationException(
                    sprintf('Configured GraphQLite %s class "%s" does not exist.', $label, $class),
                );
            }

            /** @var class-string $class */
            $validated[] = $class;
        }

        return array_values(array_unique($validated));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function cacheName(array $config): string
    {
        $cacheName = $config['cache'] ?? 'default';
        if (!is_string($cacheName) || $cacheName === '') {
            throw new GraphqlConfigurationException('Graphql.engines.Graphqlite.cache must be a non-empty string.');
        }

        return $cacheName;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function debug(array $config): bool
    {
        $debug = $config['debug'] ?? false;
        if (!is_bool($debug)) {
            throw new GraphqlConfigurationException('Graphql.engines.Graphqlite.debug must be a boolean.');
        }

        return $debug;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function optionalNonNegativeInt(array $config, string $key): ?int
    {
        if (!array_key_exists($key, $config)) {
            return null;
        }

        $value = $config[$key];
        if (!is_int($value) || $value < 0) {
            throw new GraphqlConfigurationException(
                sprintf('Graphql.engines.Graphqlite.%s must be a non-negative integer.', $key),
            );
        }

        return $value;
    }

    /**
     * @param list<class-string> $classes
     * @return list<string>
     */
    private function namespaces(array $classes): array
    {
        $namespaces = [];
        foreach ($classes as $class) {
            $separator = strrpos($class, '\\');
            if ($separator === false) {
                continue;
            }
            $namespaces[] = substr($class, 0, $separator);
        }

        return array_values(array_unique($namespaces));
    }
}
