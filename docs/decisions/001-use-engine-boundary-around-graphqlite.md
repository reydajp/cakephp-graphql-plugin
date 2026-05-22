# ADR-001: Use an Engine Boundary Around GraphQLite

## Status

Accepted

## Date

2026-05-22

## Context

CakeGraphQL needs to provide GraphQL support for CakePHP applications without becoming the owner of every GraphQL concern. GraphQLite is the initial implementation engine, but the plugin also needs a stable place for endpoint routing, configuration validation, and middleware composition.

Binding the whole plugin directly to GraphQLite APIs would make future engine changes expensive and would spread GraphQLite-specific configuration through routing and middleware code.

## Decision

Use a small engine interface and registry:

- `GraphqlEngineInterface` creates PSR-15 middleware for a validated engine context.
- `GraphqlEngineRegistry` resolves the selected engine by name.
- `GraphqlEngineContext` passes shared Cake container, endpoint path, selected engine name, and selected engine config to the adapter.
- `GraphqliteEngine` contains GraphQLite-specific schema and middleware construction.

Version 1 registers `Graphqlite` by default.

## Alternatives Considered

### Hard-code GraphQLite in the endpoint middleware

- Pros: Fewer classes and less indirection.
- Cons: Couples routing/authentication to GraphQLite construction and makes another engine a cross-cutting change.
- Rejected: The plugin boundary should stay stable while engine-specific wiring evolves.

### Expose GraphQLite directly and skip a plugin engine layer

- Pros: Host applications can use GraphQLite APIs without adaptation.
- Cons: The plugin would add little beyond route registration and would not have a coherent place for shared validation, auth, or future engines.
- Rejected: CakeGraphQL should own CakePHP integration and expose a stable configuration contract.

## Consequences

- The initial GraphQLite implementation has a little more structure than a direct middleware wrapper.
- Future engines can be added without rewriting route registration or endpoint authentication.
- Engine-specific options stay inside the selected engine config block.
- Tests can validate shared behavior separately from GraphQLite-specific wiring.
