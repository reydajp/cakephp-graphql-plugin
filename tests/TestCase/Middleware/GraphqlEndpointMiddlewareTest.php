<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Middleware;

use Cake\Core\Container;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\ServerRequest;
use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqlEngineInterface;
use CakeGraphQL\Engine\GraphqlEngineRegistry;
use CakeGraphQL\Middleware\GraphqlEndpointMiddleware;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlEndpointMiddlewareTest extends TestCase
{
    public function testAuthenticationRunsBeforeEngineMiddleware(): void
    {
        $engine = new RecordingEngine();
        $middleware = $this->middleware($engine, authenticated: true);

        $this->expectException(UnauthorizedException::class);

        try {
            $middleware->process(new ServerRequest(), $this->terminalHandler());
        } finally {
            $this->assertSame(0, $engine->calls);
        }
    }

    public function testAuthenticatedRequestRunsSelectedEngineMiddleware(): void
    {
        $engine = new RecordingEngine();
        $middleware = $this->middleware($engine, authenticated: true);
        $request = (new ServerRequest())->withAttribute('identity', new \stdClass());

        $response = $middleware->process($request, $this->terminalHandler());

        $this->assertSame(211, $response->getStatusCode());
        $this->assertSame(1, $engine->calls);
    }

    public function testDisabledAuthenticationRunsSelectedEngineMiddlewareWithoutIdentity(): void
    {
        $engine = new RecordingEngine();
        $middleware = $this->middleware($engine, authenticated: false);

        $response = $middleware->process(new ServerRequest(), $this->terminalHandler());

        $this->assertSame(211, $response->getStatusCode());
        $this->assertSame(1, $engine->calls);
    }

    public function testEngineMiddlewareIsCreatedOnce(): void
    {
        $engine = new RecordingEngine();
        $middleware = $this->middleware($engine, authenticated: false);

        $middleware->process(new ServerRequest(), $this->terminalHandler());
        $middleware->process(new ServerRequest(), $this->terminalHandler());

        $this->assertSame(1, $engine->middlewareCreations);
        $this->assertSame(2, $engine->calls);
    }

    private function middleware(RecordingEngine $engine, bool $authenticated): GraphqlEndpointMiddleware
    {
        $config = GraphqlConfig::fromArray([
            'path' => '/api/graphql',
            'engine' => 'Graphqlite',
            'authenticated' => $authenticated,
            'engines' => [
                'Graphqlite' => [],
            ],
        ]);
        $context = new GraphqlEngineContext(new Container(), $config);

        return new GraphqlEndpointMiddleware($config, $context, new GraphqlEngineRegistry([
            'Graphqlite' => $engine,
        ]));
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

final class RecordingEngine implements GraphqlEngineInterface
{
    public int $calls = 0;
    public int $middlewareCreations = 0;

    public function createMiddleware(GraphqlEngineContext $context): MiddlewareInterface
    {
        $this->middlewareCreations++;

        return new class ($this) implements MiddlewareInterface {
            public function __construct(private readonly RecordingEngine $engine)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->engine->calls++;

                return new Response(status: 211);
            }
        };
    }
}
