# ADR-001: Bridge Cake Authentication into GraphQLite

## Status
Accepted

## Date
2026-05-25

## Context
CakeGraphQL owns the GraphQL endpoint and engine middleware, while host applications own CakePHP Authentication setup and resolver business logic. Cake Authentication exposes the current user as request-scoped attributes, including `identity`, after the host application's authentication middleware runs.

GraphQLite has its own authentication contract, `AuthenticationServiceInterface`, which powers resolver-level features such as `#[Logged]` and `#[InjectUser]`. Without a bridge, CakeGraphQL could protect the whole endpoint with `Graphql.authenticated`, but GraphQLite resolvers could not access Cake's current user through GraphQLite's native resolver APIs.

The bridge must avoid `AuthenticationComponent` because that component is controller-scoped and not available inside PSR-15 middleware or GraphQLite resolver execution.

## Decision
CakeGraphQL provides a shared request-aware `CakeAuthenticationService` implementing GraphQLite's `AuthenticationServiceInterface`.

`GraphqlEndpointMiddleware` copies the current request `identity` attribute into the bridge before GraphQL execution and clears it in a `finally` block afterward. This keeps the service safe for long-running PHP workers where shared services can outlive a single request.

`GraphqliteEngine` passes the bridge to GraphQLite's `SchemaFactory` when the service is registered in the container. This lets resolvers use:

- `#[InjectUser]` to receive the current Cake user.
- `#[Logged]` to require an authenticated user at resolver level.

Endpoint-level authentication remains controlled by `Graphql.authenticated`.

## Alternatives Considered

### Use `AuthenticationComponent` in resolvers
- Pros: Familiar to CakePHP controller code.
- Cons: Controller-scoped, unavailable in middleware/resolvers, couples GraphQL execution to controller internals.
- Rejected: The GraphQL endpoint executes through PSR-15 middleware and GraphQLite, not normal controller component access.

### Pass the PSR-7 request directly into every resolver
- Pros: Gives resolvers full request context.
- Cons: Couples application resolver code to HTTP details and bypasses GraphQLite's native authentication attributes.
- Rejected: Resolver code should use GraphQLite's typed injection and security attributes.

### Keep only endpoint-level authentication
- Pros: Simple and already supported.
- Cons: Resolver classes cannot express per-field authentication with `#[Logged]`, and cannot access the current user with `#[InjectUser]`.
- Rejected: Applications need both endpoint-level gates and resolver-level authentication.

## Consequences
- Resolver authors can use GraphQLite authentication features with Cake Authentication identity data.
- The bridge returns the identity's original user object when available, otherwise the identity object itself.
- Identity state is mutable but request-scoped by middleware set/reset ordering.
- Cake Authorization remains outside this decision. GraphQLite authorization attributes such as `#[Right]` and `#[Security]` still require a separate authorization integration.
