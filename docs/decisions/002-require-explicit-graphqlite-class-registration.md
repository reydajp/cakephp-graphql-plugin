# ADR-002: Require Explicit GraphQLite Class Registration

## Status

Accepted

## Date

2026-05-22

## Context

GraphQLite can discover annotated classes by scanning namespaces. Broad namespace scanning is convenient, but it can expose classes unintentionally and makes schema shape depend on files outside the plugin configuration.

CakeGraphQL is intended to be predictable in host applications. A generated resolver should only become part of the schema when it is explicitly registered, and schema construction should avoid unnecessary filesystem scanning.

## Decision

Require GraphQLite query and type classes to be configured explicitly:

```php
'Graphql' => [
    'engines' => [
        'Graphqlite' => [
            'queries' => [
                App\Graphql\UsersQuery::class,
            ],
            'types' => [
                App\Model\Entity\User::class,
            ],
        ],
    ],
],
```

`GraphqliteEngine` passes those classes to GraphQLite's `StaticClassFinder`. The bake command can append generated query resolver classes to `Graphql.engines.Graphqlite.queries`.

## Alternatives Considered

### Scan `App\Graphql` automatically

- Pros: Less configuration for small applications.
- Cons: Schema contents depend on namespace scanning and can include classes before the host application has intentionally exposed them.
- Rejected: Explicit registration is safer and easier to reason about.

### Let host applications create the GraphQLite schema themselves

- Pros: Maximum flexibility.
- Cons: The plugin would not provide useful GraphQLite integration and would duplicate setup across applications.
- Rejected: The plugin should provide the common CakePHP/GraphQLite wiring while keeping schema ownership in the host app.

## Consequences

- Host applications must list resolver/type classes in configuration.
- Generated resolvers can be registered automatically by the bake command.
- Schema construction is deterministic and easier to test.
- The plugin avoids exposing annotated classes only because they exist under a scanned namespace.
