<?php
declare(strict_types=1);

namespace CakeGraphQL\Middleware;

use CakeGraphQL\Configuration\GraphqlConfig;
use CakeGraphQL\Engine\GraphqlEngineContext;
use CakeGraphQL\Engine\GraphqlEngineRegistry;
use CakeGraphQL\Security\CakeAuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphqlEndpointMiddleware implements MiddlewareInterface
{
    private ?MiddlewareInterface $engineMiddleware = null;

    public function __construct(
        private readonly GraphqlConfig $config,
        private readonly GraphqlEngineContext $context,
        private readonly GraphqlEngineRegistry $registry,
        private readonly CakeAuthenticationService $authenticationService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $engineMiddleware = $this->engineMiddleware ??= $this->registry
            ->getForContext($this->context)
            ->createMiddleware($this->context);
        $authenticationMiddleware = new GraphqlAuthenticationMiddleware($this->config->authenticated());

        $identity = $request->getAttribute('identity');
        $this->authenticationService->setIdentity(is_object($identity) ? $identity : null);

        try {
            return $authenticationMiddleware->process(
                $request,
                new readonly class ($engineMiddleware, $handler) implements RequestHandlerInterface {
                    public function __construct(
                        private readonly MiddlewareInterface $engineMiddleware,
                        private readonly RequestHandlerInterface $handler,
                    ) {
                    }

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->engineMiddleware->process($request, $this->handler);
                    }
                },
            );
        } finally {
            $this->authenticationService->clearIdentity();
        }
    }
}
