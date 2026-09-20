# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

Resolve [sql-parser](../sql-parser/) syntax trees against table declarations and
return a logical query with bound columns, database types, conservative NULL
facts, and occurrence-aware value lineage. No database connection is required.

A table declaration and a use of that table are different objects. A self join
therefore has two relation identities, and an outer join can make one use of a
NOT NULL column nullable without changing the declaration.

## Installation

```bash
composer require k-kinzal/sql-semantics
```

Requires PHP 8.1+ and `k-kinzal/sql-parser`. Select the parser's dialect explicitly;
the analyzer checks parser roots and catalog dialects.

## Usage

```php
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Analyzer;
use SqlSemantics\Dialect;

$parser = new PostgreSqlParser();
$analyzer = new Analyzer(Dialect::PostgreSql);
$catalog = $analyzer->schema($parser->parse(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    parent_id INTEGER,
    score INTEGER NOT NULL
);
SQL));

$query = $analyzer->analyze($parser->parse(<<<'SQL'
SELECT
    child.id,
    parent.score AS parent_score,
    COALESCE(parent.score, 0) AS effective_score
FROM users AS child
LEFT JOIN users AS parent ON child.parent_id = parent.id;
SQL), $catalog);

$query->relations[0]->id;                               // r0
$query->relations[1]->id;                               // r1
$query->outputs[1]->expression->type->name;               // integer
$query->outputs[1]->expression->nullability->value;       // maybe-null
$query->outputs[1]->expression->nullExtendedBy;           // ['j0']
$query->outputs[2]->expression->nullability->value;       // not-null
$query->outputs[2]->expression->lineage()[0]->relationId; // r1
```

Use `Dialect::MySql` with `MySqlParser`, or `Dialect::Sqlite` with `SqliteParser`,
for the same supported query shapes. The grammar version is selected on the
parser; semantic coverage is deliberately smaller than its grammar coverage.

The result is an immutable PHP object graph, not serialized SQL or YAML.
`Expression::source`, `TableUse::source`, and declaration sources retain the
original parser nodes or tokens, including their source locations. The input
syntax tree is never modified. IDs are deterministic within each analysis and
restart for a new query.

## What the result means

| Information | Representation |
| --- | --- |
| Schema snapshot | `Catalog`, `TableDefinition`, ordered `ColumnDefinition` objects |
| Declared integrity | Primary/unique keys, foreign references, CHECK and default syntax |
| Names and scope | `TableUse`, `ColumnBinding`, query-local relation and scope IDs |
| Values | `Expression`, ordered operands, dialect `TypeDescriptor`, NULL facts |
| Value dependencies | `Expression::lineage()`, distinguished by relation occurrence |
| Row dependencies | Logical `Join` tree and its `condition`, then `SelectQuery::where` |
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
composer fuzz:smoke
composer fuzz:semantics -- --max-runs=100
composer bench:quick
```

PHP-AI-Toolkit supplies the PHPUnit AI reporter, executable PHPDoc examples,
PHPStan rules, LOC/directory guards, and documentation generator. PHP-CS-Fixer,
PHPCompatibility, Deptrac, ParaTest, PHPBench, and PHP-Fuzzer follow the other
packages' conventions. Development property tests require `ext-pdo_sqlite`.

- [Semantic design and evaluation requirements](docs/design.md)
- [Supported language and conservative facts](docs/support.md)
- [Verification](docs/verification.md)

## License

MIT. See [LICENSE](LICENSE).
