<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Query;

final class AuthenticatedQuery
{
    #[Query]
    public function currentUserName(#[InjectUser] \stdClass $user): string
    {
        return (string)$user->name;
    }

    #[Logged]
    #[Query]
    public function loggedMessage(): string
    {
        return 'logged';
    }

    #[Query]
    public function optionalCurrentUserName(#[InjectUser] ?\stdClass $user): ?string
    {
        return $user === null ? null : (string)$user->name;
    }
}
