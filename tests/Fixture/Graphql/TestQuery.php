<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use TheCodingMachine\GraphQLite\Annotations\Query;

final class TestQuery
{
    #[Query]
    public function hello(): string
    {
        return 'world';
    }
}
