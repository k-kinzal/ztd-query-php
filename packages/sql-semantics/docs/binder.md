# Binder

`Binder` describes a SQL operation against an immutable [Schema](schema.md). It
resolves references and derives types, NULL facts, and dependencies. The result
contains the information a consumer needs to evaluate the operation. Binding does
not obtain runtime values, execute functions, modify rows, or apply session changes.

## Public interface

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)',
);
$statement = (new Binder($schema))->bind(
    'SELECT id, score + 1 AS next_score FROM users WHERE score > 0',
);

if ($statement instanceof BoundSelect) {
    $statement->outputs[0]->expression->lineage()[0]->column->name; // id
    $statement->outputs[1]->name;                                 // next_score
    $statement->outputs[1]->expression->type->name;               // integer
    $statement->outputs[1]->expression->nullability->value;       // not-null
    $statement->where?->inputs()[0]->lineage()[0]->column->name;   // score
}
```

`bind(string $sql, bool $strict = true): BoundStatement` reads one statement.
`bindAll(string $sql, bool $strict = true): array` returns an ordered list. Each
statement uses the same supplied schema snapshot. The statements do not form an
executed script: a SET or CREATE in that list does not change the context of later
statements. Supply a new schema snapshot to interpret changed state.

## Statement forms

The concrete class specifies which operands exist. `kind` is a `StatementKind`
enum derived from that class. Classes with different required inputs have different
constructors; unrelated operands are not represented by empty or nullable fields.
The table uses short class names. Query classes are in `Model` or
`Model\Statement`; insertion, mutation, and configuration forms have corresponding
subnamespaces under `Model\Statement`.

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `SELECT id FROM users WHERE score > 0` | `BoundSelect` | Ordered `outputs`, optional input `from`, row predicate `where`, grouping, `having`, duplicate-elimination `quantifier`, ordering, pagination, named `windows`, and `locks`. |
| `VALUES (1), (2)` | `ValuesStatement` | Nonempty `rows` of equal width; result columns and common types are derived from those rows. MySQL uses `VALUES ROW(1), ROW(2)`. |
| `TABLE users` | `TableStatement` | A required table or CTE reference; result columns are derived from that declaration. |
| `SELECT id FROM users UNION ALL SELECT id FROM incoming` | `CompoundStatement` | Required `left`, `right`, and `setOperator`; compatible result widths and common types by position. |
| `INSERT INTO users(id,score) VALUES (1,10)` | `InsertValuesStatement` | `insertion` destination mapping and nonempty `rows`; row widths agree with known destinations. |
| `INSERT INTO users(id,score) SELECT id,score FROM incoming` | `InsertSelectStatement` | `insertion` and a required `query`. The query supplies the input columns. |
| `INSERT INTO users DEFAULT VALUES` | `InsertDefaultValuesStatement` | A destination whose omitted columns obtain defaults or generated values. No row or SELECT payload. |
| MySQL: `INSERT INTO users SET id=1, score=10` | `InsertSetStatement` | `insertion` and nonempty ordered `writes`. |
| `UPDATE users SET score=score+1 WHERE id=1` | `UpdateTableStatement` | A single `target`, nonempty `writes`, and optional row predicate. |
| `UPDATE users SET score=incoming.score FROM incoming WHERE users.id=incoming.id` | `UpdateFromStatement` | Separate required `target` and `from` inputs; ordered writes and predicate. |
| MySQL: `UPDATE users JOIN incoming ON users.id=incoming.id SET users.score=incoming.score` | `UpdateJoinedStatement` | Required joined `from`, affected `targets`, and ordered writes. |
| `DELETE FROM users WHERE id=1` | `DeleteTableStatement` | One required `target` and optional predicate. |
| `DELETE FROM users USING incoming WHERE users.id=incoming.id` | `DeleteUsingStatement` | Separate required deletion `target` and read input `using`. |
| MySQL: `DELETE users FROM users JOIN incoming ON users.id=incoming.id` | `DeleteJoinedStatement` | Joined input and explicit deletion targets. |
| `MERGE INTO users USING incoming ON users.id=incoming.id WHEN MATCHED THEN DELETE` | `MergeStatement` | Required target, input, match condition, and ordered, typed actions. |
| `SET LOCAL work_mem='64MB'` | `SetStatement` | Nonempty `settings`: an `AssignedSetting` with required expressions, a `DefaultSetting` requesting the parameter default, a `CurrentSetting` copying current state, or an `AssignedUserVariable` with a required target and one expression. |
| MySQL: `SET @x=123` | `SetStatement` | An `AssignedUserVariable` holds its `target` reference and one required `value`. A declared variable target retains its supplied `VariableDefinition`. |
| `RESET work_mem`, `RESET ALL` | `ResetSettingStatement`, `ResetAllSettingsStatement` | A required named parameter, or all session parameters with no name payload. |
| MySQL: `RESET PERSIST IF EXISTS max_connections`, `RESET PERSIST` | `ResetSettingStatement`, `ResetAllPersistedVariablesStatement` | A persisted variable with its existence policy, or all persisted variables. |
| SQLite: `PRAGMA main.cache_size` | `ReadPragmaStatement` | Qualified `name`; no assigned value. |
| SQLite: `PRAGMA main.cache_size=-2000` | `AssignPragmaStatement` | Qualified `name` and one required argument, classified as a numeric, text, or identifier argument. |
| `CREATE TABLE t(id INTEGER DEFAULT 1)` | `CreateTableStatement` | `definition.table`: ordered columns, typed value sources, constraints, indexes, and dialect-specific properties. |
| `CREATE TABLE t AS SELECT id FROM users` | `CreateTableAsStatement` | The target name, source query, and declaration options. |
| `CREATE INDEX ix ON users((score+1)) WHERE score>0` | `CreateIndexStatement` | Required `table` and `index.definition`, including ordered typed keys and a partial-index predicate. |
| MySQL: `DROP INDEX ix ON users ALGORITHM=INPLACE LOCK=NONE` | `DropTableIndexStatement` | Required index name and owning table, with algorithm and lock enums. |
| `DROP INDEX CONCURRENTLY IF EXISTS ix` | `DropIndexConcurrentlyStatement` | Exactly one index name and existence policy. |
| `DROP TRIGGER tr ON users CASCADE` | `DropTableTriggerStatement` | Required trigger name and owning table, with drop behavior. |
| `PREPARE s(int) AS SELECT $1` | `PrepareQueryStatement` | Required nested statement, name and declared parameter types; the SELECT's parameter has the declared integer type. |
| MySQL: `PREPARE s FROM @sql` | `PrepareTextStatement` | Name and a required user-variable reference or text literal that supplies SQL at execution time. |
| `EXECUTE s(1, 2)` | `ExecuteQueryStatement` | Prepared-query name and ordered argument expressions. |
| MySQL: `EXECUTE s USING @x, @y` | `ExecuteUsingStatement` | Prepared-statement name and ordered user-variable references. |
| `DEALLOCATE s`, `DEALLOCATE ALL` | `DeallocateStatement`, `DeallocateAllStatement` | One required prepared-statement name, or all prepared statements. |
| `EXPLAIN UPDATE users SET score=1` | `ExplainStatement` | Required nested `statement` and classified EXPLAIN options. |
| `DECLARE cur CURSOR FOR SELECT id FROM users` | `DeclareCursorStatement` | Cursor name, required `query`, scrollability, sensitivity, transfer format, and lifetime. |
| `FETCH BACKWARD ALL FROM cur` | `FetchCursorStatement` | Cursor name and `RemainingRows(Backward)` movement. |
| `MOVE ABSOLUTE -2 FROM cur` | `MoveCursorStatement` | Cursor name and `PositionedRow(Absolute, -2)`. The position is described, not evaluated. |
| `CLOSE cur`, `CLOSE ALL` | `CloseCursorStatement`, `CloseAllCursorsStatement` | One named cursor or all cursors, respectively. |
| `LISTEN events`, `UNLISTEN events`, `UNLISTEN *` | `ListenStatement`, `UnlistenStatement`, `UnlistenAllStatement` | A required channel name for named operations; the all-channels operation has no name payload. |
| `NOTIFY events, 'changed'` | `NotifyStatement` | Required `channel` and optional text-literal `payload`. |
| `DISCARD PLANS` | `DiscardStatement` | A `DiscardResource` enum selecting plans, sequences, temporary tables, or all session resources. |
| `SET CONSTRAINTS ALL DEFERRED` | `SetAllConstraintsStatement` | A `ConstraintTiming` enum; the selection is all deferrable constraints. |
| `CHECKPOINT` | `CheckpointStatement` | A checkpoint request with no value operands. |
| MySQL: `KILL CONNECTION 42`, `KILL QUERY 42` | `KillConnectionStatement`, `KillQueryStatement` | A required `connectionId` expression and a concrete operation identifying what to stop. |
| MySQL: `INSTALL PLUGIN audit SONAME 'audit.so'` | `InstallPluginStatement` | Required plugin `name` and text-literal `library`. |
| MySQL: `UNINSTALL PLUGIN audit` | `UninstallPluginStatement` | Required plugin `name`. |
| MySQL: `RESTART`, `SHUTDOWN`, `UNLOCK TABLES` | `RestartServerStatement`, `ShutdownServerStatement`, `UnlockTablesStatement` | Distinct operations with no value operands. |
| MySQL: `CLONE LOCAL DATA DIRECTORY '/tmp/clone'` | `CloneLocalStatement` | A required destination-directory text literal. |
| MySQL: `BINLOG 'YWJj'` | `ApplyBinlogStatement` | A required text literal containing an encoded binary-log event. |
| `REINDEX INDEX app.ix`, `REINDEX TABLE app.t`, `REINDEX SCHEMA app` | `ReindexObjectStatement` | Required object name, `ReindexObjectKind`, and PostgreSQL rebuild options. |
| `REINDEX DATABASE`, `REINDEX SYSTEM` | `ReindexDatabaseStatement` | User-table or system-table index selection in the current database. An optional database name records an explicit name assertion. |
| SQLite `REINDEX`, `REINDEX ix` | `ReindexAllStatement`, `ReindexNamedStatement` | Rebuild all indexes, or resolve a required SQLite index/table/collation name. |

INSERT, UPDATE, DELETE, and MERGE retain ordered RETURNING `outputs` where the
selected language provides them. Their `affectedTables()` method identifies write
targets. REPLACE uses an insertion form with `InsertMode::Replace`. It does not lose
the distinction between explicit rows, a source query, and column assignments.

### Destinations and conditional writes

An `Insertion` maps each input position to a `Storage\Path`. `ColumnPath` requires a
column reference. `FieldPath`, `ElementPath`, and `SlicePath` add their own required
field or index operands. An unresolved destination remains a classified unresolved
column reference, accompanied by diagnostics.

`ScalarAssignment` has one path and one expression. `DefaultAssignment` has a
required destination and requests its declared default without an expression.
`TupleRowAssignment` requires an `InputRow`: ordered slots containing an expression
or `DefaultSource::Column`. `TupleQueryAssignment` requires a query with compatible
width. Input order is retained, including repeated assignments.

`InsertValuesStatement::rows` contains the same expression-or-default slots.
`DefaultSource` is a storage instruction, outside the expression hierarchy. It cannot
be passed as an arithmetic operand, a projection, or a predicate. `ValuesStatement`
is a query and accepts expression rows only.

A conflict selector is `AnyConflict`, `IndexConflict` with required ordered keys,
or `ConstraintConflict` with a required constraint name. `DoNothing` has no write
payload. `DoUpdate` requires ordered assignments and may have its own predicate.

MERGE actions distinguish updates, deletes, no action, default insertion, and
single-row insertion. `MergeRowInsertion` requires exactly one `InputRow`. Its `MatchKind` identifies the rows visible to that
branch; insertion requires a missing target, while updates require an existing one.

## Relations and query stages

Each `TableUse` has an occurrence `id`, owning `scopeId`, declaration, and optional
alias. Self joins retain separate identities. `BoundSelect::relations` is derived
from its complete `from` input; callers cannot supply a contradictory relation list.

| Input form | Structured result |
|------------|-------------------|
| Named table | `TableReference` with a qualified name and declaration. |
| Derived SELECT | `DerivedRelation` with a required query and LATERAL policy. |
| Common table expression | `CteReference` with its required definition. A statement's `ctes` owns ordered `CommonTableExpression` definitions, column aliases, and materialization policy. |
| `JOIN ... ON ...` | `OnJoin` with a required predicate and two inputs. |
| `JOIN ... USING(id)` | `UsingJoin` with a nonempty set of `SharedColumn` pairs and their merged output expressions. |
| `NATURAL JOIN` | `NaturalJoin` retaining its shared columns, including the case of no shared names. |
| `CROSS JOIN` | `CrossJoin`, with no predicate field. |
| Parenthesized, aliased join | `AliasedRelation` with a required inner input and its own visible output names. |
| Table function | `FunctionRelation` with a required invocation and output declaration. |
| `JSON_TABLE(...)` | `DocumentRelation` whose `JsonTable` retains the input document, row path, PASSING variables, and nested column declarations. Value, existence, ordinality, and nested-path columns have different types. |
| `XMLTABLE(...)` | `DocumentRelation` whose `XmlTable` retains namespaces, document and row-path expressions, passing modes, and typed value or ordinality columns. |

Document-table output columns derive from their declarations. A value column retains
its own PATH, type, and default/error behavior. Nested JSON paths retain their
parent-child structure. Those declarations are available to a consumer; binding
does not read or expand the document.

Ordering distinguishes an input expression from an output alias or output position.
A `NamedRowLock` requires explicit relation targets; `AllRowLock` applies to eligible
inputs in that query. Lock strength and waiting behavior are enums. Nested queries
own their own predicates, ordering, pagination, windows, and locks.

## Selected values

Operations that produce results implement `ResultStatement`. Commands such as SET,
COMMIT, and REINDEX do not expose a result-column API.
`ResultStatement::resultColumns()` returns ordered `OutputColumn` objects. Each has
an `ordinal`, optional output `name`, and typed `expression`. Repeated names retain
separate positions. Wildcards expand using visible declarations. An unresolved
wildcard records an unknown result width rather than pretending to be one known
column.

| Expression form | Information returned |
|-----------------|----------------------|
| Column | `ColumnReference` with a `ColumnBinding`: relation identity, table identity, and column symbol containing its declared type and NULL fact. |
| Literal | `LiteralKind` and exact SQL literal `text`, preserving numeric precision and quoting. |
| Binary or unary operation | An operator enum and required `left`/`right` or `operand`. |
| Function | `FunctionCall` has a registered or unresolved function reference and ordered value arguments. |
| Aggregate over values | `AggregateCall` retains value arguments, ALL/DISTINCT, optional input ordering, and FILTER. |
| Aggregate over rows, such as `count(*)` | `AllRowsAggregate` retains the function reference and optional FILTER; it has no value-argument list. |
| Ordered-set aggregate | `OrderedSetCall` separates `directArguments` from the required `withinGroup` row ordering and optional FILTER. Both argument groups participate in signature resolution. |
| Window function | `WindowCall` retains the invocation and its window specification or named window reference. |
| JSON membership | MySQL `JsonMembership` has a required searched `value` and JSON `array` input. Neither is evaluated during binding. |
| CASE | `SimpleCase` requires a selector; `SearchedCase` requires predicates. Each retains ordered branches and its optional ELSE value. |
| Scalar subquery | `ScalarSubquery` with a query producing one known column, or an unresolved width. |
| Row subquery | `RowSubquery` with a row-producing query, distinct from a scalar query. |
| EXISTS, IN, quantified comparison | Dedicated classes retain the query and each required comparison operand. |
| Variable | `VariableReference` identifies the supplied variable definition and scope. A missing definition produces `UnresolvedVariableReference`. Neither contains the current runtime value. |
| Context value | `ContextReference` identifies operations such as CURRENT_DATE; it does not retrieve the current date. |

Every expression exposes `type`, `nullability`, and `nullExtendedBy`. `kind` is an
`ExpressionKind` enum. `inputs()` visits immediate expression operands; `lineage()`
returns contributing column bindings while preserving distinct relation occurrences.

A selected column takes its declared type and NULL fact, adjusted for outer joins at
that occurrence. Operators derive facts from their operands and dialect. Functions
use registered signatures. A compound result combines corresponding operand types.
`TypeDescriptor::identity` carries typed storage parameters; `unknown` denotes missing
static type information. No expression is evaluated to infer its runtime value.

Predicates remain separate expression trees in ON, WHERE, HAVING, and conditional
writes. The consumer combines these with expression facts when evaluating SQL,
solving constraints, or generating fixtures. Binding describes the value-producing
operation at each stage; it does not solve predicates over possible values.

## Diagnostics and transformations

Strict binding raises `SemanticException` for unresolved names or incompatible known
types. `strict: false` retains classified unresolved references and `Diagnostic`
objects, each with `reason`, `message`, and `source`. A structurally impossible request,
such as a known INSERT width mismatch, raises `InvalidSql` with an `InputViolation`
enum; it does not manufacture a valid Statement. Lexical and syntax errors are
reported by sql-parser. An unclassified construct is an implementation failure and
is not represented by a generic command or raw grammar payload.

`source` retains parser positions and original text for diagnostics. Semantic
operands determine serialization. Transformations belong to the Statement and
return a new validated snapshot. See [statements and serialization](statements.md).
