# Architecture

CakeGraphQL is a thin CakePHP plugin that wires one GraphQL endpoint to a selected GraphQL engine. It does not own application schema design, resolver business logic, persistence, authentication providers, or field-level authorization.

## Runtime Flow

1. The host application loads the plugin.
2. `Plugin::routes()` reads the `Graphql` Cake configuration key.
3. `GraphqlRouteLoader` registers the configured GraphQL path and attaches `GraphqlEndpointMiddleware` only to that route.
4. `GraphqlEndpointMiddleware` checks endpoint authentication and delegates execution to the selected engine middleware.
5. `GraphqliteEngine` builds the GraphQLite schema from explicitly configured query and type classes.
6. GraphQLite/Webonyx validates and executes the operation, then returns the GraphQL JSON response.

## Main Components

`GraphqlConfig`

Validates the top-level `Graphql` configuration and exposes the selected engine block.

`GraphqlRouteLoader`

Connects the configured endpoint path and applies the plugin middleware to that route only.

`GraphqlEndpointMiddleware`

Coordinates endpoint authentication and selected engine execution. The engine middleware is created lazily and reused for later requests.

`GraphqlEngineRegistry`

Maps engine names to engine adapters. Version 1 registers `Graphqlite` by default while keeping the boundary open for additional engines.

`GraphqliteEngine`

Adapts GraphQLite to the plugin engine interface. It uses explicit query/type class lists, Cake cache pools, GraphQL debug flags, and optional Webonyx depth and complexity validators.

`BakeGraphqlQueryCommand`

Generates GraphQLite query resolver classes and can update `Graphql.engines.Graphqlite.queries` without evaluating the host application's config file.

## Boundaries

CakeGraphQL owns:

- Endpoint route registration
- Endpoint-level authentication toggle
- Engine selection and configuration validation
- GraphQLite middleware construction
- Bake helper command for query resolvers

The host application owns:

- Resolver classes and GraphQLite attributes
- Entity/type classes exposed to GraphQLite
- Authentication middleware/provider setup
- Field-level authorization rules
- Database queries and pagination policy
- Business logic and error semantics

## Decision Records

- [ADR-001: Use an Engine Boundary Around GraphQLite](decisions/001-use-engine-boundary-around-graphqlite.md)
- [ADR-002: Require Explicit GraphQLite Class Registration](decisions/002-require-explicit-graphqlite-class-registration.md)
- [ADR-003: Keep Authentication at the Endpoint Boundary](decisions/003-keep-authentication-at-the-endpoint-boundary.md)
