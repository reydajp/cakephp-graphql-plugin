<?php
declare(strict_types=1);

namespace CakeGraphQL\Engine;

use Psr\Http\Server\MiddlewareInterface;

interface GraphqlEngineInterface
{
    public function createMiddleware(GraphqlEngineContext $context): MiddlewareInterface;
}
