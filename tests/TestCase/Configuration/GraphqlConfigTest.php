<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Configuration;

use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Exception\GraphqlConfigurationException;
use PHPUnit\Framework\TestCase;

final class GraphqlConfigTest extends TestCase
{
    public function testValidConfigIsAccepted(): void
    {
        $config = GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => [
                    'queries' => ['App\\Graphql\\UsersQuery'],
                    'types' => ['App\\Model\\Entity\\User'],
                    'cache' => 'default',
                    'debug' => false,
                ],
            ],
        ]);

        $this->assertSame('/api/graphql', $config->path());
        $this->assertSame('Graphqlite', $config->engine());
        $this->assertTrue($config->authenticated());
        $this->assertSame(
            ['queries' => ['App\\Graphql\\UsersQuery'], 'types' => ['App\\Model\\Entity\\User'], 'cache' => 'default', 'debug' => false],
            $config->selectedEngineConfig(),
        );
    }

    public function testAuthenticatedDefaultsToTrue(): void
    {
        $config = GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);

        $this->assertTrue($config->authenticated());
    }

    public function testMissingPathFailsClearly(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.path must be a non-empty string.');

        GraphqlConfig::fromArray([
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
    }

    public function testPathMustStartWithSlash(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.path must start with "/".');

        GraphqlConfig::fromArray([
            'path' => 'api/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
    }

    public function testMissingEngineFailsClearly(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engine must be a non-empty string.');

        GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
    }

    public function testSelectedEngineMustHaveConfigBlock(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engines.Webonyx must be configured for the selected engine.');

        GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Webonyx',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
    }

    public function testEnginesMustBeArray(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engines must be an array.');

        GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'engines' => 'Graphqlite',
        ]);
    }
}
