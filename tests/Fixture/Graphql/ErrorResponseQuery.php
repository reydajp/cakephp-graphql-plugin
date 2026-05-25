<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use Cake\Http\Exception\NotFoundException;
use RuntimeException;
use TheCodingMachine\GraphQLite\Annotations\Query;

final class ErrorResponseQuery
{
    #[Query]
    public function user(): ?string
    {
        throw new NotFoundException('User not found');
    }

    #[Query]
    public function unsafeError(): ?string
    {
        throw new RuntimeException('Do not expose this internal detail');
    }

    /**
     * @return list<Product>
     */
    #[Query]
    public function products(): array
    {
        return [
            new Product('1'),
        ];
    }
}
