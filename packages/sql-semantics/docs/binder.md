# Binder

Use `Binder` to read the meaning of a SQL statement against a [Schema](schema.md).
The returned object gives you selected values, referenced columns, row conditions,
write destinations, and settings without having to interpret SQL text yourself.

## Public interface

The following declarations show the public signatures. Method bodies are omitted.

```php
namespace SqlSemantics;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;

final class Binder
{
    public function __construct(public readonly Schema $schema) { /* ... */ }

    public function bind(string $sql, bool $strict = true): BoundStatement { /* ... */ }

    /** @return list<BoundStatement> */
    public function bindAll(string $sql, bool $strict = true): array { /* ... */ }

    public function replaceExpression(
        BoundStatement $statement,
        Expression $target,
        string $replacement,
    ): BoundStatement { /* ... */ }
}
```

Use `bind()` for one statement or `bindAll()` for several statements. Both use the
schema supplied to the constructor. A SELECT returns a `BoundSelect`, which has
the same properties as `BoundStatement`.

If a table or column cannot be resolved, or a known type rule is violated,
`bind()` and `bindAll()` raise `SemanticException` by default. Its `reason` identifies the
problem and `source` identifies the responsible SQL node or token. Invalid SQL
syntax raises the parser's lexical or syntax exception.

Pass `strict: false` to either method to retain semantic problems in each
returned statement's `diagnostics` instead of raising them. The result remains a
`BoundStatement`; see [unresolved references](#unresolved-references).
`replaceExpression()` returns a new statement after checking and rebinding an
expression replacement; see [editing expressions](#editing-expressions).

## Read a statement

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 0)',
    'CREATE TABLE incoming (id INTEGER, score INTEGER)',
);
$binder = new Binder($schema);
$statement = $binder->bind('SELECT id, score FROM users WHERE score > 0');

$statement->outputs[0]->expression->binding->column->name; // id
$statement->where->symbol;                               // >
$statement->where->operands[0]->binding->column->name;     // score
```

Start with the fields for the operation you are reading:

| Operation | Fields to read |
|-----------|----------------|
| SELECT | `outputs` for returned values; `from` for tables and joins; `where` for the row condition. `groupBy`, `having`, `orderBy`, `distinct`, `limit`, `offset`, and `withTies` describe grouping and result modifiers. |
| INSERT | `insertion` maps each input position to a target column; `rows` or `queries` supplies the input; `conflicts` describes conflict handling; `outputs` describes RETURNING. |
| UPDATE | `targets` identifies written tables; ordered `writes` gives destinations and values; `from` and `where` describe the inputs and row condition; `outputs` describes RETURNING. |
| DELETE | `targets` identifies deleted-from tables; `from` and `where` describe the inputs and row condition; `outputs` describes RETURNING. |
| MERGE | `merge` contains the target, input, matching condition, and ordered conditional actions. Each action owns its writes or inserted values. |
| SET, RESET, PRAGMA | `settings` contains ordered effects with `name`, `scope`, `action`, and expression `values`. |
| CREATE TABLE | `declarations` contains table definitions; `definitions` associates bound defaults, generated expressions, and CHECK conditions with those declarations. |
| CREATE INDEX | `indexes` contains each `definition`, typed `keys`, and a typed partial-index `predicate`; `targets` identifies the indexed table. |
| Nested statements | `ctes`, compound-query `branches`, relation or expression `query`, and nested `statements` preserve the enclosed operations and their conditions. |

## Read values and column references

Each output is an `OutputColumn` with a zero-based `ordinal`, a `name`, and an
`Expression`. Duplicate output names have separate positions. An expression gives
you its operation, operands, type, NULL fact, and referenced column where known.

```php
$expression = $statement->outputs[0]->expression;

$expression->kind->value;                // column
$expression->type->name;                 // integer
$expression->nullability->value;         // not-null
$expression->binding->relationId;        // r0
$expression->binding->table->name;       // users
$expression->binding->column->name;      // id
$expression->lineage()[0]->column->name; // id
```

`lineage()` returns the columns contributing values to the expression. For row
conditions, also read joins and WHERE, including those inside nested queries.
A self join has separate `TableUse` objects with different `id` values, even when
both refer to the same `TableDefinition`. An outer join can make a column use
nullable without changing its declared nullability; `nullExtendedBy` identifies
the responsible joins.

Function calls use the [signatures registered on the schema](schema.md#register-function-signatures).
Their result types, argument conversions, aggregate roles, and NULL facts are
available through the same expression properties. An inferred parameter type is
represented by an implicit cast around the original parameter expression.

## SQL and returned structures

The following examples use the PostgreSQL schema above unless a dialect is
specified. Each row is an independent call. Paths describe selected properties
on the returned `BoundStatement`.

### Queries

| SQL | Returned structure |
|-----|--------------------|
| `SELECT id, score FROM users WHERE score > 0` | `BoundSelect`; two outputs in SQL order; `outputs[0].expression.binding.column.name = 'id'`; `where.symbol = '>'` with operands bound to `score` and literal `0`. |
| `SELECT child.id, parent.score FROM users child LEFT JOIN users parent ON child.parent_id = parent.id` | Two relation occurrences with distinct IDs; a left `Join` with its own condition; the second output is `MaybeNull` and names the join in `nullExtendedBy`, while the declared `score` remains `NotNull`. |
| `SELECT COALESCE(parent_id, 0) AS parent FROM users` | Output `name = 'parent'`; expression kind `Coalesce`, two operands, and `nullability = NotNull`; `lineage()` includes the `parent_id` binding. |
| `SELECT parent_id, SUM(score) AS total FROM users GROUP BY parent_id HAVING SUM(score) > 0 ORDER BY total DESC LIMIT 5` | `groupBy[0]` binds `parent_id`; `having` retains the aggregate comparison; `outputs[1]` is an aggregate expression; `orderBy[0].descending = true`; `limit.symbol = '5'`. |
| `WITH positive AS (SELECT id FROM users WHERE score > 0) SELECT id FROM positive` | `ctes['positive']` retains its bound query and WHERE; the outer relation's `query` exposes that CTE definition and its output declaration. |
| `SELECT id FROM users UNION ALL SELECT id FROM incoming` | Ordered `branches` retain both SELECTs; `setOperator = 'UNION ALL'`; outer outputs represent the compound result. |
| `SELECT id FROM users WHERE EXISTS (SELECT 1 FROM incoming WHERE incoming.id = users.id)` | The WHERE subquery expression has `symbol = 'EXISTS'` and a `query`; its inner predicate retains both the local and correlated column bindings. |
| `SELECT score, score FROM users` | Two distinct output positions, even though their names and dependencies are the same. |

### Writes

| SQL | Returned structure |
|-----|--------------------|
| `INSERT INTO users(score,id) VALUES(10,1)` | `insertion.target` refers to `users`; destination columns are `score`, `id` in that order; `rows[0]` contains literal `10`, `1`; `omittedColumns` includes `parent_id`. |
| `INSERT INTO users(id,score) VALUES(1,DEFAULT) RETURNING id` | Ordered destinations `id`, `score`; the second value has kind `DefaultValue`; `outputs[0]` binds the returned `id`. |
| `INSERT INTO users(id) SELECT id FROM incoming` | `insertion.columns[0]` binds `users.id`; `queries[0]` retains the source SELECT and its `incoming.id` output; other columns are omitted destinations. |
| `UPDATE users SET score = score + 1 WHERE id = 1 RETURNING score` | `targets` identifies `users`; `writes[0].targets[0]` binds `score`; `writes[0].value` is the addition expression; `where` binds `id = 1`; `outputs` retains RETURNING. |
| `UPDATE users SET score = incoming.score FROM incoming WHERE users.id = incoming.id` | Both tables occur in the input graph; only `users` is a write target. The assignment reads `incoming.score` and writes `users.score`. |
| `DELETE FROM users USING incoming WHERE users.id = incoming.id RETURNING users.id` | `targets` contains `users`; `incoming` is read-only input; `where` retains the matching predicate; `outputs` binds the deleted table's `id`. |
| `INSERT INTO users(id) VALUES(1) ON CONFLICT(id) DO UPDATE SET score = 2 WHERE users.score < 2` | `conflicts[0].action = 'update'`; `keys` binds `id`; the conflict assignment writes `score`; its predicate is `conflicts[0].where`, separate from statement `where`. |
| `MERGE INTO users USING incoming ON users.id = incoming.id WHEN MATCHED THEN UPDATE SET score = incoming.score WHEN NOT MATCHED THEN INSERT(id,score) VALUES(incoming.id,incoming.score)` | `merge.target` is `users`; `input` is `incoming`; `condition` is the match predicate; ordered actions carry the matched UPDATE and unmatched INSERT with their own storage mappings. |
| `UPDATE users SET score = 1 WHERE CURRENT OF cursor_name` | `where.kind = CurrentRow`; `reference = ['cursor_name']`; its boolean fact represents a cursor-position predicate. |

### Settings

MySQL and SQLite rows use binders constructed with the indicated dialect and an
empty schema. PostgreSQL settings also work with an empty schema.

| Dialect and SQL | Returned structure |
|-----------------|--------------------|
| PostgreSQL: `SET LOCAL work_mem = '64MB'` | `settings[0].name = ['work_mem']`; `scope = 'local'`; `action = 'set'`; `values[0]` retains the string literal. |
| PostgreSQL: `SET search_path TO DEFAULT` | A session setting with a `DefaultValue` expression. |
| PostgreSQL: `RESET ALL` | `name = ['*']`; `action = 'reset'`; no assigned values. |
| PostgreSQL: `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE` | A transaction setting named `transaction_isolation` with a structured configuration value. |
| MySQL: `SET SESSION sql_mode = 'ANSI'` | `name = ['sql_mode']`; `scope = 'session'`; `action = 'set'`; a string value is retained. |
| MySQL: `SET @threshold = 10` | A user-variable setting with value `10` and `scope = 'user'`. |
| SQLite: `PRAGMA main.cache_size = 100` | `name = ['main', 'cache_size']`; `action = 'set'`; `values[0].symbol = '100'`. |
| SQLite: `PRAGMA main.cache_size` | The same qualified setting name with `action = 'read'` and an empty value list. |

### Declarations and nested commands

| SQL | Returned structure |
|-----|--------------------|
| `CREATE TABLE totals (amount INTEGER DEFAULT 3, doubled INTEGER GENERATED ALWAYS AS (amount * 2) STORED, CHECK (amount >= 0))` | `declarations[0]` describes the table; `definitions[0].defaults['amount']` is literal `3`; `generated['doubled']` binds `amount * 2`; `checks` retains the bound CHECK predicate by constraint position. |
| `CREATE INDEX positive_scores ON users((score + 1)) WHERE score > 0` | `indexes[0].definition.name = 'positive_scores'`; `keys[0]` is an integer addition with lineage to `users.score`; `predicate` is the boolean comparison; `targets[0]` identifies `users`. |
| `EXPLAIN UPDATE users SET score = 1 WHERE id = 2` | `statements[0]` is the bound UPDATE, with its write target, assignment, and row condition; the wrapper retains EXPLAIN syntax. |
| `PREPARE read_user AS SELECT id FROM users WHERE id = 1` | `statements[0]` retains the prepared SELECT and its filter; its bound query is also reachable in `queries`. |

Binding DDL describes the statement; it does not mutate the binder's schema.
Build a new schema snapshot when subsequent binding must use changed declarations.

## Unresolved references

Use `bind($sql, strict: false)` to read SQL with missing definitions or semantic
errors. The returned statement contains `diagnostics` alongside its ordinary
properties. Each `Diagnostic` has `reason`, `message`, and `source`. Known
bindings remain available and unresolved references are marked explicitly.

```php
$unresolved = $binder->bind('SELECT id, missing FROM users', strict: false);

$unresolved->outputs[0]->expression->binding->column->name; // id
$unresolved->outputs[1]->expression->reference;             // ['missing']
$unresolved->diagnostics[0]->reason;                                  // unknown-column
```

| SQL and schema | Returned structure and diagnostics |
|----------------|------------------------------------|
| Empty schema; `SELECT t.id, t.* FROM missing t` | The relation has `declaration.resolved = false`; `t.id` is `UnresolvedColumn` with `reference = ['t', 'id']`; `t.*` is `Wildcard` with qualifier `['t']`; diagnostics include `unknown-table`. |
| The example schema; `INSERT INTO users(missing) VALUES(1)` | The insertion retains its unresolved destination and input value; diagnostics include `unknown-column`. |
| PostgreSQL example schema; `DELETE FROM users WHERE 1` | The numeric predicate remains in `where`; diagnostics include `non-boolean-predicate`. |

A wildcard whose table definition is missing has an unknown output width. It is
not one resolved result column. Invalid SQL syntax and internal failures still
raise exceptions. Diagnostics include problems in nested queries and commands.

`bindAll($sql, strict: false)` collects diagnostics separately for each returned
statement. They do not carry over to the next statement or a later binder call.
With the default `strict: true`, a successful result has an empty diagnostic list.

## Editing expressions

```php
$original = $binder->bind('SELECT score*2 FROM users');
$edited = $binder->replaceExpression(
    $original,
    $original->outputs[0]->expression->operands[0],
    'score+1',
);
$edited->toString(); // "SELECT (\nscore+1\n)*2 FROM users"
$original->toString(); // SELECT score*2 FROM users
```

Parentheses preserve precedence. The target expression must belong to the
statement being edited. The replacement must be one expression, and its names
and types are checked against the binder's schema. The original statement is
unchanged.

## Return to SQL

Call `toString()` on a bound statement to obtain its SQL:

```php
$statement->toString(); // SELECT id, score FROM users WHERE score > 0
```

`BoundStatement::toString()` preserves the original SQL's whitespace and
comments. It is available on every result, including statements with diagnostics
and statements returned by expression replacement.
