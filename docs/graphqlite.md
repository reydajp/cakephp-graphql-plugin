# GraphQLite Usage

CakeGraphQL uses GraphQLite as its default engine. The plugin wires the endpoint and GraphQLite middleware; your CakePHP application owns the resolver classes, entity type attributes, and business logic.

For the full GraphQLite attribute reference, see the [GraphQLite documentation](https://graphqlite.thecodingmachine.io/docs).

## Query Resolver

Create resolver classes in your CakePHP application and expose methods with `#[Query]`.

```php
<?php
declare(strict_types=1);

namespace App\Graphql;

use Cake\ORM\Locator\LocatorAwareTrait;
use TheCodingMachine\GraphQLite\Annotations\Query;

final class UsersQuery
{
    use LocatorAwareTrait;

    #[Query]
    public function users(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        return $this->fetchTable('Users')
            ->find()
            ->limit($limit)
            ->all()
            ->toList();
    }
}
```

This exposes a GraphQL query similar to:

```graphql
{
  users(limit: 10) {
    id
    name
  }
}
```

## Entity Type

GraphQLite only exposes fields you mark. Add `#[Type]` to the entity class and `#[Field]` to the methods you want in the GraphQL schema.

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
final class User extends Entity
{
    #[Field]
    public function getId(): string
    {
        return (string)$this->get('id');
    }

    #[Field]
    public function getName(): string
    {
        return (string)$this->get('name');
    }
}
```

## Register Classes

CakeGraphQL uses explicit class registration. Add resolver classes to `queries` and entity/type classes to `types`.

```php
'Graphql' => [
    'path' => '/api/graphql',
    'engine' => 'Graphqlite',
    'authenticated' => true,
    'engines' => [
        'Graphqlite' => [
            'queries' => [
                App\Graphql\UsersQuery::class,
            ],
            'types' => [
                App\Model\Entity\User::class,
            ],
            'cache' => 'default',
            'debug' => false,
        ],
    ],
],
```

## Security Attributes

GraphQLite provides field-level security attributes such as `#[Logged]`, `#[Right]`, and `#[Security]`.

CakeGraphQL does not currently bridge CakePHP Authentication or Authorization into GraphQLite's `AuthenticationServiceInterface` or `AuthorizationServiceInterface`. These examples show GraphQLite syntax, but they require GraphQLite auth services to be wired before they will work in a CakePHP application.

`#[Logged]` requires a logged-in GraphQLite user:

```php
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Query;

#[Logged]
#[Query]
public function me(): User
{
    return $this->fetchTable('Users')->getCurrentUser();
}
```

`#[Right]` checks an authorization right:

```php
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Right;

#[Right('admin')]
#[Query]
public function adminUsers(): array
{
    return $this->fetchTable('Users')->find()->all()->toList();
}
```

`#[Security]` evaluates a GraphQLite security expression:

```php
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Security;

#[Security("is_logged() and is_granted('admin')")]
#[Query]
public function auditLog(): array
{
    return $this->fetchTable('AuditLogs')->find()->all()->toList();
}
```

Use CakeGraphQL's `authenticated` option for endpoint-level protection today. Use GraphQLite security attributes only after adding an integration that supplies GraphQLite authentication and authorization services.
