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

The checked complete domain includes projections, joins, predicates, ordering, values, compound queries, selected table changes and transaction control. Table declarations include STRICT, WITHOUT ROWID with a resolved primary key, native INT and ANY types, literal defaults and per-column NOT NULL policies. Table declarations also cover ordered unique keys and explicitly named CHECK constraints over declared columns. Declarations also cover VIRTUAL and STORED generated columns with checked scalar expressions, including forward references to declared columns. The default search order gives temporary tables precedence over main.

A single INNER, CROSS, or LEFT join between two declared base tables can use USING or NATURAL. The IR resolves the comparison operands in left-to-right order, projects each merged name from the left occurrence, and keeps both inputs addressable by qualified names. The rendered SELECT uses explicit ON and projection clauses.

INSERT and UPDATE keep their explicit conflict strategy or inherit the target schema's policy. REPLACE is an INSERT with an explicit REPLACE strategy. Execution uses the target's constraint and trigger definitions. INSERT with VALUES or SELECT can record a targetless ON CONFLICT DO NOTHING action.

Concrete types are under `SqlSemantics\Platform\Sqlite\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
