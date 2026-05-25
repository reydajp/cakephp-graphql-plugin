<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;

final class AuthenticatedQuery
{
    #[Query]
    public function currentUserName(#[InjectUser] \stdClass $user): string
    {
        return (string)$user->name;
    }
}
