# ADR-003: Keep Authentication at the Endpoint Boundary

## Status

Accepted

## Date

2026-05-22

## Context

GraphQL applications usually need two different authorization layers:

- Endpoint access: whether the request may execute GraphQL at all.
- Field or resolver authorization: whether the current identity may access a specific object, field, or operation.

CakePHP applications commonly use Authentication middleware to attach an `identity` request attribute. GraphQLite also has attributes such as `#[Logged]`, `#[Right]`, and `#[Security]` for resolver-level rules.

CakeGraphQL needs to offer a simple endpoint protection default without taking ownership of application-specific authorization policy.

## Decision

Keep CakeGraphQL authentication at the endpoint boundary only.

When `Graphql.authenticated` is `true`, `GraphqlAuthenticationMiddleware` rejects requests without an `identity` attribute before GraphQL execution. Field-level authorization stays in host application resolvers and GraphQLite attributes.

`authenticated` defaults to `true`.

## Alternatives Considered

### Leave all authentication to the host application

- Pros: The plugin would avoid auth concerns entirely.
- Cons: A newly configured endpoint could be accidentally exposed if the host app forgets route protection.
- Rejected: Endpoint protection is a useful default for a GraphQL endpoint.

### Implement field-level authorization in CakeGraphQL

- Pros: One authorization system inside the plugin.
- Cons: Authorization rules are application-specific and GraphQLite already provides resolver-level security attributes.
- Rejected: The plugin should not duplicate or constrain engine-specific authorization systems.

## Consequences

- Host applications must run their Authentication middleware before the GraphQL route middleware when endpoint auth is enabled.
- Public GraphQL endpoints can opt out with `authenticated => false`.
- Resolver and field authorization remain explicit in the host application.
- The plugin can reject unauthenticated requests before schema execution work begins.
