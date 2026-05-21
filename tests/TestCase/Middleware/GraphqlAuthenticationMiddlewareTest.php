<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Middleware;

use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\ServerRequest;
use CakeGraphQL\Middleware\GraphqlAuthenticationMiddleware;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlAuthenticationMiddlewareTest extends TestCase
{
    public function testRejectsUnauthenticatedRequestWhenAuthenticationIsRequired(): void
    {
        $middleware = new GraphqlAuthenticationMiddleware(true);
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->calls++;

                return new Response();
            }
        };

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('GraphQL authentication required.');

        try {
            $middleware->process(new ServerRequest(), $handler);
        } finally {
            $this->assertSame(0, $handler->calls);
        }
    }

    public function testAllowsAuthenticatedRequestWhenAuthenticationIsRequired(): void
    {
        $middleware = new GraphqlAuthenticationMiddleware(true);
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->calls++;

                return new Response(status: 204);
            }
        };
        $request = (new ServerRequest())->withAttribute('identity', new \stdClass());

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(1, $handler->calls);
    }

    public function testAllowsUnauthenticatedRequestWhenAuthenticationIsDisabled(): void
    {
        $middleware = new GraphqlAuthenticationMiddleware(false);
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->calls++;

                return new Response(status: 202);
            }
        };

        $response = $middleware->process(new ServerRequest(), $handler);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(1, $handler->calls);
    }
}
