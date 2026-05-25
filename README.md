[![CI](https://github.com/reydajp/cakephp-graphql-plugin/actions/workflows/ci.yml/badge.svg?branch=dev)](https://github.com/reydajp/cakephp-graphql-plugin/actions/workflows/ci.yml?query=branch%3Adev)
[![Coverage](https://codecov.io/gh/reydajp/cakephp-graphql-plugin/branch/dev/graph/badge.svg)](https://codecov.io/gh/reydajp/cakephp-graphql-plugin)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

# CakeGraphQL

CakeGraphQL is a CakePHP 5 plugin for GraphQL endpoint routing, endpoint-level authentication, engine selection, and GraphQLite integration.

The plugin owns the HTTP endpoint and GraphQL engine wiring. The host application owns resolver classes, entity/type annotations, authentication provider setup, field-level authorization, and business logic.

## Requirements

- PHP 8.3 or newer
- CakePHP 5
- GraphQLite 8
- Webonyx GraphQL PHP 15

## Installation

```bash
composer require reydajp/cake-graphql
```

Load the plugin in the host application:

```php
// src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('CakeGraphQL');
}
```

## Configuration

Configure the plugin with the `Graphql` key:

```php
// config/app_local.php or config/app.php
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
            'maxDepth' => 10,
            'maxComplexity' => 200,
        ],
    ],
],
```

The plugin registers the configured route and attaches route-specific GraphQL middleware. CakePHP routing middleware must run as usual in the host application. `maxDepth` and `maxComplexity` reject overly nested or expensive GraphQL operations before resolver execution.

See [docs/configuration.md](docs/configuration.md) for the full configuration contract.

## Resolver Example

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

        return $this->fetchTable('Users')->find()->limit($limit)->all()->toList();
    }
}
```

Register resolver classes explicitly in `Graphql.engines.Graphqlite.queries`. CakeGraphQL does not scan broad application namespaces by default.

See [docs/graphqlite.md](docs/graphqlite.md) for CakePHP examples using GraphQLite `#[Query]`, `#[Type]`, and `#[Field]` attributes.

## Bake Command

Generate a collection query resolver:

```bash
bin/cake bake graphql query Users
```

Generate a single-record query resolver:

```bash
bin/cake bake graphql query Users --single
```

Generate into a custom namespace:

```bash
bin/cake bake graphql query Users --namespace App\\Api\\Graphql
```

By default, the command updates `Graphql.engines.Graphqlite.queries` in `config/app_local.php` or `config/app.php`. Use `--no-config` to skip that update.

See [docs/bake.md](docs/bake.md) for details.

## Authentication

When `authenticated` is `true`, CakeGraphQL rejects requests before GraphQL execution if the request has no `identity` attribute. This relies on the host application's Cake Authentication middleware running before the GraphQL route middleware.

CakeGraphQL bridges Cake Authentication's request identity into GraphQLite's authentication service. GraphQLite resolvers can inject the current Cake user with `#[InjectUser]`:

```php
use App\Model\Entity\User;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;

final class UsersQuery
{
    #[Query]
    public function me(#[InjectUser] ?User $user): ?User
    {
        return $user;
    }
}
```

If the injected parameter is not nullable, GraphQLite requires a logged-in user for that field. `#[Logged]` also uses the bridged Cake identity.

The bridge reads Cake's request `identity` attribute. `AuthenticationComponent` remains controller-only and is not available inside resolver classes.

Field-level authorization is intentionally engine-specific. GraphQLite attributes such as `#[Right]` or `#[Security]` require GraphQLite authorization services; CakeGraphQL does not currently bridge Cake Authorization into those services.

See [docs/graphqlite.md](docs/graphqlite.md#security-attributes) for examples and the current integration boundary.

## Commands

```bash
composer test
composer validate --strict
find src tests -name '*.php' -exec php -l {} \;
```

## License

MIT. See [LICENSE](LICENSE).
