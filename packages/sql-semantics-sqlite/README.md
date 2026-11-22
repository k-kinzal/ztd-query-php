# SQL Semantics for SQLite

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-sqlite

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE main.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM main.accounts', $context);

echo $ir->toString();
```

The checked complete domain includes projections, joins, predicates, ordering, values, compound queries, selected table changes and transaction control. Table declarations include STRICT, WITHOUT ROWID with a resolved primary key, native INT and ANY types, literal defaults and per-column NOT NULL policies. The default search order gives temporary tables precedence over main.

INSERT and UPDATE keep their explicit conflict strategy or inherit the target schema's policy. REPLACE is an INSERT with an explicit REPLACE strategy. Execution uses the target's constraint and trigger definitions. INSERT with VALUES or SELECT can record a targetless ON CONFLICT DO NOTHING action.

Concrete types are under `SqlSemantics\Platform\Sqlite\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
