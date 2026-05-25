<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Engine;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Core\Container;
use Cake\Http\ServerRequest;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqliteEngine;
use CakeGraphQL\Exception\GraphqlConfigurationException;
use CakeGraphQL\Security\CakeAuthenticationService;
use CakeGraphQL\Test\Fixture\Graphql\AuthenticatedQuery;
use CakeGraphQL\Test\Fixture\Graphql\ErrorResponseQuery;
use CakeGraphQL\Test\Fixture\Graphql\Product;
use CakeGraphQL\Test\Fixture\Graphql\TestQuery;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqliteEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::drop('cake_graphql_test');
        Cache::setConfig('cake_graphql_test', [
            'className' => ArrayEngine::class,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Cache::drop('cake_graphql_test');
    }

    public function testExecutesQueryFromExplicitClassList(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));

        $response = $middleware->process($this->request('{ hello }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['hello' => 'world'], $payload['data']);
    }

    public function testInjectsCurrentUserFromCakeAuthenticationBridge(): void
    {
        $user = new \stdClass();
        $user->name = 'Ada';
        $identity = new class ($user) {
            public function __construct(private readonly object $user)
            {
            }

            public function getOriginalData(): object
            {
                return $this->user;
            }
        };
        $authenticationService = new CakeAuthenticationService();
        $authenticationService->setIdentity($identity);
        $container = new Container();
        $container->addShared(CakeAuthenticationService::class, $authenticationService);
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [AuthenticatedQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ], $container));

        $response = $middleware->process($this->request('{ currentUserName }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['currentUserName' => 'Ada'], $payload['data']);
    }

    public function testDoesNotScanUnlistedQueryClasses(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));

        $response = $middleware->process($this->request('{ hidden }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertStringContainsString('Cannot query field "hidden"', $payload['errors'][0]['message']);
    }

    public function testResolverHttpExceptionUsesGraphqlPartialErrorResponse(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [ErrorResponseQuery::class],
            'types' => [Product::class],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));

        $response = $middleware->process($this->request('{ user products { id } }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'user' => null,
            'products' => [
                ['id' => '1'],
            ],
        ], $payload['data']);
        $this->assertSame('User not found', $payload['errors'][0]['message']);
        $this->assertSame(['user'], $payload['errors'][0]['path']);
    }

    public function testResolverUnsafeExceptionStaysMaskedInNonDebugResponse(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [ErrorResponseQuery::class],
            'types' => [Product::class],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));

        $response = $middleware->process($this->request('{ unsafeError products { id } }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'unsafeError' => null,
            'products' => [
                ['id' => '1'],
            ],
        ], $payload['data']);
        $this->assertSame('Internal server error', $payload['errors'][0]['message']);
        $this->assertSame(['unsafeError'], $payload['errors'][0]['path']);
        $this->assertArrayNotHasKey('extensions', $payload['errors'][0]);
    }

    public function testAppliesQueryComplexityLimit(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
            'maxComplexity' => 1,
        ]));

        $response = $middleware->process($this->request('{ hello helloAgain: hello }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $payload);
        $this->assertStringContainsString('Max query complexity should be 1', $payload['errors'][0]['message']);
    }

    public function testDisabledQueryComplexityLimitAllowsQuery(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
            'maxComplexity' => 0,
        ]));

        $response = $middleware->process($this->request('{ hello helloAgain: hello }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['hello' => 'world', 'helloAgain' => 'world'], $payload['data']);
    }

    public function testAppliesQueryDepthLimit(): void
    {
        $middleware = (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
            'maxDepth' => 1,
        ]));

        $response = $middleware->process(
            $this->request('{ __schema { queryType { fields { name } } } }'),
            $this->terminalHandler(),
        );
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $payload);
        $this->assertStringContainsString('Max query depth should be 1', $payload['errors'][0]['message']);
    }

    public function testRejectsInvalidMaxDepth(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engines.Graphqlite.maxDepth must be a non-negative integer.');

        (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
            'maxDepth' => -1,
        ]));
    }

    public function testRejectsInvalidMaxComplexity(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engines.Graphqlite.maxComplexity must be a non-negative integer.');

        (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
            'maxComplexity' => '100',
        ]));
    }

    public function testRejectsMissingQueryClass(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Configured GraphQLite query class "App\\Graphql\\MissingQuery" does not exist.');

        (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => ['App\\Graphql\\MissingQuery'],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));
    }

    public function testRejectsEmptyQueryClassList(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Graphql.engines.Graphqlite.queries must contain at least one class.');

        (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [],
            'types' => [],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));
    }

    public function testRejectsMissingTypeClass(): void
    {
        $this->expectException(GraphqlConfigurationException::class);
        $this->expectExceptionMessage('Configured GraphQLite type class "App\\Model\\Entity\\MissingUser" does not exist.');

        (new GraphqliteEngine())->createMiddleware($this->context([
            'queries' => [TestQuery::class],
            'types' => ['App\\Model\\Entity\\MissingUser'],
            'cache' => 'cake_graphql_test',
            'debug' => false,
        ]));
    }

    /**
     * @param array<string, mixed> $engineConfig
     */
    private function context(array $engineConfig, ?Container $container = null): GraphqlEngineContext
    {
        return new GraphqlEngineContext($container ?? new Container(), GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => true,
            'engines' => [
                'Graphqlite' => $engineConfig,
            ],
        ]));
    }

    private function request(string $query): ServerRequest
    {
        return (new ServerRequest([
            'url' => '/api/graphql',
            'environment' => [
                'REQUEST_METHOD' => 'POST',
            ],
            'post' => [
                'query' => $query,
            ],
        ]))->withHeader('Content-Type', 'application/json');
    }

    private function terminalHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(status: 500);
            }
        };
    }
}
