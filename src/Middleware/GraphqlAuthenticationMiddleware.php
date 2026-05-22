<?php
declare(strict_types=1);

namespace CakeGraphQL\Middleware;

use Cake\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GraphqlAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly bool $authenticated)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->authenticated && $request->getAttribute('identity') === null) {
            throw new UnauthorizedException('GraphQL authentication required.');
        }

        return $handler->handle($request);
    }
}
