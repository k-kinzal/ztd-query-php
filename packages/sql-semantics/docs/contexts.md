# Catalogs and name resolution

Pass the dialect analyzer a catalog containing the relation definitions needed to bind a statement. Supply definition SQL in dependency order.

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$analyzer = new Semantics();
$context = $analyzer->context([
    'CREATE TABLE main.accounts (id INTEGER, balance INTEGER)',
    'CREATE TEMP TABLE accounts (id INTEGER, balance INTEGER)',
]);
$result = $analyzer->analyze('SELECT id FROM accounts', $context);
```

The SQLite search order resolves the unqualified source to `temp.accounts`. Write `main.accounts` to address the other table. The returned IR stores the resolved schema, table and column slots.

`context()` creates an open catalog. With an explicit list the default is a complete catalog. Call `context($definitions, complete: false)` to declare part of the catalog. An unresolved reference cannot produce a complete statement. Use the same dialect and language profile to build the catalog and analyze each statement.

Catalog preparation binds each definition and extracts its declared relations. A definition is rejected if it supplies no relation or has a semantic error. The context is an input to analysis. A returned statement captures its own resolved identities and references.

The SQLite packages use ASCII case-insensitive identifier lookup. PostgreSQL and MySQL follow their dialect profile and selected schema search conditions. See each dialect package for version and mode details.
