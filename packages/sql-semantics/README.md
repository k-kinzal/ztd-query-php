# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

The semantic phase of a database front end: parse SQL with
[sql-parser](../sql-parser/), bind names against a schema, resolve expression
types, and derive conservative NULL facts and value provenance. The result is a
bound statement that later stages can plan or evaluate. No database connection
is required.

A table declaration and a use of that table are different objects. A self join
therefore has two relation identities, and an outer join can make one use of a
NOT NULL column nullable without changing the declaration.

## Installation

```bash
composer require k-kinzal/sql-semantics
```

Requires PHP 8.1+ and `k-kinzal/sql-parser`. Both public entry points accept SQL
strings; parsing is handled inside the package.

## Usage

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    parent_id INTEGER,
    score INTEGER NOT NULL
);
SQL);

$binder = new Binder($schema);
$statement = $binder->bind(<<<'SQL'
SELECT
    child.id,
    parent.score AS parent_score,
    COALESCE(parent.score, 0) AS effective_score
FROM users AS child
LEFT JOIN users AS parent ON child.parent_id = parent.id;
SQL);

$statement->relations[0]->id;                                             // r0
$statement->relations[1]->id;                                             // r1
$statement->outputs[1]->expression->type->name;                           // integer
$statement->outputs[1]->expression->nullability->value;                   // maybe-null
$statement->outputs[1]->expression->nullExtendedBy;                       // ['j0']
$statement->outputs[2]->expression->nullability->value;                   // not-null
$statement->outputs[2]->expression->lineage()[0]->relationId;             // r1
```

`SchemaBuilder::build(string ...$sql): Schema` constructs a reusable schema from
CREATE TABLE strings. `Binder::bind(string $sql): BoundSelect` performs semantic
binding of one SELECT against that schema. `Schema` contains declarations and
their language context; `BoundSelect` is the output of the semantic phase.

Use `Dialect::MySql` or `Dialect::Sqlite` for the other supported dialects.
`SchemaBuilder` accepts optional `defaultSchema` and `grammarVersion` arguments,
for example `new SchemaBuilder(Dialect::PostgreSql, 'app', 'pg-17.2')`. The schema
retains the resolved grammar release and default namespace, so the binder uses
the same settings. Semantic coverage is smaller than grammar coverage.

Call `build()` without arguments for a schema with no tables, such as when
binding `SELECT 1`. Each build creates a new schema; it does not apply migrations.
A binder can be reused for multiple SELECTs against the same schema.

The result is an immutable PHP object graph, not serialized SQL or YAML.
`Expression::source`, `TableUse::source`, and declaration sources retain the
original parser nodes or tokens, including their source locations. Binding
preserves that syntax tree. IDs are deterministic within each bound statement and restart for
each call to `bind()`. Syntax and lexical errors propagate from `sql-parser`;
semantic failures use `SemanticException`.

## What the result means

| Information | Representation |
| --- | --- |
| Schema snapshot | `Schema`, `TableDefinition`, ordered `ColumnDefinition` objects |
| Declared integrity | Primary/unique keys, foreign references, CHECK and default syntax |
| Names and scope | `TableUse`, `ColumnBinding`, query-local relation and scope IDs |
| Values | `Expression`, ordered operands, dialect `TypeDescriptor`, NULL facts |
| Value dependencies | `Expression::lineage()`, distinguished by relation occurrence |
| Row dependencies | Logical `Join` tree and its `condition`, then `BoundSelect::where` |
| Result shape | Ordered `OutputColumn` objects; duplicate names remain distinct |
| Result modifiers | DISTINCT, ordering, LIMIT, and OFFSET |
| Incomplete knowledge | `unknown` parameter types and `Nullability::Unknown` |
| Unsupported or invalid binding | `SemanticException` with a stable `reason` and original `source` |

`NotNull` describes successfully evaluated values; it does not promise that
execution cannot fail. `MaybeNull` is conservative, not a prediction that a NULL
will occur. A WHERE predicate is retained but does not currently refine output
nullability. SQLite's declared types carry a separate `affinity`; these are not
runtime storage-class guarantees.

## Supported surface

The initial implementation handles ordinary CREATE TABLE declarations and
single-scope SELECTs over named tables, including aliases, schema qualification,
self joins, cross/inner/left/right joins, PostgreSQL/SQLite full joins, ON and
WHERE predicates, star expansion, DISTINCT, ordering, and pagination. Scalar
models cover column references, decimal literals, parameters, integer arithmetic
(`+`, `-`, `*`), comparisons, boolean operations, NULL tests, COALESCE, and NULLIF
with compatible input types. PostgreSQL COALESCE conversions are explicit cast
nodes in the semantic graph.

This is a bounded semantic implementation, not a full database binder. CTEs,
subqueries, set operations, grouping/aggregates, window functions, USING/NATURAL
joins, explicit casts, collations, arbitrary functions, generated columns,
DDL schema evolution, and DML are rejected rather than silently omitted. See
[the support contract](docs/support.md) for dialect assumptions and exact limits.

## Consumers

A fixture generator can use declaration constraints to construct candidate rows,
then follow the join tree and predicates to choose matching and nonmatching
rows. Value lineage explains which occurrence must supply each output. It must
still solve the predicates and validate generated results; this package does not
synthesize data or execute queries.

A SQL catalog can enrich a recovered statement with output types, declarations,
relation occurrences, and source spans. It should retain semantic failures as
findings. This package does not depend on the unmerged `sql-catalog` package or
change `sql-fixture` behavior.

## Development

```bash
composer install
composer lint
composer test
composer bench:quick
```

PHP-AI-Toolkit supplies the PHPUnit AI reporter, executable PHPDoc examples,
PHPStan rules, LOC/directory guards, and documentation generator. PHP-CS-Fixer,
PHPCompatibility, Deptrac, ParaTest, and PHPBench follow the other packages'
conventions.

- [Semantic design and evaluation requirements](docs/design.md)
- [Supported language and conservative facts](docs/support.md)
- [Verification](docs/verification.md)

## License

MIT. See [LICENSE](LICENSE).
