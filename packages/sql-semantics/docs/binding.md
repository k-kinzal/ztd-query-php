# Schema Binding

`SqlSemantics\Facade\Schema` builds a schema from CREATE TABLE statements, and `SqlSemantics\Core\Binder` binds a SELECT against it. The result tells which table use and column each name refers to, the type of each value, whether it can be NULL, and which relation occurrences it comes from.

```php
use SqlSemantics\Core\Binder;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\PostgreSql\Dialect;

$schema = (new Schema(Dialect::PostgreSql))->analyze(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    parent_id INTEGER,
    score INTEGER NOT NULL
);
SQL);

$statement = (new Binder($schema))->bind(<<<'SQL'
SELECT
    child.id,
    parent.score AS parent_score,
    COALESCE(parent.score, 0) AS effective_score
FROM users AS child
LEFT JOIN users AS parent ON child.parent_id = parent.id;
SQL);

$statement->outputs[1]->expression->type->name;               // 'integer'
$statement->outputs[1]->expression->nullability->value;       // 'maybe-null', because of the LEFT JOIN
$statement->outputs[2]->expression->nullability->value;       // 'not-null'
$statement->outputs[2]->expression->lineage()[0]->relationId; // 'r1', the parent use of users
```

`Schema` takes the dialect, an optional default schema, and an optional version tag, for example `new Schema(Dialect::PostgreSql, 'app', 'pg-17.2')`. The binder uses the schema's dialect and version. `analyze()` without arguments gives an empty schema, and a binder can be reused for any number of SELECT statements. Binding with a MySQL 5.6 or 5.7 grammar is not supported.

## The result

| Information | Representation |
|-------------|----------------|
| Schema | `Schema`, `TableDefinition`, and ordered `ColumnDefinition` objects |
| Declared integrity | Primary and unique keys, foreign references, CHECK, and defaults |
| Names and scope | `TableUse` and `ColumnBinding`, with relation and scope IDs such as `r0` and `s0` |
| Values | `Expression` with ordered operands, a dialect `TypeDescriptor`, and NULL facts |
| Value sources | `Expression::lineage()`, by relation occurrence |
| Row sources | The `Join` tree and its conditions, then `BoundSelect::where` |
| Result shape | Ordered `OutputColumn` objects; duplicate names stay distinct |
| Result modifiers | DISTINCT, ORDER BY, LIMIT, and OFFSET |
| Unknown facts | `unknown` parameter types and `Nullability::Unknown` |

A table declaration and a use of it are different objects, so a self join has two relation IDs, and an outer join can make one use of a NOT NULL column nullable. `NotNull` describes values that were computed successfully; `MaybeNull` is conservative and does not predict a NULL. A WHERE condition does not narrow nullability. SQLite column types also carry their `affinity`. The state contains immutable semantic values, including generation expressions, column attributes, collations, and table options. The bound SELECT still keeps its parser nodes and tokens with source positions; IDs restart for each `bind()` call.

## Supported SELECT statements

Binding covers CREATE TABLE declarations and single-scope SELECT statements over named tables: aliases, schema qualification, self joins, cross, inner, left, and right joins, full joins in PostgreSQL and SQLite, ON and WHERE conditions, star expansion, DISTINCT, ORDER BY, LIMIT, and OFFSET. Expressions can be column references, literals, parameters, `+`, `-`, and `*` on integers, comparisons, boolean operators, NULL tests, COALESCE, and NULLIF.

CTEs, subqueries, set operations, grouping and aggregates, window functions, USING and NATURAL joins, casts, collations, other functions, and DML are rejected with `SqlSemantics\Core\SemanticException`, which carries a stable `reason` and the source it refers to. `Semantics::analyze()` still accepts all of them, since it needs no schema.

See [schema state](schema.md) for the declaration surface, state invariants, immutable updates, and migration from `SchemaBuilder`.
