<?php
declare(strict_types=1);

namespace CakeGraphQL\Error;

use Cake\Http\Exception\HttpException;
use GraphQL\Executor\ExecutionResult;
use TheCodingMachine\GraphQLite\Exceptions\WebonyxErrorHandler;
use Throwable;

/**
 * @phpstan-import-type SerializableError from ExecutionResult
 */
final class GraphqlErrorFormatter
{
    /**
     * @return SerializableError
     */
    public static function format(Throwable $error): array
    {
        $formatted = WebonyxErrorHandler::errorFormatter($error);
        $previous = $error->getPrevious();

        if ($previous instanceof HttpException && self::isSafeHttpException($previous)) {
            $formatted['message'] = $previous->getMessage();
        }

        return $formatted;
    }

    private static function isSafeHttpException(HttpException $exception): bool
    {
        $code = $exception->getCode();

        return $code >= 400 && $code < 500;
    }
}
