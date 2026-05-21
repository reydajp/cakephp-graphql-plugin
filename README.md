# CakeGraphQL

CakeGraphQL is a CakePHP 5 plugin for GraphQL endpoint routing, endpoint-level authentication, engine selection, and GraphQLite integration.

The plugin owns the HTTP endpoint and GraphQL engine wiring. The host application owns resolver classes, entity/type annotations, authentication provider setup, field-level authorization, and business logic.

## Requirements

- PHP 8.3 or newer
- CakePHP 5
- GraphQLite 8
- Webonyx GraphQL PHP 15

## Installation

For local development, install this plugin as a path repository from the host CakePHP application:

```bash
composer config repositories.cake-graphql path plugins/CakeGraphQL
composer require cake-graphql/cake-graphql
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
        ],
    ],
],
```

The plugin registers the configured route and attaches route-specific GraphQL middleware. CakePHP routing middleware must run as usual in the host application.

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
    public function users(): array
    {
        return $this->fetchTable('Users')->find()->all()->toList();
    }
}
```

Register resolver classes explicitly in `Graphql.engines.Graphqlite.queries`. CakeGraphQL does not scan broad application namespaces by default.

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

Field-level authorization is intentionally engine-specific. For GraphQLite, use GraphQLite attributes such as `#[Logged]`, `#[Right]`, or `#[Security]` inside application resolvers and types.

## Commands

```bash
composer test
composer validate --strict
find src tests -name '*.php' -exec php -l {} \;
```

## License

MIT. See [LICENSE](LICENSE).
