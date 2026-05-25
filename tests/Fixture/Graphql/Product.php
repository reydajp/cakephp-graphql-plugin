<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\Fixture\Graphql;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
final readonly class Product
{
    public function __construct(private string $id)
    {
    }

    #[Field]
    public function getId(): string
    {
        return $this->id;
    }
}
