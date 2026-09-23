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
| MySQL: `SET TRANSACTION READ ONLY` | `SetNextTransactionStatement` | Optional `isolation` and `access` enums, with at least one required; applies to the next transaction. |
| MySQL: `SET GLOBAL TRANSACTION ISOLATION LEVEL SERIALIZABLE` | `SetDefaultTransactionStatement` | Required `DefaultScope`, plus an isolation and/or access request. SESSION, GLOBAL, PERSIST, and PERSIST_ONLY remain distinct; LOCAL denotes SESSION. |
| PostgreSQL: `SET TRANSACTION READ ONLY, DEFERRABLE` | `SetCurrentTransactionStatement` | Nonempty ordered `modes` containing only `Isolation`, `Access`, or `Deferrability` enums, plus outer SET `Locality`. |
| PostgreSQL: `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY` | `SetSessionTransactionStatement` | The same typed mode roles, targeting session defaults for subsequent transactions. |
| PostgreSQL: `SET TRANSACTION SNAPSHOT 'snapshot-id'` | `SetTransactionSnapshotStatement` | Required `snapshot` text literal and SET `Locality`; the snapshot identifier is retained without loading snapshot contents. |
| MySQL 5.7+: `SET PASSWORD FOR 'u'@'localhost' = 'new'` | `SetPasswordStatement` | Required `account` and `password` text literal; optional `currentPassword` verification operand and `retainCurrentPassword` flag on releases with those clauses. |
| MySQL 8+: `SET PASSWORD TO RANDOM` | `SetRandomPasswordStatement` | Required `account`, optional verification operand, and retention flag; no supplied new password. Four derived result columns describe the requested server output. |
| MySQL 5.6: `SET PASSWORD = '*encoded'` | `SetPasswordHashStatement` | Required `account` and `hash` text literal. The encoded value is not interpreted. |
| MySQL 5.6: `SET PASSWORD = PASSWORD('new')` | `SetDerivedPasswordStatement` | Required `account`, cleartext `password` operand, and `PasswordDerivation` (`Configured` or `Pre41`). The consumer performs the requested hashing. |
| MySQL 5.6: `SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'` | `SetAccountOptionsStatement` | Two or more ordered, individually typed credential or variable operations. Each variable operation contains one assignment. |
| MySQL: `SET ROLE NONE`, `SET ROLE DEFAULT`, `SET ROLE ALL` | `SetRolePolicyStatement` | A required `SessionRolePolicy` enum; no assignment or role-name payload. |
| MySQL: `SET ROLE 'reader'@'localhost'` | `SetExplicitRolesStatement` | Nonempty `roles`, each an `AccountName` with separate `username` and optional `host`. |
| MySQL: `SET ROLE ALL EXCEPT 'writer'` | `SetRolesExceptStatement` | Nonempty `excludedRoles`; the operation identifies the complementary selection. |
| MySQL: `SET DEFAULT ROLE ALL TO 'alice'` | `SetDefaultRolePolicyStatement` | Required `DefaultRolePolicy` (`None` or `All`) and nonempty recipient `accounts`. |
| MySQL: `SET DEFAULT ROLE 'reader' TO 'alice', 'bob'` | `SetDefaultRolesStatement` | Separate nonempty `roles` and recipient `accounts`. This records account defaults rather than current session activation. |
| `RESET work_mem`, `RESET ALL` | `ResetSettingStatement`, `ResetAllSettingsStatement` | A required named parameter, or all session parameters with no name payload. |
| MySQL: `RESET PERSIST IF EXISTS max_connections`, `RESET PERSIST` | `ResetSettingStatement`, `ResetAllPersistedVariablesStatement` | A persisted variable with its existence policy, or all persisted variables. |
| SQLite: `PRAGMA main.cache_size` | `ReadPragmaStatement` | Qualified `name`; no assigned value. |
| SQLite: `PRAGMA main.cache_size=-2000` | `AssignPragmaStatement` | Qualified `name` and one required argument, classified as a numeric, text, or identifier argument. |
| `CREATE TABLE t(id INTEGER DEFAULT 1)` | `CreateTableStatement` | `definition.table`: ordered columns, typed value sources, constraints, indexes, and dialect-specific properties. |
| `CREATE TABLE t AS SELECT id FROM users` | `CreateTableAsStatement` | The target name, source query, and declaration options. |
| PostgreSQL: `IMPORT FOREIGN SCHEMA ext LIMIT TO (users) FROM SERVER remote INTO app` | `ImportForeignSchemaStatement` | Required `remoteSchema`, `server`, and `localSchema`; `selection` is `AllForeignTables`, `ImportOnlyTables`, or `ExcludeForeignTables`. Explicit selections require a nonempty list of `ForeignRelation` values. `options` is an ordered list of `ForeignOption` identifier/text-literal pairs. |
| PostgreSQL: `CREATE FOREIGN DATA WRAPPER fdw HANDLER app.h OPTIONS (format 'csv')` | `CreateForeignDataWrapperStatement` | Required wrapper `name`, optional `handler` and `validator` function names, and initial `ForeignOption` values with unique names. Omitted functions and explicit NO HANDLER/NO VALIDATOR both mean absence. |
| PostgreSQL: `ALTER FOREIGN DATA WRAPPER fdw NO HANDLER OPTIONS (ADD format 'csv', DROP path)` | `AlterForeignDataWrapperStatement` | Required wrapper `name`; each support-function change is a `QualifiedName`, `FunctionChange::Keep`, or `FunctionChange::Remove`. Ordered option changes are `AddForeignOption`, `SetForeignOption`, or `DropForeignOption`. At least one change is required. |
| `CREATE INDEX ix ON users((score+1)) WHERE score>0` | `CreateIndexStatement` | Required `table` and `index.definition`, including ordered typed keys and a partial-index predicate. |
| MySQL: `DROP INDEX ix ON users ALGORITHM=INPLACE LOCK=NONE` | `DropTableIndexStatement` | Required index name and owning table, with algorithm and lock enums. |
| `DROP INDEX CONCURRENTLY IF EXISTS ix` | `DropIndexConcurrentlyStatement` | Exactly one index name and existence policy. |
| `DROP TRIGGER tr ON users CASCADE` | `DropTableTriggerStatement` | Required trigger name and owning table, with drop behavior. |
| `PREPARE s(int) AS SELECT $1` | `PrepareQueryStatement` | Required nested statement, name and declared parameter types; the SELECT's parameter has the declared integer type. |
| MySQL: `PREPARE s FROM @sql` | `PrepareTextStatement` | Name and a required user-variable reference or text literal that supplies SQL at execution time. |
| `EXECUTE s(1, 2)` | `ExecuteQueryStatement` | Prepared-query name and ordered argument expressions. |
| MySQL: `EXECUTE s USING @x, @y` | `ExecuteUsingStatement` | Prepared-statement name and ordered user-variable references. |
| `DEALLOCATE s`, `DEALLOCATE ALL` | `DeallocateStatement`, `DeallocateAllStatement` | One required prepared-statement name, or all prepared statements. |
| MySQL: `SHOW ENGINES`, `SHOW STORAGE ENGINES` | `ShowEnginesStatement` | Six result fields identify the engine, availability, description, transaction support, XA support, and savepoint support. Synonymous syntax produces the same operation. |
| MySQL: `SHOW PLUGINS` | `ShowPluginsStatement` | Five result fields identify plugin name, status, type, library, and license. |
| MySQL: `SHOW PRIVILEGES` | `ShowPrivilegesStatement` | Three result fields describe privilege name, applicable object context, and description. |
| MySQL: `SHOW FULL PROCESSLIST` | `ShowProcessesStatement` | Eight connection-activity result fields and a `ProcessQueryText` policy; ordinary PROCESSLIST requests the first 100 characters of active SQL, while FULL requests complete text. |
| MySQL: `DO @x := 1, @x + 2` | `DoExpressionsStatement` | Nonempty ordered scalar `expressions`, with their ordinary types, references, and dependencies. The operation requests evaluation with no returned result set; Binder does not perform it. |
| MySQL: `DROP TEMPORARY TABLE IF EXISTS scratch` | `DropTableStatement` | Nonempty `names`, existence and dependency policies, and `TableDropScope::Temporary`. Normal DROP TABLE uses the visible table namespace. |
| MySQL: `DROP DATABASE IF EXISTS app` | `Definition\MySql\DropDatabaseStatement` | One database `name` and `ifExists`; `DROP SCHEMA` binds to the same operation. |
| MySQL: `DROP EVENT IF EXISTS app.daily` | `DropEventStatement` | One local or database-qualified event `name` and an existence policy. |
| MySQL: `DROP SERVER IF EXISTS remote` | `DropServerStatement` | One foreign-server definition `name` and an existence policy. |
| MySQL: `DROP RESOURCE GROUP workers FORCE` | `DropResourceGroupStatement` | One group `name` and a `force` flag requesting reassignment of affected threads to their default groups. |
| MySQL: `DROP USER CURRENT_USER, 'reader'@'localhost'` | `DropUsersStatement` | Nonempty `accounts`: named `AccountName` values or `CurrentAccount::Authenticated`. Usernames and optional hosts remain separate. |
| MySQL: `DROP ROLE IF EXISTS reader` | `DropRolesStatement` | Nonempty named `roles` and `ifExists`; each role has its own username and optional host. |
| MySQL: `CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'` | `CreateSpatialReferenceSystemStatement` | Required nonzero unsigned 32-bit `srid`, `SpatialDefinition`, and a mutually exclusive `CreationPolicy`: require a new definition, ignore an existing one, or replace it. |
| MySQL: `DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4120` | `DropSpatialReferenceSystemStatement` | Required nonzero unsigned 32-bit `srid` and `ifExists`; no declaration metadata. |
| MySQL: `DROP FUNCTION IF EXISTS app.f`, `DROP PROCEDURE app.p` | `Definition\MySql\DropFunctionStatement`, `DropProcedureStatement` | One required `QualifiedName` and an existence policy. There is no overload signature or dependency policy. |
| PostgreSQL: `DROP FUNCTION f, g(), h(IN integer) CASCADE` | `Definition\PostgreSql\DropFunctionsStatement` | Nonempty `targets`: `RoutineByName` leaves arguments unspecified; `RoutineBySignature` owns the ordered parameters, including an explicit empty list. `ifExists` and `DropBehavior` apply to the request. |
| PostgreSQL: `DROP PROCEDURE p(text)`, `DROP ROUTINE r` | `DropProceduresStatement`, `DropRoutinesStatement` | The same typed overload selectors, with distinct statement types for procedure-only and general routine lookup. |
| PostgreSQL: `DROP AGGREGATE a(*), b(integer), c(integer ORDER BY text)` | `DropAggregatesStatement` | A nonempty list of `ZeroArgumentAggregate`, `OrdinaryAggregate`, or `OrderedSetAggregate`. The ordered-set form requires aggregated parameters and separately retains direct parameters. |
| MySQL or SQLite: `DROP TRIGGER IF EXISTS audit` | `DropTriggerStatement` | Exactly one `name` and an existence policy. PostgreSQL uses `DropTableTriggerStatement` with a required owning table. |
| `EXPLAIN UPDATE users SET score=1` | `ExplainStatement` | Required nested `statement` and classified EXPLAIN options. |
| `DECLARE cur CURSOR FOR SELECT id FROM users` | `DeclareCursorStatement` | Cursor name, required `query`, scrollability, sensitivity, transfer format, and lifetime. |
| `FETCH BACKWARD ALL FROM cur` | `FetchCursorStatement` | Cursor name and `RemainingRows(Backward)` movement. |
| `MOVE ABSOLUTE -2 FROM cur` | `MoveCursorStatement` | Cursor name and `PositionedRow(Absolute, -2)`. The position is described, not evaluated. |
| `CLOSE cur`, `CLOSE ALL` | `CloseCursorStatement`, `CloseAllCursorsStatement` | One named cursor or all cursors, respectively. |
| `LISTEN events`, `UNLISTEN events`, `UNLISTEN *` | `ListenStatement`, `UnlistenStatement`, `UnlistenAllStatement` | A required channel name for named operations; the all-channels operation has no name payload. |
| `NOTIFY events, 'changed'` | `NotifyStatement` | Required `channel` and optional text-literal `payload`. |
| `DISCARD PLANS` | `DiscardStatement` | A `DiscardResource` enum selecting plans, sequences, temporary tables, or all session resources. |
| `SET CONSTRAINTS ALL DEFERRED` | `SetAllConstraintsStatement` | A `ConstraintTiming` enum; the selection is all deferrable constraints. |
| PostgreSQL: `LOCK ONLY users IN SHARE MODE NOWAIT` | `LockRelationsStatement` | Nonempty ordered `tables`, one `PostgreSqlLockMode`, and a `nowait` policy. Descendant exclusion is retained on each target. |
| MySQL: `LOCK TABLES users AS u READ LOCAL, incoming WRITE` | `LockTablesStatement` | Nonempty `locks`; each `MySqlTableLock` owns its required table occurrence, alias, and independent `MySqlLockMode`. |
| `CHECKPOINT` | `CheckpointStatement` | A checkpoint request with no value operands. |
| MySQL: `XA START 'g', 'b', 42 JOIN` | `XaStartStatement` | Required `transactionId` and `StartMode` (`NewBranch`, `Join`, or `Resume`). `XA BEGIN` produces the same start operation. |
| MySQL: `XA END 'g' SUSPEND FOR MIGRATE` | `XaEndStatement` | Required `transactionId` and `EndMode` (`End`, `Suspend`, or `Migrate`). |
| MySQL: `XA PREPARE 'g'`, `XA ROLLBACK 'g'` | `XaPrepareStatement`, `XaRollbackStatement` | Required `transactionId`; each class fixes its operation and has no start or commit policy. |
| MySQL: `XA COMMIT 'g' ONE PHASE` | `XaCommitStatement` | Required `transactionId` and `CommitMode` (`Prepared` or `OnePhase`). |
| MySQL: `XA RECOVER CONVERT XID` | `XaRecoverStatement` | `RecoveryEncoding` and four derived result columns; no input transaction identifier. |
| MySQL: `KILL CONNECTION 42`, `KILL QUERY 42` | `KillConnectionStatement`, `KillQueryStatement` | A required `connectionId` expression and a concrete operation identifying what to stop. |
| MySQL: `CREATE TEMPORARY TABLE copied LIKE original` | `CreateTableLikeStatement` | Required destination `target` and `template` table reference, plus `temporary` and `ifNotExists`. The reference exposes the supplied source definition without applying the copy to the snapshot. |
| MySQL: `INSTALL PLUGIN audit SONAME 'audit.so'` | `InstallPluginStatement` | Required plugin `name` and text-literal `library`. |
| MySQL: `UNINSTALL PLUGIN audit` | `UninstallPluginStatement` | Required plugin `name`. |
| MySQL: `RESTART`, `SHUTDOWN`, `UNLOCK TABLES` | `RestartServerStatement`, `ShutdownServerStatement`, `UnlockTablesStatement` | Distinct operations with no value operands. |
| MySQL: `CLONE LOCAL DATA DIRECTORY '/tmp/clone'` | `CloneLocalStatement` | A required destination-directory text literal. |
| MySQL: `BINLOG 'YWJj'` | `ApplyBinlogStatement` | A required text literal containing an encoded binary-log event. |
| MySQL: `TRUNCATE users` | `TruncateTableStatement` | One required physical `table`; there is no predicate or query input. |
| MySQL: `CACHE INDEX users, incoming IN hot` | `CacheTableIndexesStatement` | Nonempty `targets`, each a `TableIndexes` with one physical `table` and optional `NamedIndexes`; required `cache` is `DefaultCache` or a `CacheName`. |
| MySQL: `CACHE INDEX users PARTITION (p0,p1) IN hot` | `CachePartitionIndexesStatement` | One `target`, required `AllPartitions` or nonempty `NamedPartitions`, and required `cache`. |
| MySQL: `LOAD INDEX INTO CACHE users, incoming IGNORE LEAVES` | `PreloadTableIndexesStatement` | Nonempty `PreloadTarget` requests; each contains `TableIndexes` and its own `ignoreLeaves` policy. The current cache assignment supplies the destination. |
| MySQL: `LOAD INDEX INTO CACHE users PARTITION (ALL) IGNORE LEAVES` | `PreloadPartitionIndexesStatement` | One `PreloadTarget` and a required partition selection. No explicit cache destination. |
| MySQL: `CHECK TABLE users QUICK FOR UPGRADE` | `CheckTablesStatement` | Nonempty physical `tables` and ordered `CheckOption` values. |
| MySQL: `REPAIR LOCAL TABLE users QUICK USE_FRM` | `RepairTablesStatement` | Nonempty physical `tables`, ordered `RepairOption` values, and `BinlogPolicy`. |
| MySQL: `OPTIMIZE TABLE users`, `ANALYZE TABLE users` | `OptimizeTablesStatement`, `AnalyzeTablesStatement` | Nonempty physical `tables` and `BinlogPolicy`; each class identifies its own operation. |
| MySQL: `CHECKSUM TABLE users QUICK` | `ChecksumTablesStatement` | Nonempty physical `tables` and `ChecksumMode` selecting automatic, stored, or row-scanned checksums. |
| MySQL: `ANALYZE TABLE users UPDATE HISTOGRAM ON score WITH 100 BUCKETS AUTO UPDATE` | `UpdateHistogramStatement` | One required `table`, nonempty bound `columns`, optional `BucketCount` from 1 to 1024, `RefreshPolicy`, and `BinlogPolicy`. |
| MySQL: `ANALYZE TABLE users UPDATE HISTOGRAM ON score USING DATA '{}'` | `ImportHistogramStatement` | One required `table` and bound `column`, an opaque text-literal `data` operand, and `BinlogPolicy`. |
| MySQL: `ANALYZE TABLE users DROP HISTOGRAM ON score` | `DropHistogramStatement` | One required `table`, nonempty bound `columns`, and `BinlogPolicy`; no sampling or import operands. |
| PostgreSQL: `TRUNCATE ONLY users, incoming RESTART IDENTITY CASCADE` | `TruncateRelationsStatement` | Nonempty explicit `tables`, an `IdentityReset` policy, and a `ReferencingTables` policy for foreign-key dependencies. Binding records these requests without removing rows, resetting sequences, or expanding runtime effects. |
| `REINDEX INDEX app.ix`, `REINDEX TABLE app.t`, `REINDEX SCHEMA app` | `ReindexObjectStatement` | Required object name, `ReindexObjectKind`, and PostgreSQL rebuild options. |
| `REINDEX DATABASE`, `REINDEX SYSTEM` | `ReindexDatabaseStatement` | User-table or system-table index selection in the current database. An optional database name records an explicit name assertion. |
| SQLite `REINDEX`, `REINDEX ix` | `ReindexAllStatement`, `ReindexNamedStatement` | Rebuild all indexes, or resolve a required SQLite index/table/collation name. |

Foreign-data wrapper creation and alteration have different operand domains.
Creation owns initial named text options. Alteration owns ordered changes:
addition and replacement require a `ForeignOption`, while removal has only a name.
The handler identifies a zero-argument function returning `fdw_handler`; the
validator identifies a function accepting `text[]` and `oid` whose return value is
ignored. Binding records those function roles without calling them. An ALTER's
`withChanges()` replaces function and option changes together, so removing the
last requested operation cannot leave an empty alteration.

Foreign schema imports retain remote relation qualification and descendant scope
for the foreign-data wrapper. These are remote selectors, so binding does not look
them up among local table declarations. A `ForeignOption` requires a name and a
PostgreSQL text literal; the selected wrapper defines the option's meaning.
Binding does not connect to the remote server, discover columns, or create local
tables. The supplied schema snapshot stays unchanged. Import selections and
options can be replaced immutably, and the remote server/schema pair can be
replaced together with `withRemote()`.

Spatial reference declarations are available in the selected MySQL 8+ grammars.
`SpatialDefinition` requires `name` and `definition` text literals. Optional
`Organization` pairs its name literal with an unsigned 32-bit authority identifier;
`description` is a separate optional literal. Attribute order has no semantic
meaning and duplicate attributes are rejected. Binding describes these operands
without interpreting the coordinate-system definition text or reading existing
spatial systems. Replacing metadata supplies a complete `SpatialDefinition`, so
required attributes cannot disappear during a transformation.

INSERT, UPDATE, DELETE, and MERGE retain ordered RETURNING `outputs` where the
selected language provides them. Their `affectedTables()` method identifies write
targets. REPLACE uses an insertion form with `InsertMode::Replace`. It does not lose
the distinction between explicit rows, a source query, and column assignments.

Index-cache requests retain the explicit index selection, including `INDEX ()`,
separately from an omitted selection. These operands describe the request; MySQL's
storage engine currently applies cache assignment and preloading to all indexes
of the target table ([CACHE INDEX](https://dev.mysql.com/doc/refman/8.4/en/cache-index.html),
[LOAD INDEX INTO CACHE](https://dev.mysql.com/doc/refman/8.4/en/load-index.html)).
`ignoreLeaves` requests nonleaf pages only. The four cache
statement forms return the maintenance result roles `Table`, `Op`, `Msg_type`, and
`Msg_text`. Binding neither allocates a cache nor loads pages.

Server inspection statements are in `Model\Statement\Inspection`. Their
`resultColumns()` returns the requested metadata shape, without reading the server.
`ServerTextColumn` uses an `EngineField`, `PluginField`, or `PrivilegeField` enum and
records the producing scope. Its VARCHAR and NULL facts follow the field's role;
for example, a plugin's library can be NULL for a built-in plugin. Process fields
use `ProcessColumn` for identity and activity, and `ProcessInfoColumn` for query
text. The latter retains preview versus complete text and is nullable when no SQL
is active. `withQueryText()` refreshes both the request and the resulting text-length
fact in a new statement.

MySQL table-maintenance statements are in `Model\Statement\Maintenance\MySql`.
Their table references expose the supplied declarations; histogram columns identify
their target relation occurrence and column symbol. `withTarget()` changes a histogram's
table and columns together, so a column from another table cannot be attached to it.
`BinlogPolicy` distinguishes the default binary-log request from LOCAL or
NO_WRITE_TO_BINLOG. These operations describe requested storage-engine work without
performing it or consulting live statistics.

CHECK, REPAIR, OPTIMIZE, ANALYZE, and histogram operations expose four result columns:
`Table`, `Op`, `Msg_type`, and `Msg_text`. Their `StatusColumn` expressions carry a
`StatusField` enum, producing scope identity, and non-NULL VARCHAR facts. CHECKSUM
exposes `Table` and `Checksum`; its `ChecksumColumn` describes a non-NULL VARCHAR
table name or a nullable unsigned BIGINT checksum. The structure supplies result
roles and types, not status messages, computed checksums, or histogram values.

MySQL role operations are in `Model\Statement\Configuration\Role`. Their
account names preserve case and separate host qualification; an omitted host
refers to `%`. A quoted name such as `'NONE'` is a named role, distinct from the
`None` policy. Binding records the requested selection and recipients without
reading grants, activating roles, or changing account defaults. These statements
have no variable assignments. Each owns immutable replacements for its required
policy, role list, exclusion list, or recipient list.

Transaction-setting statements distinguish the current transaction, the next
transaction, and defaults for future transactions. PostgreSQL mode requests preserve
their order, including repeated requests. `Locality` retains the outer SET lifetime,
separately from which transaction settings the statement targets. These operations
describe requested changes; the supplied Schema and live transaction state are not
changed or consulted. In particular, snapshot existence and execution-state
requirements belong to the consumer executing the request.

Account identifiers use `Model\Configuration\Account\AccountName`, with username
and host kept separately. A password request for the authenticated principal uses
`CurrentAccount::Authenticated`; binding does not look up its username. Credential
literals preserve their SQL spelling. Verification, hashing, plugin selection, and
random generation are execution operations represented by the structure. MySQL 5.7's
deprecated `PASSWORD(...)` spelling in SET PASSWORD normalizes to the same cleartext
request as direct assignment, following that release's behavior.

`SetRandomPasswordStatement::resultColumns()` describes `user`, `host`,
`generated password` (VARCHAR), and `auth_factor` (unsigned BIGINT). Each expression
is a `GeneratedPasswordColumn` with the producing `scopeId`, account reference, and
`GeneratedPasswordField` enum. These are server-produced fields with no scalar SQL
inputs, not generated or retrieved credential values.

XA statement classes are in `Model\Statement\Transaction\Xa`. Their
`TransactionId` requires a `global` string, hexadecimal, or bit literal of at most
64 bytes. An optional `BranchIdentifier` has its own required `qualifier` literal
and optional `FormatIdentifier`. A format cannot be supplied without a branch.
The format operand retains its numeric spelling, including the hexadecimal and
numeric forms admitted by the MySQL grammar. Binding does not convert literal
operands into runtime transaction identifiers. Omitted qualifiers and formats
request MySQL's empty branch and format 1 defaults.

`XaRecoverStatement::resultColumns()` returns `formatID`, `gtrid_length`,
`bqual_length`, and `data` in that order. Each expression is a `RecoveryColumn`
with a `RecoveryField` enum, the producing statement's scope identity, and the
requested encoding. The first three columns have non-NULL bigint facts; `data`
has a non-NULL varchar fact and represents concatenated identifier bytes or their
hexadecimal rendering. These are server-produced fields, with no standalone scalar
SQL expression. Binding records the recovery request without inspecting prepared
transactions. Likewise, binding XA control records the requested operation without
checking live transaction state or applying a transaction transition.

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
| Named table | `TableReference` with a qualified name and declaration. PostgreSQL includes descendant tables. |
| PostgreSQL `ONLY table` | `OnlyTableReference` with the same identity information and an explicit restriction to this table's rows. This type also identifies UPDATE, DELETE, TABLE, LOCK, and CREATE INDEX targets. |
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
column. PostgreSQL permits a projection with zero output columns. MySQL and SQLite
SELECT structures require at least one output.

| Expression form | Information returned |
|-----------------|----------------------|
| Column | `ColumnReference` with a `ColumnBinding`: relation identity, table identity, and column symbol containing its declared type and NULL fact. |
| Literal | `LiteralKind` and exact SQL literal `text`, preserving numeric precision and quoting. |
| PostgreSQL typed literal | An explicit `CastExpression` retains the literal operand and the declared type identity, including precision, interval fields, or a user-defined type name and modifiers. Binding does not parse the literal into a runtime date or other value. |
| Date/time extraction | `Extract` has a required `PostgreSqlField` or `MySqlUnit` enum and a required temporal `value`. The field must match the operand dialect. Its result is PostgreSQL numeric or MySQL bigint; field names are normalized without evaluating the input. |
| MySQL temporal arithmetic | `DateShift` has required temporal `value`, interval `quantity`, `MySqlUnit`, and `ShiftDirection`. `IntervalOperandOrder` retains input order, including leading intervals, so anonymous parameter positions survive serialization. DATE_ADD, DATE_SUB, ADDDATE, SUBDATE, and infix interval forms bind these roles. |
| String position | `Position` has required `needle` and `haystack` expressions in the same dialect. Its integer result and NULL facts are derived from those operands; binding does not perform the search. |
| Binary or unary operation | An operator enum and required `left`/`right` or `operand`. MySQL and PostgreSQL truth tests (`IS TRUE`, `IS FALSE`, `IS UNKNOWN`, and their negations) are unary enum cases with a non-NULL predicate result. |
| Function | `FunctionCall` has a registered or unresolved function reference and ordered value arguments. |
| PostgreSQL COALESCE and NULLIF | `Coalesce` retains ordered alternatives; `NullIf` has required `left` and `right` comparison operands. These are language operations. |
| PostgreSQL GREATEST and LEAST | `Extremum` has an `ExtremumKind selection` and nonempty ordered `arguments` converted to the common result type. |
| MySQL and SQLite conditional functions | COALESCE and NULLIF are `FunctionCall` objects referencing their registered `FunctionSignature`, including application replacements. |
| Aggregate over values | `AggregateCall` retains value arguments, ALL/DISTINCT, optional input ordering, and FILTER. |
| Aggregate over rows, such as `count(*)` | `AllRowsAggregate` retains the function reference and optional FILTER; it has no value-argument list. |
| Ordered-set aggregate | `OrderedSetCall` separates `directArguments` from the required `withinGroup` row ordering and optional FILTER. Both argument groups participate in signature resolution. |
| Window function | `WindowCall` retains the invocation and its window specification or named window reference. |
| JSON membership | MySQL `JsonMembership` has a required searched `value` and JSON `array` input. Neither is evaluated during binding. |
| CASE | `SimpleCase` requires a selector; `SearchedCase` requires predicates. Each retains ordered branches and its optional ELSE value. |
| Row constructor | `RowExpression` retains ordered field `items`. PostgreSQL allows zero or more fields; MySQL and SQLite require at least two. |
| IN with value candidates | `InList` has a searched `value`, ordered `choices`, and `negated` flag. Known row widths must agree; SQLite permits an empty candidate list. |
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
SQLite MIN and MAX with one argument are `AggregateCall`; with two or more arguments
they are `FunctionCall`. PostgreSQL GREATEST and LEAST retain their non-NULL selection
semantics independently of registered functions with similar names.
MySQL temporal arithmetic derives its result family from the temporal input and
interval fields: a DATE with calendar-only units remains a date, while time fields
promote it to datetime. TIME plus calendar fields follows the selected release's
rules. `DateArithmeticRules` retains that distinction; modern releases also infer
dynamic parameter families from the interval's required inputs. Literal text is
retained without parsing or evaluating its date, time, or interval value.

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

Routine argument declarations expose a `TypeDescriptor` or a `ColumnTypeReference`, an optional parameter name, a direction enum, and the set-valued type flag. A column-type reference retains its qualified name and the matching `ColumnBinding` when the supplied Schema resolves it; it never reads a column value. Missing tables or columns produce binding diagnostics. Routine targets describe the requested identity; binding does not remove definitions or execute dependency checks.

`ParameterMode::Implicit` remains distinct from an explicit `Input` mode because PostgreSQL procedure lookup uses that distinction. Aggregate arguments use the narrower `AggregateInputMode`, which has no output mode. An ordered-set aggregate with a variadic direct parameter requires exactly one variadic aggregated parameter with the same declared type. These rules apply to constructors and immutable statement transformations as well as SQL binding. The lookup semantics follow the PostgreSQL [DROP FUNCTION](https://www.postgresql.org/docs/17/sql-dropfunction.html), [DROP PROCEDURE](https://www.postgresql.org/docs/17/sql-dropprocedure.html), and [DROP AGGREGATE](https://www.postgresql.org/docs/17/sql-dropaggregate.html) definitions.

MySQL account removal keeps `CURRENT_USER` as the authenticated-account symbol for the consumer to resolve from its execution context. It does not read the current account, change active roles, disconnect sessions, or mutate the supplied Schema. Role removal accepts named accounts; it cannot substitute a session-principal symbol for a role name. MySQL 5.6 account removal has no `IF EXISTS` option, and immutable changes preserve that release-specific rule.
