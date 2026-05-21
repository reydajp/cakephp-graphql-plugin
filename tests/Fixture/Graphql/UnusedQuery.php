<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use TheCodingMachine\GraphQLite\Annotations\Query;

final class UnusedQuery
{
    #[Query]
    public function hidden(): string
    {
        return 'not listed';
    }
}
