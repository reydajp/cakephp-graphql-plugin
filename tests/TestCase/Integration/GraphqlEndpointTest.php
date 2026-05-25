<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Integration;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Http\ServerRequest;
use CakeGraphQL\GraphqlServiceProvider;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;
use CakeGraphQL\Test\Fixture\Graphql\AuthenticatedQuery;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlEndpointTest extends TestCase
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

        Configure::delete('Graphql');
        Cache::drop('cake_graphql_test');
    }

    public function testResolverReceivesAuthenticatedCakeUser(): void
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
        $middleware = $this->middleware(authenticated: true);

        $response = $middleware->process(
            $this->request('{ currentUserName loggedMessage }')->withAttribute('identity', $identity),
            $this->terminalHandler(),
        );
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'currentUserName' => 'Ada',
            'loggedMessage' => 'logged',
        ], $payload['data']);
    }

    public function testOptionalInjectedUserCanBeNullOnPublicEndpoint(): void
    {
        $middleware = $this->middleware(authenticated: false);

        $response = $middleware->process($this->request('{ optionalCurrentUserName }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['optionalCurrentUserName' => null], $payload['data']);
    }

    public function testLoggedResolverRejectsAnonymousRequestOnPublicEndpoint(): void
    {
        $middleware = $this->middleware(authenticated: false);

        $response = $middleware->process($this->request('{ loggedMessage }'), $this->terminalHandler());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame('You need to be logged to access this field', $payload['errors'][0]['message']);
    }

    private function middleware(bool $authenticated): GraphqlEndpointMiddleware
    {
        Configure::write('Graphql', [
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => $authenticated,
            'engines' => [
                'Graphqlite' => [
                    'queries' => [AuthenticatedQuery::class],
                    'types' => [],
                    'cache' => 'cake_graphql_test',
                    'debug' => false,
                ],
            ],
        ]);
        $container = new Container();
        (new GraphqlServiceProvider())->services($container);

        return $container->get(GraphqlEndpointMiddleware::class);
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
