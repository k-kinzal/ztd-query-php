# Binder

`Binder` turns SQL into an immutable `BoundStatement` using an explicit [Schema](schema.md).
It resolves names, assigns expression types and NULL facts, and describes query inputs,
write effects, declarations, and settings. It describes operations for a consumer to
interpret; it does not execute them or change the supplied schema.

## Bind a statement

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 0)',
    'CREATE TABLE incoming (id INTEGER, score INTEGER)',
);
$statement = (new Binder($schema))->bind(
    'SELECT id, score + 1 AS next_score FROM users WHERE score > 0',
);

$statement->outputs[0]->expression->binding->column->name; // id
$statement->outputs[1]->name;                              // next_score
$statement->outputs[1]->expression->kind->value;           // operator
$statement->outputs[1]->expression->type->name;            // integer
$statement->outputs[1]->expression->nullability->value;   // not-null
$statement->outputs[1]->expression->operands[0]->binding->column->name; // score
$statement->where->symbol;                               // >
```

`bind(string $sql, bool $strict = true): BoundStatement` reads exactly one statement.
`bindAll(string $sql, bool $strict = true): array` returns statements in source order.
The constructor takes a `Schema`. Every call, including every element of `bindAll`,
uses that same snapshot. To interpret a sequence against changing definitions, build
the next schema snapshot explicitly and use it for the next binder.

Successful strict binding returns an empty `diagnostics` list. An unresolved name or
an incompatible known type raises `SemanticException`, with a `reason` and the
responsible `source` node or token. `strict: false` instead retains those facts and
diagnostics in the returned statement. Lexical and syntax errors always raise the
corresponding sql-parser exception.

## Statement types and fields

All concrete types extend `BoundStatement`. Query types additionally extend
`BoundQuery`. `BoundSelect` is in `SqlSemantics\Model`; the other concrete types below
are in `SqlSemantics\Model\Statement`.

| SQL operation | Returned type | Structured information |
|---------------|---------------|------------------------|
| SELECT | `BoundSelect` | Ordered `outputs`; `from` relation and join tree; `where`; `groupBy`, `having`, `distinct`, `orderBy`, `limit`, `offset`, `withTies`. |
| VALUES | `ValuesStatement` | Ordered `rows`, corresponding typed `outputs`, and query modifiers. |
| TABLE | `TableStatement` | Referenced relation and its ordered declared output columns. |
| UNION, INTERSECT, EXCEPT | `CompoundStatement` | Ordered `branches`, `setOperator` including ALL, and common output types by ordinal. |
| INSERT, REPLACE | `InsertStatement` | `insertion` maps positions to destination columns; `rows` or `queries` supplies values; `conflicts` describes conflict actions; `outputs` describes RETURNING. |
| UPDATE | `UpdateStatement` | Written `targets`, ordered `writes` with destination expressions and assigned values, input `from`, row `where`, and RETURNING `outputs`. |
| DELETE | `DeleteStatement` | Deleted-from `targets`, input `from`, row `where`, and RETURNING `outputs`. |
| MERGE | `MergeStatement` | `merge.target`, `input`, matching `condition`, and ordered actions with their own conditions, assignments, or insertion mapping and rows. |
| SET, RESET, PRAGMA | `ConfigurationStatement` | Ordered `settings`: identifier-part `name`, `scope`, `action`, and expression `values`. |
| CREATE TABLE and other table-producing declarations | `CreateTableStatement` | `declarations` contains table definitions; `definitions` associates typed defaults, generated values, and CHECK conditions with them. |
| CREATE INDEX | `CreateIndexStatement` | `indexes` contains storage definitions, typed ordered `keys`, and partial-index `predicate`; `targets` identifies the indexed table. |
| Other language commands | `CommandStatement` | `kind`, complete SQL `sql` structure, affected `targets`, embedded `queries`, and nested `statements`, as applicable to the operation. |

`relations` lists each visible table occurrence; `targets` lists affected occurrences.
`TableUse` identifies its declaration, alias, `id`, and `scopeId`. Self joins retain
separate occurrence identities. `Join` has a kind, left and right inputs, and a match
condition. A derived relation's `query`, a scalar expression's `query`, `ctes`, compound
`branches`, and command `statements` retain nested stages rather than flattening them.
Table functions and aliased joins expose their derived output metadata in a relation query.

The common `sql` field is a complete immutable SQL structure. It preserves components
specific to the selected grammar, including options and clauses beyond the convenience
fields above. `syntaxClauses` groups original clause nodes by grammar name. `source`
retains the parser tree and source positions for diagnostics. See
[statements and serialization](statements.md) for transformations and SQL generation.

## Output values and expression facts

`outputs` is a list of `OutputColumn` objects in result order. Each has a zero-based
`ordinal`, a `name` (explicit alias or resolved column name; otherwise `null`), and an
`Expression`. Repeated names retain separate positions. A wildcard expands against the
visible declarations, including the merged output columns of USING and NATURAL joins.

| Expression field | Meaning |
|------------------|---------|
| `kind` | Semantic operation, such as `Column`, `Literal`, `Operator`, `Function`, `Aggregate`, `Cast`, `Subquery`, `DefaultValue`, or `UnresolvedColumn`. |
| `operands` | Ordered input expressions. Casts retain their input; an inferred conversion is a `Cast` with `symbol = 'implicit'`. |
| `symbol` | Operator or function name, parameter identifier, or literal SQL spelling, according to the kind. Literal spellings retain SQL precision and quoting. |
| `type` | `TypeDescriptor`: database `dialect`, canonical `name`, declared `modifiers`, and SQLite `affinity` when applicable. `unknown` explicitly represents unresolved type information. |
| `nullability` | A conservative structural fact: `NotNull`, `MaybeNull`, `AlwaysNull`, or `Unknown`. |
| `binding` | For a resolved column, its relation occurrence ID, table declaration, and column declaration. |
| `nullExtendedBy` | Join occurrence IDs that can introduce NULL at this use of the value. |
| `reference` | Identifier parts for an unresolved reference, wildcard qualifier, or cursor reference. |
| `query` | The bound query for scalar, EXISTS, or membership subquery operations. |
| `lineage()` | Unique contributing column bindings in encounter order, preserving distinct relation occurrences. |
| `sql`, `source` | Owned SQL structure for serialization, and original syntax for diagnostics, respectively. |

A selected column takes its declared type and declaration-level NULL fact, adjusted for
outer joins at that occurrence. Operators derive result types and NULL facts from their
operands and dialect rules. Function and aggregate results use the
[registered signatures](schema.md#register-function-signatures), including argument
conversions and NULL propagation. Compound outputs combine corresponding branch types.
Parameters and unresolved inputs retain unknown facts until the surrounding operation
provides a known type. SQLite affinity describes type preference, not a computed runtime value.

These facts describe expressions at their relational evaluation stage. Predicates remain
separate expression trees in join conditions, `where`, `having`, and conditional writes.
A consumer combines those predicates with the expression facts when performing constraint
solving, fixture generation, or evaluation. Value lineage follows contributing values;
row dependencies also include predicates and query inputs.

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
| `SELECT id FROM users UNION ALL SELECT id FROM incoming` | `CompoundStatement`; ordered `branches` retain both SELECTs; `setOperator = 'UNION ALL'`; outer outputs represent the compound result. |
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

## Transform and serialize

Transformations belong to the returned Statement. They return a new instance, validate
its complete structure against the original schema snapshot, and recompute dependent
facts. `Binder` remains the SQL-to-Statement entry point. The original statement and
schema remain unchanged.

`SimpleSerializer::serialize($statement)` and `$statement->toString()` write the same
compact SQL layout from `sql`. See [statements and serialization](statements.md) for the
transformation methods, construction API, and formatting contract.
