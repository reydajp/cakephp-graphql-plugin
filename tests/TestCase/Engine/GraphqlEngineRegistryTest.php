<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Engine;

use Cake\Core\Container;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqlEngineInterface;
use CakeGraphQL\Engine\GraphqlEngineRegistry;
use CakeGraphQL\Exception\GraphqlConfigurationException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlEngineRegistryTest extends TestCase
{
    public function testResolveReturnsRegisteredEngineByName(): void
    {
        $engine = $this->engine();
        $registry = new GraphqlEngineRegistry([
            'Graphqlite' => $engine,
        ]);

        $this->assertSame($engine, $registry->get('Graphqlite'));
    }

    public function testResolveRejectsUnknownEngineName(): void
    {
        $registry = new GraphqlEngineRegistry([
            'Graphqlite' => $this->engine(),
        ]);

        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('GraphQL engine "Webonyx" is not registered.');

        $registry->get('Webonyx');
    }

    public function testResolveSelectedEngineFromContext(): void
    {
        $engine = $this->engine();
        $registry = new GraphqlEngineRegistry([
            'Graphqlite' => $engine,
        ]);
        $context = new GraphqlEngineContext(new Container(), GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'engines' => [
                'Graphqlite' => [],
            ],
        ]));

        $this->assertSame($engine, $registry->getForContext($context));
    }

    private function engine(): GraphqlEngineInterface
    {
        return new class implements GraphqlEngineInterface {
            public function createMiddleware(GraphqlEngineContext $context): MiddlewareInterface
            {
                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            }
        };
    }
}
