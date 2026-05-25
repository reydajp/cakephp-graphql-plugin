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
            'maxDepth' => 10,
            'maxComplexity' => 200,
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

`maxDepth`

Optional non-negative integer passed to Webonyx's query depth validator. Set a positive value to reject overly nested operations before resolver execution. Set `0` to disable the depth validator, or omit the key to leave the plugin's default validation rules unchanged.

`maxComplexity`

Optional non-negative integer passed to Webonyx's query complexity validator. Set a positive value to reject overly expensive operations before resolver execution. Set `0` to disable the complexity validator, or omit the key to leave the plugin's default validation rules unchanged.

## Request Flow

For authenticated endpoints, the intended host application flow is:

1. Cake routing matches the configured GraphQL path.
2. Body parsing runs.
3. Cake Authentication attaches the request `identity`.
4. CakeGraphQL copies the identity into its GraphQLite authentication bridge for resolver-level `#[Logged]` and `#[InjectUser]` support.
5. CakeGraphQL rejects missing identity when `authenticated` is `true`.
6. The selected engine middleware executes the GraphQL operation.
7. The engine returns the GraphQL JSON response.

## Error Responses

CakeGraphQL keeps transport-level authentication failures separate from GraphQL execution errors.

When `authenticated` is `true` and no Cake `identity` attribute exists on the request, the endpoint rejects the request before GraphQL execution with HTTP `401`.

Once GraphQL execution starts, resolver and field errors follow the common GraphQL partial-response pattern. Successfully resolved fields stay in `data`, failed nullable fields become `null`, and `errors` contains the field error with its `path`. Cake HTTP exceptions raised by resolvers expose their client-safe message while still returning HTTP `200` when partial data exists.

Resolver-level authentication errors from GraphQLite attributes such as `#[Logged]` are execution errors, so they also return GraphQL error payloads with HTTP `200`.

```json
{
  "data": {
    "user": null,
    "products": [
      { "id": "1" }
    ]
  },
  "errors": [
    {
      "message": "User not found",
      "path": ["user"]
    }
  ]
}
```

Unsafe exceptions remain masked as `Internal server error` when `debug` is `false`.

## Boundaries

CakeGraphQL does not define application schema DSLs, business logic, field-level authorization rules, or authentication providers. Those stay in the host application and selected GraphQL engine.
