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

$statement->outputs[1]->expression->type->name;               // Builtin::Integer
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
| Values | `Expression` with ordered operands, an `Operator` for operator expressions, a dialect `TypeDescriptor`, and NULL facts |
| Value sources | `Expression::lineage()`, by relation occurrence |
| Row sources | The `Join` tree and its conditions, then `BoundSelect::where` |
| Result shape | Ordered `OutputColumn` objects; duplicate names stay distinct |
| Result modifiers | DISTINCT, ORDER BY, LIMIT, and OFFSET |
| Types | `TypeDescriptor`: a `Builtin` case or a `TypeName`, with length, precision, scale, sign, character set, array and affinity facts |
| Unknown facts | `Builtin::Unknown` parameter and NULL types and `Nullability::Unknown` |

A table declaration and a use of it are different objects, so a self join has two relation IDs, and an outer join can make one use of a NOT NULL column nullable. `NotNull` describes values that were computed successfully; `MaybeNull` is conservative and does not predict a NULL. A WHERE condition does not narrow nullability. The state contains immutable semantic values, including generation expressions, column attributes, collations, and table options. The bound SELECT still keeps its parser nodes and tokens with source positions; IDs restart for each `bind()` call.

Every closed set in the result is an enum: `Builtin` for type identities, `Operator` for operators, `ExpressionKind`, `JoinKind`, `ConstraintKind`, `GenerationKind`, `Nullability`, and `Affinity`. Strings appear only for what a declaration chooses freely: table, column, character set and constraint names, and the spelling of a literal or parameter. See [types](#types) below.

## Types

`TypeDescriptor` separates the identity of a type from the facts declared around it. The identity is a `Builtin` case, spelled canonically, or a `TypeName` for a user-defined, domain, or unmodeled type, whose decoded name parts are kept. Each dialect maps its synonyms onto the same cases: `INT1`, `MIDDLEINT`, `INT8` and `FLOAT8` become `Builtin::TinyInt`, `MediumInt`, `BigInt` and `DoublePrecision`; PostgreSQL catalog names such as `int4`, `timestamptz` and `varbit` resolve the same way as their keyword spellings; SQLite maps the names its documentation lists and keeps other names, such as `UNSIGNED BIG INT`, as a `TypeName` with their affinity.

The other declared facts are independent typed fields, never part of the name:

| Fact | Field | Example |
|------|-------|---------|
| Length or display width | `length` (int) | `VARCHAR(10)`, `INT(11)`, `BIT(8)` |
| Precision and scale | `precision`, `scale` (int) | `NUMERIC(2,-3)`, `TIME(6)`, `FLOAT(24)` |
| Sign and padding | `unsigned`, `zerofill` (bool) | `INT UNSIGNED ZEROFILL` |
| Character set and collation | `characterSet` (string), `binaryCollation` (bool) | `VARCHAR(10) CHARSET utf8mb4 BINARY` |
| Enumeration members | `members` (typed literals) | `ENUM('a','b')` |
| Array dimensions | `arrayDimensions` (int) | `INTEGER[][]` |
| Interval fields | `intervalFields` (`IntervalFields`) | `INTERVAL DAY TO SECOND(3)` |
| Storage affinity | `affinity` (`Affinity`) | SQLite `VARCHAR(20)` has `Affinity::Text` |

A `TypeDescriptor` is always a valid state: a `Builtin` must be one the dialect supports, `zerofill` implies `unsigned`, a scale requires a precision, only numeric types carry a sign, only character types carry a character set, and exactly the enumeration types have members. Invalid construction throws `InvalidArgumentException`. `is()` compares the identity alone, so `VARCHAR(10)` and `VARCHAR(20)` are the same type with different lengths.

Type spellings that stand for column properties report them separately as a `TypeDeclaration`: MySQL `SERIAL` declares an unsigned `BigInt` that is `autoIncrement`, NOT NULL and unique; PostgreSQL `SERIAL`, `BIGSERIAL` and `SMALLSERIAL` declare an `autoIncrement`, NOT NULL integer. A MySQL column attribute `KEY` declares the primary key. Three cases are not declared types: `Builtin::Unknown` is a NULL literal or parameter the binder has not resolved, `Builtin::Dynamic` is a SQLite value whose storage class is decided at run time, and `Builtin::Any` is the SQLite declared type that accepts every storage class.

## Supported SELECT statements

Binding covers CREATE TABLE declarations and single-scope SELECT statements over named tables: aliases, schema qualification, self joins, cross, inner, left, and right joins, full joins in PostgreSQL and SQLite, ON and WHERE conditions, star expansion, DISTINCT, ORDER BY, LIMIT, and OFFSET. Expressions can be column references, literals, parameters, `+`, `-`, and `*` on integers, comparisons, boolean operators, NULL tests, COALESCE, and NULLIF.

CTEs, subqueries, set operations, grouping and aggregates, window functions, USING and NATURAL joins, casts, collations, other functions, and DML are rejected with `SqlSemantics\Core\SemanticException`, which carries a stable `reason` and the source it refers to. `Semantics::analyze()` still accepts all of them, since it needs no schema.

See [schema state](schema.md) for the declaration surface, state invariants, immutable updates, and migration from `SchemaBuilder`.
