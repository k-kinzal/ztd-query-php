# Binder

`Binder` turns SQL into immutable semantic objects using a [Schema](schema.md).
The result describes what a query reads, what a mutation writes, which conditions
apply, and which configuration effects are requested. Original parser nodes
remain attached, and a statement parsed from SQL can be written back as SQL.

## Public interface

The entry points are in `SqlSemantics`. Result classes are in
`SqlSemantics\Model`, unless a subnamespace is shown.

| Interface | Return value and behavior |
|-----------|---------------------------|
| `new Binder(Schema $schema)` | Reusable binder using the snapshot's dialect, grammar release, and default namespace. `schema` is publicly readable. |
| `bind(string $sql): BoundStatement` | Binds one statement. A SELECT returns the `BoundSelect` subtype. Known name/type errors raise `SemanticException`. |
| `analyze(string $sql): Analysis` | Returns `statement: BoundStatement` and `diagnostics: list<Diagnostic>`. Unresolved names retain their qualified references and unknown facts; diagnostics accompany the graph. |
| `bindAll(string $sql): list<BoundStatement>` | Binds a script accepted by the selected parser, preserving statement order and boundaries. Each statement uses the binder's schema snapshot. |
| `replaceExpression(BoundStatement $statement, Expression $target, string $replacement): BoundStatement` | Replaces an expression owned by the statement, parses the expression boundary, and strictly rebinds the result into a new snapshot. |
| `BoundStatement::toSql(): string` | Writes the retained syntax, preserving the original SQL bytes for a statement returned by `bind()` or `analyze()`. |
| `Expression::lineage(): list<ColumnBinding>` | Unique value dependencies in encounter order, distinguished by relation occurrence and column. Row dependencies require following the query graph too. |
| `SemanticException::$reason`, `$source` | Stable diagnostic category and original syntax location. |

`bind()` and `analyze()` use the same semantic lowering. Lexical errors, syntax
errors, and internal failures propagate; `analyze()` does not convert them into
successful results. Unknown function signatures or runtime values can remain
unknown even after strict binding. See [limitations](limitation.md).

## Statement structure

| Public fields on `BoundStatement` | Representation and meaning |
|----------------------------------|----------------------------|
| `kind`, `scopeId`, `source` | Statement operation, scope identity, and complete original syntax. |
| `from`, `relations` | Logical input as `TableUse`, `Join`, or `null`; ordered relation occurrences. A self join has separate occurrences of the same declaration. |
| `outputs` | Ordered `OutputColumn` objects with `ordinal`, `name`, and `expression`; duplicate names remain separate. |
| `where`, `groupBy`, `having` | Row filter, grouping expressions, and group filter. Join conditions remain on the `Join` objects. |
| `distinct`, `orderBy`, `limit`, `offset`, `withTies` | Result modifiers; ordering keys and pagination values remain expressions. |
| `ctes`, `branches`, `setOperator` | Named CTE statements, compound-query branches, and set operation, including `ALL`. |
| `queries`, `statements` | Input SELECTs and nested command boundaries. For example, EXPLAIN retains its explained command in `statements`. |
| `targets`, `insertion`, `writes`, `rows`, `conflicts`, `merge` | Affected relations, ordered storage destinations and values, conflict actions, and MERGE decisions. |
| `settings` | Ordered `Configuration\Setting` effects: qualified `name`, `scope`, `action`, expression `values`, `source`, and `ifExists`. |
| `declarations`, `definitions` | Table declarations and `Definition\TableDeclaration` objects whose defaults, generated values, and CHECK conditions are bound expressions. |
| `clauses`, `syntaxClauses` | Additional clause expressions and original clause nodes, keyed by grammar production name. |
| `assignments` | Compatibility view of assigned values indexed by destination name. Use `writes` for ordered assignments, duplicate destinations, and tuples. |

| Related object | Public fields and interpretation |
|----------------|----------------------------------|
| `Analysis` | `statement` and ordered `diagnostics`. |
| `Diagnostic` | Machine-readable `reason`, explanatory `message`, and original `source` node or token. |
| `BoundSelect` | SELECT-specific subtype of `BoundStatement`, with the same public fields. |
| `OutputColumn` | Zero-based `ordinal`, nullable `name`, and `expression`. A null name means an engine-generated result label. |
| `TableUse` | `id`, `scopeId`, `declaration`, `alias`, `source`, `query`. The optional query describes a derived relation or CTE. |
| `Join` | `id`, `kind`, `left`, `right`, `condition`, `source`. The tree preserves join order and the ON evaluation stage. |
| `JoinKind` | `Cross`, `Inner`, `Left`, `Right`, `Full`; backed values are lowercase. |
| `Expression` | `kind`, `type`, `nullability`, `source`, ordered `operands`, optional `binding`, `symbol`, `nullExtendedBy`, `query`, and `reference`. |
| `ExpressionKind` | `Column`, `UnresolvedColumn`, `Wildcard`, `Literal`, `Parameter`, `Operator`, `Coalesce`, `NullIf`, `Cast`, `Function`, `Aggregate`, `Window`, `CaseExpression`, `Subquery`, `Row`, `DefaultValue`, `Subscript`, `Field`, `CurrentRow`, `ConfigurationValue`. |
| `ColumnBinding` | `relationId`, `table`, and `column`: occurrence identity and the referenced declaration. |
| `Ordering` | `expression`, `descending`, and `nullsFirst` (null means dialect default). |
| `Write\Insertion` | `target`, ordered `columns`, `explicitColumns`, `defaultValues`, `omittedColumns`. Input position maps to destination position. |
| `Write\Assignment` | Ordered `targets`, one `value` expression, and `source`; the value can be scalar, row, subquery, or DEFAULT. |
| `Write\ConflictAction` | `action`, `keys`, `constraint`, `indexPredicate`, ordered `assignments`, `where`, `source`. Conflict-update predicates are separate from the outer statement's WHERE. |
| `Write\Merge` | `target`, read-only `input`, matching `condition`, and ordered `actions`. |
| `Write\MergeAction` | Match category, action, optional branch `condition`, `assignments`, `insertion`, `rows`, and `source`. Effects belong to their branch. |
| `Definition\TableDeclaration` | `table`, `defaults` and `generated` indexed by column name, and `checks` indexed by constraint position. Values are bound expressions. |

Scope, relation, and join IDs are deterministic within a statement; nested graphs
share the allocator. They are not persistent IDs across independent statements.
`Nullability` describes successfully evaluated values. An outer join can make an
occurrence nullable while leaving its declaration unchanged.

## SQL and returned structures

The PostgreSQL examples below use this catalog unless a row says otherwise:

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 0)',
    'CREATE TABLE incoming (id INTEGER, score INTEGER)',
);
$binder = new Binder($schema);
```

Table paths are relative to the returned statement and show selected fields,
not a serialization format. Each row is bound independently.

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
| `EXPLAIN UPDATE users SET score = 1 WHERE id = 2` | `statements[0]` is the bound UPDATE, with its write target, assignment, and row condition; the wrapper retains EXPLAIN syntax. |
| `PREPARE read_user AS SELECT id FROM users WHERE id = 1` | `statements[0]` retains the prepared SELECT and its filter; its bound query is also reachable in `queries`. |

Binding DDL describes the statement; it does not mutate the binder's schema.
Build a new schema snapshot when subsequent binding must use changed declarations.

## Incomplete catalogs and diagnostics

Use `analyze()` when the catalog is incomplete or the caller needs to collect
semantic problems alongside the structure.

| Input and catalog | Returned `Analysis` |
|-------------------|---------------------|
| Empty catalog; `SELECT t.id, t.* FROM missing t` | The relation declaration has `resolved = false`; the first output is `UnresolvedColumn` with `reference = ['t', 'id']`; the second is `Wildcard` with qualifier `['t']`; diagnostics include `unknown-table`. |
| The example catalog; `INSERT INTO users(missing) VALUES(1)` | The insertion retains an unresolved destination expression and its value; diagnostics include `unknown-column`. |
| PostgreSQL example catalog; `DELETE FROM users WHERE 1` | The numeric predicate remains in `statement.where`; diagnostics include `non-boolean-predicate`. |

An unresolved wildcard is one marker for an unknown output width. A consumer
must not count it as one resolved result column.

## Editing and SQL output

```php
$original = $binder->bind('SELECT score*2 FROM users');
$edited = $binder->replaceExpression(
    $original,
    $original->outputs[0]->expression->operands[0],
    'score+1',
);
$edited->toSql(); // "SELECT (\nscore+1\n)*2 FROM users"
$original->toSql(); // SELECT score*2 FROM users
```

Parentheses preserve precedence. Ownership checks reject an expression from a
different statement, and parsing rejects replacements that escape the expression
boundary. Rebinding refreshes types, scopes, lineage, and diagnostics. Invalid
model construction raises `Model\Validation\InvalidStructure` where constructor
invariants apply, including output order, scope ownership, column membership,
dialect consistency, and effect shape. This is not a general graph serializer;
see [editing limitations](limitation.md#construction-editing-and-sql-output).
