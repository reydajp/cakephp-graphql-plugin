# Bake GraphQL Query Command

CakeGraphQL registers a three-word CakePHP command:

```bash
bin/cake bake graphql query <Name>
```

## Collection Query

```bash
bin/cake bake graphql query Users
```

Generates:

```text
src/Graphql/UsersQuery.php
```

The generated method returns a list:

```php
#[Query]
public function users(): array
{
    return $this->fetchTable('Users')->find()->all()->toList();
}
```

## Single-Record Query

```bash
bin/cake bake graphql query Users --single
```

The generated method accepts a GraphQL `ID!` argument:

```php
#[Query]
public function user(#[UseInputType('ID!')] string $id): mixed
{
    return $this->fetchTable('Users')->get($id);
}
```

## Custom Namespace

```bash
bin/cake bake graphql query Users --namespace App\\Api\\Graphql
```

Generates:

```text
src/Api/Graphql/UsersQuery.php
```

## Config Updates

By default, the command adds the generated class to:

```php
Graphql.engines.Graphqlite.queries
```

The updater looks for `config/app_local.php` first and then `config/app.php`. It avoids duplicate entries.

Use `--no-config` to generate the resolver without editing configuration:

```bash
bin/cake bake graphql query Users --no-config
```

If config update fails after file generation, the generated resolver is removed and the command exits with an error.

## Overwrites

The command does not overwrite existing resolver files.
