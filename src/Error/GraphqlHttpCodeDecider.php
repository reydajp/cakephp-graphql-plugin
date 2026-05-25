<?php
declare(strict_types=1);

namespace CakeGraphQL\Error;

use GraphQL\Executor\ExecutionResult;
use TheCodingMachine\GraphQLite\Http\HttpCodeDecider;
use TheCodingMachine\GraphQLite\Http\HttpCodeDeciderInterface;

final readonly class GraphqlHttpCodeDecider implements HttpCodeDeciderInterface
{
    public function __construct(private HttpCodeDeciderInterface $fallback = new HttpCodeDecider())
    {
    }

    public function decideHttpStatusCode(ExecutionResult $result): int
    {
        if ($result->data !== null) {
            return 200;
        }

        foreach ($result->errors as $error) {
            if ($error->getPath() !== null) {
                return 200;
            }
        }

        return $this->fallback->decideHttpStatusCode($result);
    }
}
