# Configuration

CakeGraphQL reads a `Graphql` configuration key from CakePHP configuration.

```php
'Graphql' => [
    'path' => '/api/graphql',
    'engine' => 'Graphqlite',
    'authenticated' => true,
    'engines' => [
        'Graphqlite' => [
            'queries' => [],
            'types' => [],
            'cache' => 'default',
            'debug' => false,
        ],
    ],
],
```

## Top-Level Keys

`path`

The HTTP path for the GraphQL endpoint. It must be a non-empty string that starts with `/`.

`engine`

The selected engine name. Version 1 registers `Graphqlite` by default.

`authenticated`

When `true`, requests without a Cake Authentication identity are rejected before GraphQL execution. Defaults to `true` if omitted.

`engines`

An array of engine-specific configuration blocks. The selected engine must have a matching block.

## GraphQLite Configuration

`queries`

Required list of resolver class names. The list must contain at least one class.

`types`

Optional list of type/entity class names. These classes are included in GraphQLite's explicit class finder.

`cache`

Cake cache pool name used by GraphQLite. Defaults to `default`.

`debug`

When `true`, GraphQL error responses include debug details. Defaults to `false`. Keep this disabled outside local development because debug responses can include internal exception details and traces.

## Request Flow

For authenticated endpoints, the intended host application flow is:

1. Cake routing matches the configured GraphQL path.
2. Body parsing runs.
3. Cake Authentication attaches the request `identity`.
4. CakeGraphQL rejects missing identity when `authenticated` is `true`.
5. The selected engine middleware executes the GraphQL operation.
6. The engine returns the GraphQL JSON response.

## Boundaries

CakeGraphQL does not define application schema DSLs, business logic, field-level authorization rules, or authentication providers. Those stay in the host application and selected GraphQL engine.
