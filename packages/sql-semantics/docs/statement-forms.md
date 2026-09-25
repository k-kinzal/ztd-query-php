# Statement forms

This catalogue lists, for each SQL form, the Statement class that
`Binder::bind()` returns and the structure that class carries. The concrete class
specifies which operands exist; `kind` is a `StatementKind` enum derived from that
class. Classes with different required inputs have different constructors, and
unrelated operands are not represented by empty or nullable fields. See
[Binder](binder.md) for binding, relations, and expressions, and
[statements and serialization](statements.md) for transformations.

A row without a prefix applies to MySQL, PostgreSQL, and SQLite. A `MySQL:`,
`PostgreSQL:`, or `SQLite:` prefix names the dialects of the examples that follow
it, and a release such as `MySQL 8.0+:` names the first grammar release that
provides the form. Class names are short when unique. Where two classes share a
short name, the name is qualified relative to `SqlSemantics\Model\Statement`, for
example `Definition\MySql\Program\CreateFunctionStatement` and
`Definition\Routine\CreateFunctionStatement`.

- [Queries](#queries)
- [Data modification](#data-modification)
- [Configuration and session](#configuration-and-session)
- [Transactions](#transactions)
- [Accounts, roles and privileges](#accounts-roles-and-privileges)
- [Schema definition — tables, indexes, views](#schema-definition--tables-indexes-views)
- [Schema definition — other objects](#schema-definition--other-objects)
- [Stored programs](#stored-programs)
- [Server inspection (SHOW)](#server-inspection-show)
- [Replication and server administration](#replication-and-server-administration)
- [Maintenance and loading](#maintenance-and-loading)

## Queries

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `SELECT id FROM users WHERE score > 0` | `BoundSelect` | Ordered `outputs`, optional input `from`, row predicate `where`, grouping, `having`, duplicate-elimination `quantifier`, ordering, pagination, named `windows`, and `locks`. `groupBy` lists keys and grouping-set constructs: `Rollup`, `Cube`, `GroupingSets` and `EmptyGroupingSet` (PostgreSQL, with `distinctGroupingSets` for GROUP BY DISTINCT), MySQL `WITH ROLLUP` or `ROLLUP(...)` as one `Rollup`, MySQL 8.3+ `CUBE(...)`, and MySQL 5.x `DescendingGroupKey`s; MySQL 8.3+ `qualify` is the QUALIFY predicate. |
| PostgreSQL and SQLite: `VALUES (1), (2)`; MySQL: `VALUES ROW(1), ROW(2)` | `ValuesStatement` | Nonempty `rows` of equal width; result columns and common types are derived from those rows. MySQL keeps a trailing locking clause as `locks`; PostgreSQL rejects FOR UPDATE/SHARE on VALUES (`values-lock`). |
| MySQL and PostgreSQL: `TABLE users` | `TableStatement` | A required table or CTE reference; result columns are derived from that declaration; optional ordering, pagination and row `locks` (`FOR UPDATE`, MySQL `LOCK IN SHARE MODE`, PostgreSQL `FOR KEY SHARE` and `FOR NO KEY UPDATE`). |
| `SELECT id FROM users UNION ALL SELECT id FROM incoming` | `CompoundStatement` | Required `left`, `right`, and `setOperator`; compatible result widths and common types by position. A MySQL locking clause written after a set operation (`SELECT a FROM t UNION SELECT a FROM u FOR UPDATE`) locks only the last query block, as the server does, so it becomes the `locks` of the rightmost operand and is written back inside that operand's parentheses; PostgreSQL rejects it (`set-operation-lock`). |
| PostgreSQL: `SELECT * FROM ROWS FROM (f(1) AS (a int), g(2)) WITH ORDINALITY AS r` | `BoundSelect` with a `RowsFromRelation` input | A `RowsFromTable` of ordered `RowsFromFunction`s (invocation and optional `DefinedColumn` list) with an `ordinality` flag, the relation alias and column aliases. Function columns come first (NULL-padded when several functions), then a not-null bigint `ordinality` column. A single function with WITH ORDINALITY or a column definition list binds to the same structure and is written as ROWS FROM; misplaced or repeated column definition lists are `function-table-columns` violations. |
| MySQL: `SELECT a, b INTO @x, @y FROM t` | `SelectIntoVariablesStatement` | The query and one user variable per selected column; INTO is accepted only after the last query block, never inside a subquery. |
| MySQL: `SELECT a FROM t INTO OUTFILE 'a.csv' FIELDS TERMINATED BY ','` | `SelectIntoOutfileStatement` | The query, the file literal, and the optional character set with FIELDS and LINES separators. |
| MySQL: `SELECT a FROM t INTO DUMPFILE 'a.bin'` | `SelectIntoDumpfileStatement` | The query and the file literal. |
| PostgreSQL: `SELECT a INTO TEMP copied FROM t` | `SelectIntoTableStatement` | The query, the new table name, and its persistence (permanent, temporary or unlogged); INTO belongs to the first SELECT, and a temporary table names no schema other than pg_temp. |
| `EXPLAIN UPDATE users SET score=1` | `ExplainStatement` | Required nested `statement` and classified EXPLAIN options. |
| MySQL 8.2+: `EXPLAIN FOR DATABASE sales SELECT * FROM orders` | `ExplainInDatabaseStatement` | The database in which unqualified names of the explained query or data change resolve, the explained statement, and the plan options. |
| MySQL: `EXPLAIN FORMAT=JSON FOR CONNECTION 42` | `ExplainConnectionStatement` | A required numeric `connection` identifier and a `MySqlFormat`; the plan belongs to the statement that connection is running. |
| MySQL: `HANDLER t OPEN AS h` | `OpenHandlerStatement` | The resolved table reference, with the handler alias when given. |
| MySQL: `HANDLER h CLOSE` | `CloseHandlerStatement` | The handler reference. |
| MySQL: `HANDLER h READ FIRST WHERE a > 1 LIMIT 5` | `ReadHandlerStatement` | Handler, `HandlerScan` (`FIRST` or `NEXT`), optional condition bound against the handler's table, and optional row window. |
| MySQL: `HANDLER h READ ix NEXT` | `ReadHandlerIndexStatement` | Handler, index name, `IndexStep` (`FIRST`, `NEXT`, `PREV`, `LAST`), optional condition and row window. |
| MySQL: `HANDLER h READ ix >= (1, 2)` | `ReadHandlerKeyStatement` | Handler, index name, `KeyComparison`, a nonempty key value list (DEFAULT is diagnosed), optional condition and row window. |

## Data modification

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `INSERT INTO users(id,score) VALUES (1,10)` | `InsertValuesStatement` | `insertion` destination mapping and nonempty `rows`; row widths agree with known destinations. |
| MySQL 8.0.19+: `INSERT INTO t (a) VALUES (1) AS n(x) ON DUPLICATE KEY UPDATE a = x` | `InsertValuesStatement`, `InsertSetStatement` | A row alias on `MySqlInsertion::$rowAlias` (`RowAlias`): a `ProposedRow` named by the alias with one column per inserted column, renamed by the optional column aliases. ON DUPLICATE KEY UPDATE references resolve to it beside the target. INSERT ... SELECT has no row alias; a table-name alias, an alias count other than the inserted column count, or a repeated alias is `InvalidSql` (`insert-row-alias`). |
| `INSERT INTO users(id,score) SELECT id,score FROM incoming` | `InsertSelectStatement` | `insertion` and a required `query`. The query supplies the input columns. |
| PostgreSQL and SQLite: `INSERT INTO users DEFAULT VALUES` | `InsertDefaultValuesStatement` | A destination whose omitted columns obtain defaults or generated values. No row or SELECT payload. |
| MySQL: `INSERT INTO users SET id=1, score=10` | `InsertSetStatement` | `insertion` and nonempty ordered `writes`. |
| `UPDATE users SET score=score+1 WHERE id=1` | `UpdateTableStatement` | A single `target`, nonempty `writes`, and optional row predicate. |
| PostgreSQL and SQLite: `UPDATE users SET score=incoming.score FROM incoming WHERE users.id=incoming.id` | `UpdateFromStatement` | Separate required `target` and `from` inputs; ordered writes and predicate. |
| MySQL: `UPDATE users JOIN incoming ON users.id=incoming.id SET users.score=incoming.score` | `UpdateJoinedStatement` | Required joined `from`, affected `targets`, and ordered writes. |
| `DELETE FROM users WHERE id=1` | `DeleteTableStatement` | One required `target` and optional predicate. |
| PostgreSQL: `DELETE FROM users USING incoming WHERE users.id=incoming.id` | `DeleteUsingStatement` | Separate required deletion `target` and read input `using`. |
| MySQL: `DELETE users FROM users JOIN incoming ON users.id=incoming.id` | `DeleteJoinedStatement` | Joined input and explicit deletion targets. |
| PostgreSQL: `MERGE INTO users USING incoming ON users.id=incoming.id WHEN MATCHED THEN DELETE` | `MergeStatement` | Required target, input, match condition, and ordered, typed actions. |
| MySQL: `TRUNCATE users` | `TruncateTableStatement` | One required physical `table`; there is no predicate or query input. |
| PostgreSQL: `TRUNCATE ONLY users, incoming RESTART IDENTITY CASCADE` | `TruncateRelationsStatement` | Nonempty explicit `tables`, an `IdentityReset` policy, and a `ReferencingTables` policy for foreign-key dependencies. Binding records these requests without removing rows, resetting sequences, or expanding runtime effects. |

INSERT, UPDATE, DELETE, and MERGE retain ordered RETURNING `outputs` where the
selected language provides them. Their `affectedTables()` method identifies write
targets. REPLACE uses an insertion form with `InsertMode::Replace`. It does not lose
the distinction between explicit rows, a source query, and column assignments.

## Configuration and session

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `SET LOCAL work_mem='64MB'`; MySQL: `SET @x=123`, `SET NAMES utf8mb4 COLLATE utf8mb4_bin, CHARACTER SET DEFAULT` | `SetStatement` | Nonempty `settings`: an `AssignedSetting` with required expressions, a `DefaultSetting` requesting the parameter default, a `CurrentSetting` copying current state, or an `AssignedUserVariable` with a required `target` reference and one `value`. A declared variable target retains its supplied `VariableDefinition`. MySQL `NAMES` and `CHARACTER SET` (also `CHARSET`, `CHAR SET`) are `ConnectionNames` and `ConnectionCharacterSet` items that name a character set (null for DEFAULT) and an optional collation; `NAMES = value` is diagnosed. A GLOBAL, PERSIST, PERSIST_ONLY, or SESSION keyword carries over to later unscoped variables, a qualified name has one prefix other than GLOBAL, LOCAL, or SESSION, and a single identifier value is a `ConfigurationIdentifier` whether or not it is quoted. |
| PostgreSQL: `RESET work_mem`, `RESET ALL`; MySQL: `RESET PERSIST IF EXISTS max_connections`, `RESET PERSIST` | `ResetSettingStatement`, `ResetAllSettingsStatement`, `ResetAllPersistedVariablesStatement` | A required named parameter; a MySQL persisted variable also carries its existence policy. `ResetAllSettingsStatement` resets all session parameters and `ResetAllPersistedVariablesStatement` removes all persisted variables; neither has a name payload. |
| SQLite: `PRAGMA main.cache_size` | `ReadPragmaStatement` | Qualified `name`; no assigned value. |
| SQLite: `PRAGMA main.cache_size=-2000` | `AssignPragmaStatement` | Qualified `name` and one required argument, classified as a numeric, text, or identifier argument. |
| PostgreSQL: `ALTER SYSTEM SET work_mem = '64MB'` | `AlterSystemSetStatement` | One assigned or DEFAULT `setting`; ALTER SYSTEM has no FROM CURRENT form. |
| PostgreSQL: `ALTER SYSTEM RESET work_mem` | `AlterSystemResetStatement` | One `ResetSetting`. |
| PostgreSQL: `ALTER SYSTEM RESET ALL` | `AlterSystemResetAllStatement` | No operands; removes every parameter from the automatic configuration file. |
| PostgreSQL: `SHOW work_mem` | `ShowSettingStatement` | Nonempty parameter `name` parts; `SHOW TIME ZONE`, `SHOW TRANSACTION ISOLATION LEVEL` and `SHOW SESSION AUTHORIZATION` name `timezone`, `transaction_isolation` and `session_authorization`. One not-null text result column labeled with the name. |
| PostgreSQL: `SHOW ALL` | `ShowAllSettingsStatement` | No operands (a parameter named `all` binds here too); result columns `name`, `setting` and nullable `description`, all text. |
| MySQL: `USE app` | `UseDatabaseStatement` | The required `database` name. The supplied Schema keeps its default schema; supply a new snapshot to bind later statements against the selected database. |
| SQLite: `ATTACH DATABASE 'archive.db' AS archive` | `AttachDatabaseStatement` | A `database` file expression, the `schema` name expression, and an optional `encryptionKey`. |
| SQLite: `DETACH DATABASE archive` | `DetachDatabaseStatement` | The attached `schema` name expression. |
| PostgreSQL: `PREPARE s(int) AS SELECT $1` | `PrepareQueryStatement` | Required nested statement, name and declared parameter types; the SELECT's parameter has the declared integer type. |
| MySQL: `PREPARE s FROM @sql` | `PrepareTextStatement` | Name and a required user-variable reference or text literal that supplies SQL at execution time. |
| PostgreSQL: `EXECUTE s(1, 2)` | `ExecuteQueryStatement` | Prepared-query name and ordered argument expressions. |
| PostgreSQL: `CREATE TEMP TABLE copied (a) AS EXECUTE s(1) WITH NO DATA` | `CreateTableFromExecuteStatement` | The target name, column aliases, table options and persistence, `ifNotExists`, `withData`, and the prepared-query name with its ordered arguments. |
| MySQL: `EXECUTE s USING @x, @y` | `ExecuteUsingStatement` | Prepared-statement name and ordered user-variable references. |
| PostgreSQL: `DEALLOCATE s`, `DEALLOCATE ALL` | `DeallocateStatement`, `DeallocateAllStatement` | One required prepared-statement name, or all prepared statements. |
| PostgreSQL: `DECLARE cur CURSOR FOR SELECT id FROM users` | `DeclareCursorStatement` | Cursor name, required `query`, scrollability, sensitivity, transfer format, and lifetime. |
| PostgreSQL: `FETCH BACKWARD ALL FROM cur` | `FetchCursorStatement` | Cursor name and `RemainingRows(Backward)` movement. |
| PostgreSQL: `MOVE ABSOLUTE -2 FROM cur` | `MoveCursorStatement` | Cursor name and `PositionedRow(Absolute, -2)`. The position is described, not evaluated. |
| PostgreSQL: `CLOSE cur`, `CLOSE ALL` | `CloseCursorStatement`, `CloseAllCursorsStatement` | One named cursor or all cursors, respectively. |
| PostgreSQL: `LISTEN events`, `UNLISTEN events`, `UNLISTEN *` | `ListenStatement`, `UnlistenStatement`, `UnlistenAllStatement` | A required channel name for named operations; the all-channels operation has no name payload. |
| PostgreSQL: `NOTIFY events, 'changed'` | `NotifyStatement` | Required `channel` and optional text-literal `payload`. |
| PostgreSQL: `DISCARD PLANS` | `DiscardStatement` | A `DiscardResource` enum selecting plans, sequences, temporary tables, or all session resources. |
| MySQL: `DO @x := 1, @x + 2` | `DoExpressionsStatement` | Nonempty ordered scalar `expressions`, with their ordinary types, references, and dependencies. The operation requests evaluation with no returned result set; Binder does not perform it. |

## Transactions

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `BEGIN`; MySQL: `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`; PostgreSQL: `BEGIN ISOLATION LEVEL SERIALIZABLE`; SQLite: `BEGIN IMMEDIATE` | `BeginTransactionStatement` | An optional SQLite `Mode` (`Deferred`, `Immediate`, or `Exclusive`) and `Characteristics` with optional `isolation`, `access`, and `deferrable` requests and MySQL's `consistentSnapshot` flag. |
| `COMMIT`, `ROLLBACK`; MySQL and PostgreSQL: `COMMIT AND CHAIN`; MySQL: `ROLLBACK AND NO CHAIN NO RELEASE` | `CommitTransactionStatement`, `RollbackTransactionStatement` | A `Chaining` policy (`Default`, `Chain`, or `NoChain`) and MySQL's `Release` policy (`Default`, `Release`, or `NoRelease`). |
| `SAVEPOINT sp1`, `RELEASE SAVEPOINT sp1`, `ROLLBACK TO SAVEPOINT sp1` | `SavepointStatement`, `ReleaseSavepointStatement`, `RollbackToSavepointStatement` | One required savepoint `name`; each class identifies its own operation. |
| PostgreSQL: `PREPARE TRANSACTION 'tx1'`, `COMMIT PREPARED 'tx1'`, `ROLLBACK PREPARED 'tx1'` | `PrepareTransactionStatement`, `CommitPreparedStatement`, `RollbackPreparedStatement` | One required text-literal `transactionId` naming the two-phase transaction. |
| MySQL: `SET TRANSACTION READ ONLY` | `SetNextTransactionStatement` | Optional `isolation` and `access` enums, with at least one required; applies to the next transaction. |
| MySQL: `SET GLOBAL TRANSACTION ISOLATION LEVEL SERIALIZABLE` | `SetDefaultTransactionStatement` | Required `DefaultScope`, plus an isolation and/or access request. SESSION, GLOBAL, PERSIST, and PERSIST_ONLY remain distinct; LOCAL denotes SESSION. |
| PostgreSQL: `SET TRANSACTION READ ONLY, DEFERRABLE` | `SetCurrentTransactionStatement` | Nonempty ordered `modes` containing only `Isolation`, `Access`, or `Deferrability` enums, plus outer SET `Locality`. |
| PostgreSQL: `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY` | `SetSessionTransactionStatement` | The same typed mode roles, targeting session defaults for subsequent transactions. |
| PostgreSQL: `SET TRANSACTION SNAPSHOT 'snapshot-id'` | `SetTransactionSnapshotStatement` | Required `snapshot` text literal and SET `Locality`; the snapshot identifier is retained without loading snapshot contents. |
| PostgreSQL: `SET CONSTRAINTS ALL DEFERRED` | `SetAllConstraintsStatement` | A `ConstraintTiming` enum; the selection is all deferrable constraints. |
| PostgreSQL: `SET CONSTRAINTS app.orders_fk, items_fk DEFERRED` | `SetNamedConstraintsStatement` | Nonempty `constraints` list of names with at most three components and a `ConstraintTiming`; `SET CONSTRAINTS ALL` stays `SetAllConstraintsStatement`. |
| PostgreSQL: `LOCK ONLY users IN SHARE MODE NOWAIT` | `LockRelationsStatement` | Nonempty ordered `tables`, one `PostgreSqlLockMode`, and a `nowait` policy. Descendant exclusion is retained on each target. |
| MySQL: `LOCK TABLES users AS u READ LOCAL, incoming WRITE` | `LockTablesStatement` | Nonempty `locks`; each `MySqlTableLock` owns its required table occurrence, alias, and independent `MySqlLockMode`. |
| MySQL: `UNLOCK TABLES` | `UnlockTablesStatement` | No operands; releases the table locks held by the session. |
| MySQL: `XA START 'g', 'b', 42 JOIN` | `XaStartStatement` | Required `transactionId` and `StartMode` (`NewBranch`, `Join`, or `Resume`). `XA BEGIN` produces the same start operation. |
| MySQL: `XA END 'g' SUSPEND FOR MIGRATE` | `XaEndStatement` | Required `transactionId` and `EndMode` (`End`, `Suspend`, or `Migrate`). |
| MySQL: `XA PREPARE 'g'`, `XA ROLLBACK 'g'` | `XaPrepareStatement`, `XaRollbackStatement` | Required `transactionId`; each class fixes its operation and has no start or commit policy. |
| MySQL: `XA COMMIT 'g' ONE PHASE` | `XaCommitStatement` | Required `transactionId` and `CommitMode` (`Prepared` or `OnePhase`). |
| MySQL: `XA RECOVER CONVERT XID` | `XaRecoverStatement` | `RecoveryEncoding` and four derived result columns; no input transaction identifier. |

Transaction-setting statements distinguish the current transaction, the next
transaction, and defaults for future transactions. PostgreSQL mode requests preserve
their order, including repeated requests. `Locality` retains the outer SET lifetime,
separately from which transaction settings the statement targets. These operations
describe requested changes; the supplied Schema and live transaction state are not
changed or consulted. In particular, snapshot existence and execution-state
requirements belong to the consumer executing the request.

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

## Accounts, roles and privileges

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CREATE USER IF NOT EXISTS 'a'@'h' IDENTIFIED BY 'x' DEFAULT ROLE r REQUIRE SSL ACCOUNT LOCK` | `CreateUsersStatement` | Nonempty `accounts`: each an `AccountDefinition` (account, optional first-factor identification, up to two further factors) or an `InitialAuthenticationDefinition` (passwordless plugin plus temporary credential). Shared `ifNotExists`, `defaultRoles`, `requirement` (`ConnectionSecurity` or distinct `CertificateRequirements`), `resourceLimits`, `policies` (`AccountPolicy` or ranged `AccountLimit`), and `annotation`. Credentials are literals recorded as written; 5.6 and 5.7 accept only their own forms. Returns generated-password rows when a random password is requested. |
| MySQL: `ALTER USER a IDENTIFIED BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD, b ACCOUNT LOCK` | `AlterUsersStatement` | Nonempty per-account `alterations`: `CredentialChange`, `AuthenticationChange`, `PluginChange`, `AccountTarget`, `OldPasswordDiscard`, `FactorChange` (ADD or MODIFY of distinct factors 2 and 3), or `FactorRemoval`; shared `ifExists`, requirement, limits, policies, and annotation. `USER()` stands alone as `ClientAccount::Connected`. The 5.6 form lists accounts with the single `PASSWORD EXPIRE` policy. |
| MySQL: `ALTER USER a DEFAULT ROLE r1, 'r2'@'h'` | `AlterDefaultRolesStatement` | One account and a nonempty list of named default roles; MySQL 8 only. |
| MySQL: `ALTER USER CURRENT_USER DEFAULT ROLE ALL` | `AlterDefaultRolePolicyStatement` | One account and a `DefaultRolePolicy` (`ALL` or `NONE`); MySQL 8 only. |
| MySQL: `ALTER USER a 2 FACTOR INITIATE REGISTRATION` | `InitiateRegistrationStatement` | One account (including `USER()`) and the `AuthenticationFactor` (2 or 3) being registered. |
| MySQL: `ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'` | `FinishRegistrationStatement` | Account, factor, and the challenge response as a text or hexadecimal literal, never verified. |
| MySQL: `ALTER USER a 3 FACTOR UNREGISTER` | `UnregisterFactorStatement` | Account and the factor whose registered device is discarded. |
| MySQL: `RENAME USER a TO 'b'@'h', CURRENT_USER TO c` | `RenameUsersStatement` | Nonempty ordered `AccountRename` pairs; either side may be `CurrentAccount::Authenticated`. |
| MySQL: `DROP USER CURRENT_USER, 'reader'@'localhost'` | `DropUsersStatement` | Nonempty `accounts`: named `AccountName` values or `CurrentAccount::Authenticated`. Usernames and optional hosts remain separate. |
| MySQL: `CREATE ROLE IF NOT EXISTS reader, 'writer'@'h'` | `CreateRolesStatement` | Nonempty named `roles` and `ifNotExists`; MySQL 8 only. |
| MySQL: `DROP ROLE IF EXISTS reader` | `Definition\MySql\DropRolesStatement` | Nonempty named `roles` and `ifExists`; each role has its own username and optional host. |
| MySQL 5.7+: `SET PASSWORD FOR 'u'@'localhost' = 'new'` | `SetPasswordStatement` | Required `account` and `password` text literal; optional `currentPassword` verification operand and `retainCurrentPassword` flag on releases with those clauses. |
| MySQL 8.0+: `SET PASSWORD TO RANDOM` | `SetRandomPasswordStatement` | Required `account`, optional verification operand, and retention flag; no supplied new password. Four derived result columns describe the requested server output. |
| MySQL 5.6: `SET PASSWORD = '*encoded'` | `SetPasswordHashStatement` | Required `account` and `hash` text literal. The encoded value is not interpreted. |
| MySQL 5.6: `SET PASSWORD = PASSWORD('new')` | `SetDerivedPasswordStatement` | Required `account`, cleartext `password` operand, and `PasswordDerivation` (`Configured` or `Pre41`). The consumer performs the requested hashing. |
| MySQL 5.6: `SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'` | `SetAccountOptionsStatement` | Two or more ordered, individually typed credential or variable operations. Each variable operation contains one assignment. |
| MySQL: `SET ROLE NONE`, `SET ROLE DEFAULT`, `SET ROLE ALL` | `SetRolePolicyStatement` | A required `SessionRolePolicy` enum; no assignment or role-name payload. |
| MySQL: `SET ROLE 'reader'@'localhost'` | `SetExplicitRolesStatement` | Nonempty `roles`, each an `AccountName` with separate `username` and optional `host`. |
| MySQL: `SET ROLE ALL EXCEPT 'writer'` | `SetRolesExceptStatement` | Nonempty `excludedRoles`; the operation identifies the complementary selection. |
| MySQL: `SET DEFAULT ROLE ALL TO 'alice'` | `SetDefaultRolePolicyStatement` | Required `DefaultRolePolicy` (`None` or `All`) and nonempty recipient `accounts`. |
| MySQL: `SET DEFAULT ROLE 'reader' TO 'alice', 'bob'` | `SetDefaultRolesStatement` | Separate nonempty `roles` and recipient `accounts`. This records account defaults rather than current session activation. |
| MySQL: `GRANT SELECT, UPDATE (a) ON t TO u WITH GRANT OPTION AS g WITH ROLE r` | `Definition\MySql\Privilege\GrantPrivilegesStatement` | Nonempty `privileges` (`StaticPrivilege`, `ColumnPrivilege`, or global-only `DynamicPrivilege`), one level (`PrivilegeScope`, `DatabaseScope`, resolved `TableReference`, or `RoutineTarget`), nonempty grantees, `withGrantOption`, optional MySQL 8 `Grantor`, and legacy credentials, requirement, and resource limits. Each privilege must be defined at its level. |
| MySQL: `GRANT ALL PRIVILEGES ON app.* TO u` | `GrantAllPrivilegesStatement` | One level and nonempty grantees with the same options as a named grant, without a privilege list. |
| MySQL: `GRANT PROXY ON 'p'@'h' TO u WITH GRANT OPTION` | `GrantProxyStatement` | The proxied account, nonempty grantees (legacy releases may attach one credential each), and `withGrantOption`. |
| MySQL: `GRANT reader, 'writer'@'h' TO u WITH ADMIN OPTION` | `Definition\MySql\Privilege\GrantRolesStatement` | Nonempty named roles, nonempty recipients, and `withAdminOption`; MySQL 8 only. |
| MySQL: `REVOKE IF EXISTS SELECT ON app.* FROM u IGNORE UNKNOWN USER` | `Definition\MySql\Privilege\RevokePrivilegesStatement` | Nonempty privileges at one level, nonempty accounts, and the MySQL 8 `ifExists` and `ignoreUnknownUser` policies. |
| MySQL: `REVOKE ALL ON FUNCTION app.f FROM u` | `RevokeAllPrivilegesStatement` | One level and nonempty accounts losing every privilege of that level; same policies. |
| MySQL: `REVOKE ALL PRIVILEGES, GRANT OPTION FROM u` | `RevokeAllGrantsStatement` | Nonempty accounts losing every grant at every level; same policies. |
| MySQL: `REVOKE PROXY ON p FROM u` | `RevokeProxyStatement` | The proxied account and nonempty accounts losing the proxy privilege; same policies. |
| MySQL: `REVOKE reader FROM u` | `Definition\MySql\Privilege\RevokeRolesStatement` | Nonempty named roles and nonempty accounts; MySQL 8 only; same policies. |
| PostgreSQL: `CREATE ROLE app WITH LOGIN CONNECTION LIMIT 10 IN ROLE staff` | `CreateRoleStatement` | Required concrete `name`, the definition `keyword` (ROLE, USER, or GROUP, which changes the LOGIN default), and ordered `options`, at most one per property: `RoleAttribute` (SUPERUSER, CREATEDB, CREATEROLE, INHERIT, LOGIN, REPLICATION, BYPASSRLS with their NO forms), `RolePassword` or `ClearedPassword`, `ConnectionLimit`, `RoleValidity`, `RoleMembers` (ROLE, USER), `RoleMemberships` (IN ROLE, IN GROUP), `RoleAdmins`, and `RoleSystemId`. |
| PostgreSQL: `ALTER ROLE app WITH NOLOGIN PASSWORD NULL USER alice` | `AlterRoleStatement` | Required `role` (named or session role) and ordered `options` limited to attributes, credentials, `ConnectionLimit`, `RoleValidity`, and a `RoleMembers` list that adds members. ALTER USER binds to the same operation. |
| PostgreSQL: `ALTER ROLE app RENAME TO crew` | `RenameRoleStatement` | Required concrete `role` and `newName`; ALTER USER and ALTER GROUP renames bind to the same operation. |
| PostgreSQL: `ALTER GROUP staff ADD USER alice, CURRENT_USER` | `AddGroupMembersStatement` | Required `group` and nonempty `members`, each a `NamedRole` or `SessionRole`. |
| PostgreSQL: `ALTER GROUP staff DROP USER alice` | `DropGroupMembersStatement` | Required `group` and nonempty `members`, each a `NamedRole` or `SessionRole`. |
| PostgreSQL: `ALTER ROLE ALL IN DATABASE shop SET search_path TO app` | `AlterRoleSetStatement` | Required `role` (`NamedRole`, `SessionRole`, or `AllRoles`), optional `database`, and one session-scoped `AssignedSetting`, `DefaultSetting`, or `CurrentSetting`; transaction characteristics are diagnosed. |
| PostgreSQL: `ALTER USER app IN DATABASE shop RESET work_mem` | `AlterRoleResetStatement` | Required `role` selection, optional `database`, and one session-scoped `ResetSetting`. |
| PostgreSQL: `ALTER ROLE app RESET ALL` | `AlterRoleResetAllStatement` | Required `role` selection and optional `database`; no parameter name because every stored default is removed. |
| PostgreSQL: `DROP ROLE IF EXISTS alice, bob` | `Definition\PostgreSql\Role\DropRolesStatement` | Nonempty concrete `roles` and `ifExists`; DROP USER and DROP GROUP bind to the same operation. |
| PostgreSQL: `GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob` | `Definition\PostgreSql\Privilege\GrantPrivilegesStatement` | Nonempty `privileges` (`ObjectPrivilege` or `ColumnPrivilege`, ALL PRIVILEGES standing alone), one typed `target` (`TableTargets`, `SchemaObjectTargets` for SEQUENCE, DOMAIN, TYPE, `ServerObjectTargets` for DATABASE, FOREIGN DATA WRAPPER, FOREIGN SERVER, LANGUAGE, SCHEMA, TABLESPACE, `RoutineTargets` with optional signatures, `LargeObjectTargets`, `ParameterTargets`, or `SchemaScopedTargets` for ALL ... IN SCHEMA), nonempty `grantees` including `PublicRole`, `grantOption`, and optional `grantor`. Privileges outside the object class domain are diagnosed. |
| PostgreSQL: `REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE` | `Definition\PostgreSql\Privilege\RevokePrivilegesStatement` | The same `privileges`, `target`, and `grantees` as a grant, plus `grantOptionOnly`, optional `grantor`, and `DropBehavior`. |
| PostgreSQL: `GRANT staff TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob` | `Definition\PostgreSql\Privilege\GrantRolesStatement` | Nonempty concrete `roles`, nonempty `grantees` (named or session roles), ordered `RoleGrantOption` values for ADMIN, INHERIT, and SET, and optional `grantor`. |
| PostgreSQL: `REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT` | `Definition\PostgreSql\Privilege\RevokeRolesStatement` | Nonempty concrete `roles`, nonempty `grantees`, optional single `option` revoked instead of the membership, optional `grantor`, and `DropBehavior`. |
| PostgreSQL: `ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC` | `GrantDefaultPrivilegesStatement` | Nonempty `privileges` without column lists, one `DefaultPrivilegeTarget` (TABLES, SEQUENCES, FUNCTIONS with ROUTINES as a synonym, TYPES, SCHEMAS), nonempty `grantees`, `grantOption`, optional defining `roles`, and optional `schemas`, which SCHEMAS cannot carry. |
| PostgreSQL: `ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON FUNCTIONS FROM alice CASCADE` | `RevokeDefaultPrivilegesStatement` | The same operands as a default grant, plus `grantOptionOnly` and `DropBehavior`. |
| PostgreSQL: `DROP OWNED BY alice, CURRENT_USER CASCADE` | `DropOwnedStatement` | Nonempty `owners`, each a `NamedRole` or `SessionRole`, and `DropBehavior` for dependent objects. |
| PostgreSQL: `REASSIGN OWNED BY alice TO SESSION_USER` | `ReassignOwnedStatement` | Nonempty source `owners` and required `newOwner`, each retaining a named or session role identity. |

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

MySQL role operations are in `Model\Statement\Configuration\Role`. Their
account names preserve case and separate host qualification; an omitted host
refers to `%`. A quoted name such as `'NONE'` is a named role, distinct from the
`None` policy. Binding records the requested selection and recipients without
reading grants, activating roles, or changing account defaults. These statements
have no variable assignments. Each owns immutable replacements for its required
policy, role list, exclusion list, or recipient list.

MySQL account removal keeps `CURRENT_USER` as the authenticated-account symbol for the consumer to resolve from its execution context. It does not read the current account, change active roles, disconnect sessions, or mutate the supplied Schema. Role removal accepts named accounts; it cannot substitute a session-principal symbol for a role name. MySQL 5.6 account removal carries no existence policy, and immutable changes preserve that release-specific rule.

Ownership statements describe object selection by role. `NamedRole` holds a
case-sensitive name; `SessionRole` identifies `CURRENT_ROLE`, `CURRENT_USER`, or
`SESSION_USER` without reading the session's username. Removal selects owned
objects and associated privileges with a dependent-object policy. Reassignment
requires a new owner and has no removal policy. `withOwners()` replaces the
nonempty source selection; reassignment's `withNewOwner()` replaces its required
destination. Binding records these requests against the supplied state without
enumerating owned objects, applying privilege changes, or changing the session.

## Schema definition — tables, indexes, views

### Tables

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `CREATE TABLE t(id INTEGER DEFAULT 1)`; PostgreSQL: `CREATE TABLE t(id INTEGER, LIKE src INCLUDING DEFAULTS, EXCLUDE USING gist (id WITH =)) INHERITS (a)`, `CREATE TABLE m(id INTEGER) PARTITION BY RANGE (id)` | `CreateTableStatement` | `definition.table`: ordered columns, typed value sources, constraints, indexes, and dialect-specific properties. PostgreSQL adds `templates` lists each LIKE clause with its source table, INCLUDING/EXCLUDING selections, and its position among the declared columns; `exclusions` lists EXCLUDE constraints; `definition.table.properties` (`PostgreSqlProperties`) carries `parents` (distinct INHERITS tables) and `partitioning` (`PartitionScheme`: RANGE, LIST, or HASH and ordered column or expression keys with optional collation and operator class). A partitioned table cannot inherit or take storage parameters, and LIST takes one key. |
| `CREATE TABLE t AS SELECT id FROM users`; MySQL: `CREATE TABLE t IGNORE AS SELECT id FROM users` | `CreateTableAsStatement` | The target name, source query, and declaration options; on MySQL a table without its own column declarations also carries `duplicates` (`DuplicateRows::Ignore` or `Replace`, or null when a duplicate key fails the statement), which only MySQL accepts. |
| MySQL: `CREATE TABLE t (id INT PRIMARY KEY) ENGINE = InnoDB IGNORE AS SELECT a FROM u` | `CreateTableFromQueryStatement` | A required `definition` with at least one declared column, its constraints, keys (`indexes`), MySQL table options, and partitioning; the required input `query` whose result columns follow the declared ones; `ifNotExists`; and `duplicates` (`DuplicateRows::Ignore` or `Replace`, or null when a duplicate key fails the statement). |
| MySQL: `CREATE TEMPORARY TABLE copied LIKE original` | `CreateTableLikeStatement` | Required destination `target` and `template` table reference, plus `temporary` and `ifNotExists`. The reference exposes the supplied source definition without applying the copy to the snapshot. |
| PostgreSQL: `CREATE TABLE c PARTITION OF p (id WITH OPTIONS NOT NULL) FOR VALUES FROM (1) TO (10)` | `CreatePartitionStatement` | Required partition name, parent table, and bound (IN list, FROM/TO range, MODULUS/REMAINDER, or DEFAULT); optional column overrides (each column once, no type), table constraints, EXCLUDE constraints, persistence, sub-partitioning, access method, storage parameters, ON COMMIT, tablespace, and IF NOT EXISTS. The schema snapshot gives the partition its parent's columns with the overrides applied. |
| PostgreSQL: `CREATE TABLE people OF app.person (name WITH OPTIONS NOT NULL)` | `CreateTypedTableStatement` | Required table name and composite type (at most schema-qualified); optional column overrides, table constraints, EXCLUDE constraints, and the same table properties as an ordinary declaration except INHERITS. The snapshot table stays unresolved because composite types are not part of the schema. |
| SQLite: `CREATE VIRTUAL TABLE IF NOT EXISTS temp.docs USING fts5(title, body)` | `CreateVirtualTableStatement` | The qualified `name`, the module `constructor` invocation with its arguments, and `ifNotExists`. |
| MySQL: `ALTER TABLE t ALGORITHM = INPLACE, LOCK = NONE, ADD COLUMN n INT AFTER id, DROP INDEX ix` | `AlterTableStatement` | Required resolved `table` and ordered typed `alterations` (empty for a bare `ALTER TABLE t`): `AddColumn`, `AddColumns`, `ChangeColumn`, `ModifyColumn`, `DropColumn`, `ColumnDefaultAssignment`, `ColumnDefaultRemoval`, `SetColumnVisibility`, `RenameColumn`, `AddIndex`, `AddConstraint`, `DropKey`, `SetIndexVisibility`, `SetConstraintEnforcement`, `RenameIndex`, `RenameTable`, `ConvertCharacterSet`, `ChangeTableOptions`, `OrderRows`, `RepartitionTable`, the operand-free `TableCommand` cases, and the partition commands `AddPartitions`, `AddPartitionCount`, `DropPartitions`, `ProcessPartitions`, `CheckPartitions`, `RepairPartitions`, `TruncatePartitions`, `CoalescePartitions`, `ReorganizePartitions`, `RebuildPartitioning`, `ExchangePartition`, `PartitionTablespaces`, and `SecondaryLoad`. Partition and tablespace commands stand alone, a partitioning change comes last, and the statement carries `algorithm` (INSTANT from 8.0), `lock`, `validation` (5.7 and later), and `ignore` (5.6 only). Unknown ALGORITHM or LOCK values, empty names, bare `ADD PARTITION`, zero counts, and partitions whose values do not match the partitioning are diagnosed. |
| MySQL 5.6 and 5.7: `PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10))` | `PartitionSchemeStatement` | The parser entry for stored partitioning: a required `TablePartitioning` with a HASH, KEY, RANGE, LIST, or COLUMNS function, optional partition and subpartition counts, and partition definitions whose values agree with the function. |
| MySQL 5.7: `PARSE_GCOL_EXPR (a + 1)` | `GeneratedColumnExpressionStatement` | The parser entry for a stored generated column expression: one required MySQL `expression`; column names stay unresolved because no table is known. |
| MySQL: `RENAME TABLE a TO tmp, b TO a, tmp TO b` | `RenameTablesStatement` | Nonempty ordered `renamings`, each a resolved source `TableReference` and a `newName` of at most two components; a later pair may rename a table produced by an earlier pair. `RENAME TABLES` is a synonym. |
| SQLite: `ALTER TABLE t RENAME TO u` | `RenameTableStatement` | The qualified `table` and its `newName`. |
| SQLite: `ALTER TABLE t RENAME COLUMN n TO m` | `RenameColumnStatement` | The qualified `table`, the current `column` name, and `newName`. |
| SQLite: `ALTER TABLE t ADD COLUMN n INTEGER NOT NULL DEFAULT 0` | `AddColumnStatement` | The qualified `table`, the new `column` declaration with the table `constraints` it introduces, and `ifNotExists`. |
| SQLite: `ALTER TABLE t DROP COLUMN n` | `DropColumnStatement` | The qualified `table`, the `column` name, `ifExists`, and `DropBehavior`. |
| MySQL: `DROP TEMPORARY TABLE IF EXISTS scratch` | `DropTableStatement` | Nonempty `names`, existence and dependency policies, and `TableDropScope::Temporary`. Normal DROP TABLE uses the visible table namespace. |
| PostgreSQL: `ALTER TABLE IF EXISTS ONLY t SET LOGGED, ALTER id SET NOT NULL, ADD CHECK (id > 0)` | `AlterRelationStatement` | A `RelationKind` (table, sequence, view, materialized view, index, foreign table), the relation `name`, `ifExists`, `only` (tables and foreign tables), and a nonempty ordered list of typed `RelationAction`s: column changes (`AddColumn`, `DropColumn`, `ColumnDefaultChange`, `SetColumnNullability`, `ColumnTypeChange`, `SetColumnExpression`, `DropColumnExpression`, `SetColumnStatistics`, `SetColumnStorage`, `SetColumnCompression`, column options), identity changes, constraints (`AddConstraint`, `AddExclusionConstraint`, `AlterConstraint`, `ValidateConstraint`, `DropConstraint`), firing, row-level security, inheritance, typed-table binding, owner, replica identity, storage and foreign options. A partition attach or detach is the only action of its statement. |
| PostgreSQL: `ALTER SEQUENCE IF EXISTS s RENAME TO s2` | `RenameRelationStatement` | The relation class and `name`, `newName`, `ifExists`, and `only` for tables. |
| PostgreSQL: `ALTER VIEW v RENAME COLUMN a TO b` | `RenameRelationColumnStatement` | The relation class, `name`, `column`, `newName`, `ifExists`, and `only`. |
| PostgreSQL: `ALTER TABLE ONLY t RENAME CONSTRAINT c TO d` | `RenameTableConstraintStatement` | Required `table`, `constraint`, and `newName`; `ifExists` and `only`. |
| PostgreSQL: `ALTER TABLE IF EXISTS t SET SCHEMA archive` | `SetRelationSchemaStatement` | The relation class, `name`, destination `schema`, `ifExists`, and `only`. |
| PostgreSQL: `ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice SET TABLESPACE fast NOWAIT` | `MoveTablespaceRelationsStatement` | The relation class (table, index, materialized view), source and destination tablespaces, optional owners, and `nowait`. |
| PostgreSQL: `DROP SEQUENCE IF EXISTS s1, s2 CASCADE` | `DropRelationsStatement` | Sequences or foreign tables: nonempty `names`, `ifExists`, and `DropBehavior`. |

### Indexes

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL and SQLite: `CREATE INDEX ix ON users((score+1)) WHERE score>0` | `CreateIndexStatement` | Required `table` and `index.definition`, including ordered typed keys and a partial-index predicate. |
| MySQL: `DROP INDEX ix ON users ALGORITHM=INPLACE LOCK=NONE` | `DropTableIndexStatement` | Required index name and owning table, with algorithm and lock enums. |
| PostgreSQL and SQLite: `DROP INDEX IF EXISTS ix` | `DropIndexStatement` | Nonempty qualified `names`, `ifExists`, and `DropBehavior`. MySQL names the owning table and uses `DropTableIndexStatement`. |
| PostgreSQL: `DROP INDEX CONCURRENTLY IF EXISTS ix` | `DropIndexConcurrentlyStatement` | Exactly one index name and existence policy. |

### Views

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| `CREATE VIEW v (n) AS SELECT id FROM users`; PostgreSQL: `CREATE OR REPLACE TEMPORARY VIEW v (n) AS SELECT id FROM users WITH LOCAL CHECK OPTION`, `CREATE RECURSIVE VIEW v (n) WITH (security_barrier, check_option = local) AS SELECT 1`; MySQL: `CREATE ALGORITHM=MERGE DEFINER='app'@'localhost' SQL SECURITY INVOKER VIEW v AS SELECT 1` | `CreateViewStatement` | Required `name` and bound `query`; declared `columns`, `temporary`, `replace`, and `ViewCheck`. `ifNotExists` is available only to SQLite views, which have no replacement or check policy. `properties` are dialect-specific and must match the statement's dialect. `MySqlViewProperties` carry `ViewAlgorithm` (`Undefined`, `Merge`, `TempTable`), an optional `definer` that is an `AccountName` or `CurrentAccount`, and `ViewSecurity` (`Definer`, `Invoker`); omitted clauses take MySQL's defaults. `PostgreSqlViewProperties` carry `recursive`, which requires declared `columns`, and ordered storage-style `parameters`; an option named without a value carries `ImpliedSetting::Enabled`. |
| MySQL: `ALTER ALGORITHM = MERGE SQL SECURITY INVOKER VIEW v (a) AS SELECT 1 WITH LOCAL CHECK OPTION` | `AlterViewStatement` | Required view `name` and bound `query`; declared `columns`, `ViewCheck`, and the same `MySqlViewProperties` (algorithm, definer, SQL security) as CREATE VIEW. |
| `DROP VIEW IF EXISTS v` | `DropViewStatement` | Nonempty qualified `names`, `ifExists`, and `DropBehavior`. SQLite names one view per statement. |
| PostgreSQL: `CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS s.m (x) USING heap WITH (fillfactor = 70) TABLESPACE ts AS SELECT 1 WITH NO DATA` | `CreateMaterializedViewStatement` | Required `name` and `query`; declared `columns`, `unlogged`, `ifNotExists`, optional `accessMethod` and `tablespace`, ordered `storageParameters`, and `withData`. Empty storage names are rejected. |
| PostgreSQL: `REFRESH MATERIALIZED VIEW CONCURRENTLY s.m` | `RefreshMaterializedViewStatement` | Required `name`, `concurrently`, and `withData`; a concurrent refresh cannot request NO DATA. The operation is `StatementKind::Refresh`. |
| PostgreSQL: `DROP MATERIALIZED VIEW IF EXISTS a, s.b CASCADE` | `DropMaterializedViewsStatement` | Nonempty qualified `names`, `ifExists`, and `DropBehavior`; separate from ordinary view removal. |

## Schema definition — other objects

### Databases and schemas

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CREATE DATABASE IF NOT EXISTS app CHARACTER SET utf8mb4` | `Definition\MySql\CreateDatabaseStatement` | Required database `name`, `ifNotExists`, and ordered initial `options`: `DatabaseCharacterSet`, `DatabaseCollation`, or `DatabaseEncryption`. |
| MySQL: `ALTER DATABASE app READ ONLY 1 COLLATE utf8mb4_bin` | `AlterDatabaseStatement` | Required database identity and nonempty ordered `options`, additionally allowing `DatabaseReadOnly`. Repeated READ ONLY requests must agree. |
| MySQL 5.6 and 5.7: `ALTER DATABASE old UPGRADE DATA DIRECTORY NAME` | `UpgradeDatabaseDirectoryStatement` | One required database `name`; this requests a directory-name encoding upgrade and has no default-option payload. |
| MySQL: `DROP DATABASE IF EXISTS app` | `Definition\MySql\DropDatabaseStatement` | One database `name` and `ifExists`; `DROP SCHEMA` binds to the same operation. |
| PostgreSQL: `CREATE DATABASE app WITH OWNER = alice CONNECTION LIMIT = 10` | `Definition\PostgreSql\Database\CreateDatabaseStatement` | Required database `name` and ordered `DatabaseOption` list (a `DatabaseParameter` with a value in its declared domain, or null for DEFAULT); each parameter at most once. |
| PostgreSQL: `ALTER DATABASE app WITH ALLOW_CONNECTIONS false` | `AlterDatabaseOptionsStatement` | Required `name` and the connection-policy and template options only (`ALLOW_CONNECTIONS`, `CONNECTION LIMIT`, `IS_TEMPLATE`). |
| PostgreSQL: `ALTER DATABASE app SET TABLESPACE fast` | `SetDatabaseTablespaceStatement` | Required database `name` and destination `tablespace`; `WITH TABLESPACE = fast` binds to the same class. |
| PostgreSQL: `ALTER DATABASE app REFRESH COLLATION VERSION` | `RefreshDatabaseCollationStatement` | Required database `name`. |
| PostgreSQL: `ALTER DATABASE app SET work_mem = '64MB'` | `AlterDatabaseSetStatement` | Required `name` and one stored `setting` (assigned value, DEFAULT, or FROM CURRENT); session-only forms such as `SET TRANSACTION` are `stored-setting` violations. |
| PostgreSQL: `ALTER DATABASE app RESET work_mem` | `AlterDatabaseResetStatement` | Required `name` and one `ResetSetting`. |
| PostgreSQL: `ALTER DATABASE app RESET ALL` | `AlterDatabaseResetAllStatement` | Required database `name`; removes every stored default. |
| PostgreSQL: `DROP DATABASE IF EXISTS app WITH (FORCE)` | `Definition\PostgreSql\Database\DropDatabaseStatement` | Required `name`, `ifExists` policy and `force` flag. |
| PostgreSQL: `CREATE SCHEMA IF NOT EXISTS app AUTHORIZATION alice` | `CreateSchemaStatement` | Required schema `name`, optional `owner` role, `ifNotExists`, and nested `elements` (CREATE TABLE/VIEW/INDEX/SEQUENCE/TRIGGER, GRANT) bound as statements; `IF NOT EXISTS` with elements is a `schema-element` violation. |
| PostgreSQL: `CREATE SCHEMA AUTHORIZATION alice` | `CreateAuthorizationSchemaStatement` | Required `owner` role that also names the schema, `ifNotExists`, and nested `elements`. |

MySQL database character defaults identify a character set or collation by name.
The legacy 5.6/5.7 grammars also allow `ServerCharacterInheritance::Inherit`.
`DatabaseEncryption` selects enabled or disabled encryption for subsequently
created tables; `DatabaseReadOnly` describes the requested access policy. READ
ONLY DEFAULT and READ ONLY 0 both bind to `Disabled`. These are creation and
alteration requests, so binding neither creates a database nor changes its state.
The ordered options retain dependencies between character defaults and repeated
requests. Creation allows an empty option list; alteration requires at least one
change. An omitted ALTER target resolves to `Schema::defaultSchema` when supplied,
otherwise it remains `CurrentDatabase::Session` for the consumer to resolve.
`withOptions()` replaces the option list while checking its operand domains,
release requirements, and access-policy consistency.

### Tablespaces and storage

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CREATE TABLESPACE ts ADD DATAFILE 'ts.dat' USE LOGFILE GROUP lg INITIAL_SIZE 1M ENGINE NDB` | `Definition\MySql\Storage\CreateTablespaceStatement` | Required `name`; optional first `datafile` (required before MySQL 8) and `logfileGroup`; `TablespaceOptions` with sizes in bytes (`initialSize`, `autoextendSize`, `maxSize`, `extentSize`, `fileBlockSize`), `nodegroup`, `engine`, `comment`, `StorageEncryption`, JSON `engineAttribute`, and `CompletionWait`. Repeated NODEGROUP, COMMENT, or FILE_BLOCK_SIZE, invalid sizes, and encryption other than Y/N are rejected. |
| MySQL: `ALTER TABLESPACE ts ADD DATAFILE 'more.dat' INITIAL_SIZE 4M` | `AddTablespaceDatafileStatement` | Required tablespace `name` and added `datafile`, plus `TablespaceChanges` (sizes, `engine`, `encryption`, `engineAttribute`, completion policy). |
| MySQL: `ALTER TABLESPACE ts DROP DATAFILE 'old.dat'` | `DropTablespaceDatafileStatement` | Required tablespace `name` and removed `datafile`, plus `TablespaceChanges`. |
| MySQL 5.6 and 5.7: `ALTER TABLESPACE ts CHANGE DATAFILE 'ts.dat' INITIAL_SIZE 8M` | `ChangeTablespaceDatafileStatement` | Required `name`, `datafile`, and at least one of `initialSize`, `autoextendSize`, `maxSize`; available in the MySQL 5.6 and 5.7 grammars. |
| MySQL 8.0+: `ALTER TABLESPACE ts AUTOEXTEND_SIZE 4M ENCRYPTION 'Y'` | `AlterTablespaceStatement` | Required `name` and `TablespaceChanges` without a data-file action; encryption and engine attributes require MySQL 8+. |
| MySQL 8.0+: `ALTER TABLESPACE ts RENAME TO archive` | `RenameTablespaceStatement` | Required current `name` and nonempty `newName`. |
| MySQL 5.6 and 5.7: `ALTER TABLESPACE ts READ_ONLY` | `SetTablespaceAccessStatement` | Required `name` and `TablespaceAccess` (`READ_ONLY`, `READ_WRITE`, `NOT ACCESSIBLE`); available in the MySQL 5.6 and 5.7 grammars. |
| MySQL: `DROP TABLESPACE store ENGINE NDB NO_WAIT` | `Definition\MySql\Storage\DropTablespaceStatement` | Required storage `name`, optional `engine`, and `CompletionWait` policy. |
| MySQL 8.0+: `CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE InnoDB` | `CreateUndoTablespaceStatement` | Required `name` and `datafile`, optional `engine`. |
| MySQL 8.0+: `ALTER UNDO TABLESPACE u SET INACTIVE` | `AlterUndoTablespaceStatement` | Required `name` and `UndoTablespaceState` (`ACTIVE`, `INACTIVE`), optional `engine`. |
| MySQL 8.0+: `DROP UNDO TABLESPACE undo1 ENGINE InnoDB` | `DropUndoTablespaceStatement` | Required undo-tablespace `name` and optional `engine`. This form has no wait-policy operand. |
| MySQL: `CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log' UNDO_BUFFER_SIZE 2M ENGINE NDB` | `CreateLogfileGroupStatement` | Required `name`, `LogFileKind` and `file` (REDOFILE only in MySQL 5.x), and `LogfileGroupOptions` (`initialSize`, `undoBufferSize`, `redoBufferSize`, `nodegroup`, `engine`, `comment`, completion policy). |
| MySQL: `ALTER LOGFILE GROUP lg ADD UNDOFILE 'more.log' INITIAL_SIZE 1M NO_WAIT` | `AlterLogfileGroupStatement` | Required `name`, `LogFileKind`, and added `file`; optional `initialSize` and `engine`; `CompletionWait` policy. |
| MySQL: `DROP LOGFILE GROUP logs ENGINE NDB` | `DropLogfileGroupStatement` | Required logfile-group `name`, optional `engine`, and completion policy; the target is a group rather than a tablespace. |
| PostgreSQL: `CREATE TABLESPACE fast OWNER alice LOCATION '/ssd' WITH (random_page_cost = 1.1)` | `Definition\PostgreSql\Tablespace\CreateTablespaceStatement` | Required `name` and text-constant `location`, optional `owner`, and known cost or concurrency `parameters`. |
| PostgreSQL: `ALTER TABLESPACE fast SET (seq_page_cost = 0.5)` | `SetTablespaceOptionsStatement` | Required `name` and a nonempty list of known parameter overrides. |
| PostgreSQL: `ALTER TABLESPACE fast RESET (seq_page_cost)` | `ResetTablespaceOptionsStatement` | Required `name` and a nonempty list of parameter `names`. |
| PostgreSQL: `DROP TABLESPACE IF EXISTS fast` | `Definition\PostgreSql\Tablespace\DropTablespaceStatement` | Required `name` and `ifExists` policy. |

Storage removal statements retain local storage identities rather than table
references. `engine` selects a named storage engine when supplied. Ordinary
tablespace and logfile-group removal default to `CompletionWait::Wait`; NO_WAIT
selects the other policy. Each statement owns name and engine transformations,
and the two wait-bearing forms also own `withWaiting()`. Binding checks the
selected grammar's option rules and records the request without checking files,
deleting storage, or executing engine-specific behavior.

### Types, domains, casts, and transforms

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE TYPE app.box3d` | `CreateShellTypeStatement` | The shell type `name`. |
| PostgreSQL: `CREATE TYPE pair AS (label text COLLATE "C", amount integer)` | `CreateCompositeTypeStatement` | The type `name` and ordered, uniquely named `CompositeAttribute`s (name, type, optional collation); the list may be empty. SETOF attributes are rejected as invalid input. |
| PostgreSQL: `CREATE TYPE mood AS ENUM ('sad', 'ok', 'happy')` | `CreateEnumTypeStatement` | The type `name` and its decoded, unique `labels` of at most 63 bytes; the list may be empty. |
| PostgreSQL: `CREATE TYPE box3d (INPUT = box3d_in, OUTPUT = box3d_out, INTERNALLENGTH = VARIABLE, STORAGE = main)` | `CreateBaseTypeStatement` | The type `name` and nonempty `options` keyed by `BaseTypeAttribute`: support function names, `LIKE`/`ELEMENT` types, the type length (`VARIABLE` is -1), Boolean flags, text (`CATEGORY`, `DELIMITER`, `DEFAULT`), and the canonical `ALIGNMENT`/`STORAGE` keyword. `INPUT` and `OUTPUT` are required; `ANALYSE` reads as `ANALYZE`; unrecognized attributes are ignored as the server ignores them. Repeated attributes, a non-ASCII category, and `ELEMENT` without `SUBSCRIPT` are rejected as invalid input. |
| PostgreSQL: `CREATE TYPE floatrange AS RANGE (SUBTYPE = float8, SUBTYPE_DIFF = float8mi)` | `CreateRangeTypeStatement` | The type `name` and nonempty `options` keyed by `RangeAttribute`: the required `SUBTYPE` type and the names of the operator class, collation, canonical and difference functions, and multirange type. Unknown or repeated attributes are rejected as invalid input. |
| PostgreSQL: `ALTER TYPE mood ADD VALUE IF NOT EXISTS 'calm' BEFORE 'happy'` | `AddEnumLabelStatement` | The enum `type`, the new `label`, `ifNotExists`, and an optional `EnumLabelPosition` (`BEFORE`/`AFTER` an existing label). `DROP VALUE` is rejected as invalid input. |
| PostgreSQL: `ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'` | `RenameEnumLabelStatement` | The enum `type`, the current `label`, and a different `newLabel`. |
| PostgreSQL: `ALTER TYPE pair ADD ATTRIBUTE note text, DROP ATTRIBUTE IF EXISTS amount CASCADE` | `AlterCompositeTypeStatement` | The composite `type` and a nonempty ordered list of `AttributeChange`s: `AddAttribute`, `DropAttribute` (with `ifExists`), and `RetypeAttribute` (`[SET DATA] TYPE` with an optional collation), each with its drop behavior. |
| PostgreSQL: `ALTER TYPE box3d SET (RECEIVE = NONE, STORAGE = extended)` | `AlterTypeOptionsStatement` | The base `type` and nonempty `options` limited to `RECEIVE`, `SEND`, `TYPMOD_IN`, `TYPMOD_OUT`, `ANALYZE`, `SUBSCRIPT` (a null argument is `NONE`), and `STORAGE`. Other attributes are rejected as invalid input. |
| PostgreSQL: `DROP DOMAIN IF EXISTS d1, d2` | `DropTypesStatement` | A `TypeKind`, nonempty PostgreSQL type declarations, `ifExists`, and `DropBehavior`. |
| PostgreSQL: `CREATE DOMAIN app.price AS numeric(10,2) DEFAULT 0 COLLATE "C" CONSTRAINT present NOT NULL CHECK (VALUE >= 0)` | `CreateDomainStatement` | The domain `name`, its `baseType`, an optional `default` expression and `collation`, and ordered `DomainConstraint`s (`DomainNotNull`, `DomainNullable`, `DomainCheck` whose condition binds `VALUE` to the base type), each with an optional name. Keys, references, generated columns, deferrability, `NO INHERIT`, repeated `DEFAULT`/`COLLATE`, and contradictory `NULL`/`NOT NULL` are rejected as invalid input. |
| PostgreSQL: `ALTER DOMAIN price SET DEFAULT 1` | `SetDomainDefaultStatement` | The `domain` and the required `default` expression. |
| PostgreSQL: `ALTER DOMAIN price DROP DEFAULT` | `DropDomainDefaultStatement` | The `domain`. |
| PostgreSQL: `ALTER DOMAIN price SET NOT NULL` | `AlterDomainNullabilityStatement` | The `domain` and `notNull` (`SET NOT NULL` or `DROP NOT NULL`). |
| PostgreSQL: `ALTER DOMAIN price ADD CONSTRAINT positive CHECK (VALUE > 0) NOT VALID` | `AddDomainConstraintStatement` | The `domain`, a `DomainNotNull` or `DomainCheck` constraint, and `notValid`, which only a CHECK constraint accepts; `NO INHERIT` is ignored as the server ignores it, and deferrability is rejected. |
| PostgreSQL: `ALTER DOMAIN price DROP CONSTRAINT IF EXISTS positive CASCADE` | `DropDomainConstraintStatement` | The `domain`, the `constraint` name, `ifExists`, and the drop `behavior`. |
| PostgreSQL: `ALTER DOMAIN price VALIDATE CONSTRAINT positive` | `ValidateDomainConstraintStatement` | The `domain` and the `constraint` name. |
| PostgreSQL: `CREATE CAST (bigint AS money) WITH INOUT AS ASSIGNMENT` | `CreateCastStatement` | The `sourceType`, the different `targetType`, the function-free `CastMechanism` (`WITHOUT FUNCTION` or `WITH INOUT`), and the `CastContext`. A cast from a type to itself and a binary-coercible array cast are rejected as invalid input. |
| PostgreSQL: `CREATE CAST (integer AS app.tag) WITH FUNCTION app.to_tag(integer) AS IMPLICIT` | `CreateFunctionCastStatement` | The `sourceType`, the `targetType`, the cast `function` addressed by name or signature, and the `CastContext`. |
| PostgreSQL: `DROP CAST IF EXISTS (bigint AS money) CASCADE` | `DropCastStatement` | The `cast` as a `CastIdentity`, `ifExists`, and the drop `behavior`. |
| PostgreSQL: `CREATE OR REPLACE TRANSFORM FOR hstore LANGUAGE plperl (FROM SQL WITH FUNCTION f(internal))` | `CreateTransformStatement` | The `type`, the `language`, an optional `fromSql` and `toSql` function of which at least one is present, and `orReplace`. |
| PostgreSQL: `DROP TRANSFORM IF EXISTS FOR hstore LANGUAGE plperl` | `DropTransformStatement` | The `transform` as a `TransformIdentity`, `ifExists`, and the drop `behavior`. |

### Routines and aggregates

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE OR REPLACE FUNCTION app.f(a integer, b integer DEFAULT 1) RETURNS SETOF integer LANGUAGE sql STABLE AS 'SELECT a + b'` | `Definition\Routine\CreateFunctionStatement` | Required `name` (at most three components), `parameters` (`ParameterDeclaration`: a non-set `RoutineParameter` and an optional input default), `result` (`RoutineResult`: a type or `%TYPE` reference and `setOf`), `implementation` and `options`; `orReplace` and `window` apply. `ROWS` requires a set result. |
| PostgreSQL: `CREATE FUNCTION f(n integer) RETURNS TABLE(k integer, v text) LANGUAGE sql AS '...'` | `CreateTableFunctionStatement` | As `CreateFunctionStatement`, with nonempty uniquely named result `columns` (`ResultColumn`) instead of a result type; only input parameters are allowed. |
| PostgreSQL: `CREATE FUNCTION split(s text, OUT head text, OUT tail text) LANGUAGE sql AS '...'` | `CreateOutputFunctionStatement` | As `CreateFunctionStatement` without `result`; at least one OUT or INOUT parameter describes the result and `ROWS` does not apply. |
| PostgreSQL: `CREATE PROCEDURE app.p(v integer) BEGIN ATOMIC INSERT INTO t VALUES (v); END` | `Definition\Routine\CreateProcedureStatement` | Required `name`, `parameters`, `implementation`, and `options` limited to `RoutineSecurity`, `RoutineSetting`, and `RoutineReset`; no `WINDOW`, and no output parameter after `VARIADIC` or a default. |
| PostgreSQL: `ALTER FUNCTION app.f(integer) STABLE PARALLEL SAFE RESET ALL RESTRICT` | `AlterRoutineStatement` | Required `routine` (`RoutineKind`), `target` (`RoutineByName` or `RoutineBySignature`), and nonempty `changes` of the attributes above; a procedure accepts only SECURITY, SET, and RESET. RENAME, OWNER, SET SCHEMA, and DEPENDS ON EXTENSION bind to the catalog command forms. |
| PostgreSQL: `DROP FUNCTION f, g(), h(IN integer) CASCADE` | `DropFunctionsStatement` | Nonempty `targets`: `RoutineByName` leaves arguments unspecified; `RoutineBySignature` owns the ordered parameters, including an explicit empty list. `ifExists` and `DropBehavior` apply to the request. |
| PostgreSQL: `DROP PROCEDURE p(text)`, `DROP ROUTINE r` | `DropProceduresStatement`, `DropRoutinesStatement` | The same typed overload selectors, with distinct statement types for procedure-only and general routine lookup. |
| PostgreSQL: `DROP AGGREGATE a(*), b(integer), c(integer ORDER BY text)` | `DropAggregatesStatement` | A nonempty list of `ZeroArgumentAggregate`, `OrdinaryAggregate`, or `OrderedSetAggregate`. The ordered-set form requires aggregated parameters and separately retains direct parameters. |
| PostgreSQL: `CREATE OR REPLACE AGGREGATE app.total(integer) (SFUNC = int4pl, STYPE = integer, PARALLEL = safe)` | `CreateAggregateStatement` | The `aggregate` signature (`ZeroArgumentAggregate`, `OrdinaryAggregate`, or `OrderedSetAggregate`), nonempty `options` keyed by `AggregateAttribute` (support function names, state types, sizes, Boolean flags, initial conditions as text, the sort operator, and the exact lowercase modify and parallel keywords), and `orReplace`. `SFUNC` and `STYPE` are required; `MSTYPE` requires `MSFUNC` and `MINVFUNC` and the other moving attributes require `MSTYPE`; `HYPOTHETICAL` requires an ordered-set signature. The old `BASETYPE` syntax binds to the same signatures (`BASETYPE = ANY` is `(*)`) and `SFUNC1`/`STYPE1`/`INITCOND1` read as their current names; unrecognized attributes are ignored and a repeated attribute keeps its last argument, as the server does. SETOF arguments are rejected as invalid input. |
| MySQL: `CREATE AGGREGATE FUNCTION avgcost RETURNS REAL SONAME 'udf.so'` | `CreateLoadableFunctionStatement` | Required nonempty `name`, `LoadableResult` (`STRING`, `REAL`, `DECIMAL`, `INTEGER`), and `library`; `aggregate` and `ifNotExists` (MySQL 8+). Distinct from stored functions, which have a body. |

The four PostgreSQL routine creation forms share a `RoutineImplementation`: `language`, ordered `transforms` (`TRANSFORM FOR TYPE`), and one `body`: `DefinitionBody` (`AS 'text'`, requires LANGUAGE), `LinkedBody` (`AS 'file', 'symbol'`, language C only), `ReturnBody` (`RETURN expr`), or `AtomicBody` (`BEGIN ATOMIC ... END`, bound statements and RETURN steps). Inline bodies are language sql and see the input parameters as a relation named after the routine; `options` are `Volatility`, `NullInputBehavior`, `RoutineSecurity`, `LeakproofBehavior`, `ParallelSafety`, `ExecutionCost`, `ResultRows`, `SupportFunction`, `RoutineSetting`, `RoutineReset`, each at most once except SET and RESET.

Routine argument declarations expose a `TypeDescriptor` or a `ColumnTypeReference`, an optional parameter name, a direction enum, and the set-valued type flag. A column-type reference retains its qualified name and the matching `ColumnBinding` when the supplied Schema resolves it; it never reads a column value. Missing tables or columns produce binding diagnostics. Routine targets describe the requested identity; binding does not remove definitions or execute dependency checks.

`ParameterMode::Implicit` remains distinct from an explicit `Input` mode because PostgreSQL procedure lookup uses that distinction. Aggregate arguments use the narrower `AggregateInputMode`, which has no output mode. An ordered-set aggregate with a variadic direct parameter requires exactly one variadic aggregated parameter with the same declared type. These rules apply to constructors and immutable statement transformations as well as SQL binding. The lookup semantics follow the PostgreSQL [DROP FUNCTION](https://www.postgresql.org/docs/17/sql-dropfunction.html), [DROP PROCEDURE](https://www.postgresql.org/docs/17/sql-dropprocedure.html), and [DROP AGGREGATE](https://www.postgresql.org/docs/17/sql-dropaggregate.html) definitions.

### Operators

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE OPERATOR app.=== (FUNCTION = int4eq, LEFTARG = integer, RIGHTARG = integer, COMMUTATOR = ===, HASHES)` | `CreateOperatorStatement` | The operator `name` and nonempty `options`: `DefinitionOption`s keyed by `OperatorAttribute` with typed arguments (function and estimator names, operand types, commutator and negator operators, Boolean `HASHES`/`MERGES`). `FUNCTION` and `RIGHTARG` are required; `PROCEDURE` is read as `FUNCTION` and the obsolete sort operators as `MERGES`; unrecognized attributes are ignored and a repeated attribute keeps its last argument, as the server does. SETOF operands and arguments of the wrong form are rejected as invalid input. |
| PostgreSQL: `ALTER OPERATOR === (integer, integer) SET (RESTRICT = NONE, HASHES)` | `AlterOperatorStatement` | The `operator` as an `OperatorIdentity` and nonempty `options` limited to `RESTRICT`, `JOIN`, `COMMUTATOR`, `NEGATOR`, `HASHES`, and `MERGES`; a null argument is `NONE`. Other attributes are rejected as invalid input. |
| PostgreSQL: `DROP OPERATOR IF EXISTS app.~ (NONE, text), === (integer, integer) CASCADE` | `DropOperatorsStatement` | A nonempty list of `operators` as `OperatorIdentity` values (symbol, left and right operand types, `NONE` as null), `ifExists`, and the drop `behavior`. |
| PostgreSQL: `CREATE OPERATOR CLASS app.int_ops DEFAULT FOR TYPE integer USING btree FAMILY app.ints AS OPERATOR 1 <, FUNCTION 1 btint4cmp(integer, integer)` | `CreateOperatorClassStatement` | The class `name`, the indexed `type`, the access `method`, nonempty `members` (`OperatorMember` with strategy number, operator, optional operand types and `FOR ORDER BY` family; `SupportFunctionMember` with support number, optional associated types, and function; `StorageMember`), `isDefault`, and an optional `family`. Numbers outside 1 to 32767, unary operators, more than two associated types, and a second storage type are rejected as invalid input; `RECHECK` is ignored as the server ignores it. |
| PostgreSQL: `CREATE OPERATOR FAMILY app.ints USING btree` | `CreateOperatorFamilyStatement` | The family `name` and the index access `method`. |
| PostgreSQL: `ALTER OPERATOR FAMILY integer_ops USING btree ADD OPERATOR 1 < (integer, bigint)` | `AddOperatorFamilyMembersStatement` | The `family`, the access `method`, and nonempty operator and support function `members`; operators must name their operand types and `STORAGE` is rejected as invalid input. |
| PostgreSQL: `ALTER OPERATOR FAMILY integer_ops USING btree DROP FUNCTION 1 (integer, bigint)` | `DropOperatorFamilyMembersStatement` | The `family`, the access `method`, and nonempty `MemberRemoval`s (kind, number, left and right associated types; one written type stands for both). |
| PostgreSQL: `DROP OPERATOR FAMILY IF EXISTS app.ints USING btree CASCADE` | `DropOperatorSetStatement` | The operator class or family as an `OperatorSetIdentity` (kind, name, access method), `ifExists`, and the drop `behavior`. |

### Collations, conversions, and text search

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE COLLATION IF NOT EXISTS app.ci (provider = icu, locale = 'und-u-ks-level2', deterministic = false)` | `CreateCollationStatement` | The collation `name`, the `CollationProvider` (libc when omitted), the `locale` or the `lcCollate`/`lcCtype` pair, `deterministic`, ICU `rules`, the recorded `version`, and `ifNotExists`. `LOCALE` together with `LC_COLLATE`/`LC_CTYPE`, a libc collation without both categories, an ICU or builtin collation without a locale, a builtin locale other than `C` or `C.UTF-8`, nondeterminism or rules outside ICU, and unknown or repeated settings are rejected as invalid input. |
| PostgreSQL: `CREATE COLLATION IF NOT EXISTS app.german FROM "de_DE"` | `CopyCollationStatement` | The collation `name`, the `copied` collation, and `ifNotExists`; the `(FROM = name)` spelling binds here too and may not be combined with other settings. |
| PostgreSQL: `ALTER COLLATION app.german REFRESH VERSION` | `RefreshCollationVersionStatement` | The `collation` name. |
| PostgreSQL: `CREATE DEFAULT CONVERSION app.to_latin FOR 'UTF8' TO 'LATIN1' FROM utf8_to_iso8859_1` | `CreateConversionStatement` | The conversion `name`, the `sourceEncoding` and `targetEncoding` as `ServerEncoding` values (aliases such as `'unicode'` resolve as the server resolves them), the conversion `function`, and `isDefault`. Unknown encodings and `SQL_ASCII` are rejected as invalid input. |
| PostgreSQL: `CREATE TEXT SEARCH PARSER app.words (START = prsd_start, GETTOKEN = prsd_nexttoken, END = prsd_end, LEXTYPES = prsd_lextype)` | `CreateTextSearchParserStatement` | The parser `name`, the required `start`, `gettoken`, `end`, and `lextypes` functions, and an optional `headline` function. Unknown attributes are rejected as invalid input; a repeated attribute keeps its last argument. |
| PostgreSQL: `CREATE TEXT SEARCH TEMPLATE app.plain (INIT = dsimple_init, LEXIZE = dsimple_lexize)` | `CreateTextSearchTemplateStatement` | The template `name`, the required `lexize` function, and an optional `init` function. |
| PostgreSQL: `CREATE TEXT SEARCH DICTIONARY app.stem (TEMPLATE = snowball, Language = english)` | `CreateTextSearchDictionaryStatement` | The dictionary `name`, the required `template`, and the ordered template `options` as `DictionaryOption`s (name and the argument text the template reads, or none). |
| PostgreSQL: `ALTER TEXT SEARCH DICTIONARY app.stem (StopWords = russian, Accept)` | `AlterTextSearchDictionaryStatement` | The `dictionary` and nonempty `options`; an option without an argument is removed. |
| PostgreSQL: `CREATE TEXT SEARCH CONFIGURATION app.english (PARSER = pg_catalog.default)` | `CreateTextSearchConfigurationStatement` | The configuration `name` and its `parser`. |
| PostgreSQL: `CREATE TEXT SEARCH CONFIGURATION app.english (COPY = pg_catalog.english)` | `CopyTextSearchConfigurationStatement` | The configuration `name` and the `copied` configuration; `PARSER` together with `COPY` is rejected as invalid input. |
| PostgreSQL: `ALTER TEXT SEARCH CONFIGURATION app.english ALTER MAPPING FOR word, hword WITH english_stem` | `MapTextSearchTokensStatement` | The `configuration`, the `MappingChange` (`ADD` or `ALTER`), the nonempty `tokenTypes`, and the nonempty ordered `dictionaries`. |
| PostgreSQL: `ALTER TEXT SEARCH CONFIGURATION app.english ALTER MAPPING REPLACE english_stem WITH app.stem` | `ReplaceTextSearchDictionaryStatement` | The `configuration`, the replaced `dictionary`, its `replacement`, and the `tokenTypes` it is limited to, or null for every token type. |
| PostgreSQL: `ALTER TEXT SEARCH CONFIGURATION app.english DROP MAPPING IF EXISTS FOR email, url` | `DropTextSearchMappingStatement` | The `configuration`, the nonempty `tokenTypes`, and `ifExists`. |

### Extensions, languages, access methods, and statistics

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE EXTENSION IF NOT EXISTS hstore WITH SCHEMA app VERSION '1.8' CASCADE` | `CreateExtensionStatement` | Required `name`; optional `schema` and nonempty `version`; `ifNotExists` and `cascade` apply. Each option is given once and `FROM` is rejected. A parameterless `CREATE [OR REPLACE] LANGUAGE l` binds here because the server runs it as `CREATE EXTENSION [IF NOT EXISTS] l`. |
| PostgreSQL: `ALTER EXTENSION hstore UPDATE TO '1.8'` | `UpdateExtensionStatement` | Required `name`; an optional target `version`, where null selects the default version. |
| PostgreSQL: `ALTER EXTENSION hstore ADD FUNCTION app.f(integer)`, `ALTER EXTENSION hstore DROP TABLE t` | `AddExtensionMemberStatement`, `DropExtensionMemberStatement` | Required `extension` and a whole-object `ObjectAddress` `object` of any class the grammar names; columns, relation members, domain constraints, and large objects are rejected. |
| PostgreSQL: `CREATE OR REPLACE TRUSTED LANGUAGE pl HANDLER app.call INLINE app.run VALIDATOR app.check` | `CreateLanguageStatement` | Required `name` and `handler`; optional `inline` and `validator` functions (NO VALIDATOR is an omitted validator); `orReplace` and `trusted` apply; PROCEDURAL is noise. |
| PostgreSQL: `CREATE ACCESS METHOD heap2 TYPE TABLE HANDLER heap_tableam_handler` | `CreateAccessMethodStatement` | Required `name`, `type` (`AccessMethodKind::Table` or `Index`), and `handler` function. |
| PostgreSQL: `CREATE STATISTICS IF NOT EXISTS s (ndistinct, mcv) ON a, lower(b) FROM t` | `CreateStatisticsStatement` | Optional `name` (required by `ifNotExists`), distinct `kinds` (empty means all), two to eight distinct `elements` (column references or expressions without subqueries), and one plain `table` reference. |
| PostgreSQL: `CREATE STATISTICS s ON (a + 1) FROM t` | `CreateExpressionStatisticsStatement` | Optional `name`, one non-column `expression`, and the `table`; this univariate form takes no kinds. |
| PostgreSQL: `ALTER STATISTICS IF EXISTS app.s SET STATISTICS 500` | `SetStatisticsTargetStatement` | Required `name` and `target` between -1 (also `DEFAULT`) and 10000; larger targets are lowered to 10000 as the server does; `ifExists` applies. |
| PostgreSQL: `CREATE ASSERTION positive CHECK (NOT EXISTS (SELECT 1 FROM t WHERE a < 0)) DEFERRABLE` | `CreateAssertionStatement` | Required `name`, bound `condition`, and `checking` (`CheckingTime`), as the PostgreSQL grammar defines the statement. NOT VALID and NO INHERIT are rejected. |

### Sequences

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE TEMP SEQUENCE IF NOT EXISTS s AS integer INCREMENT BY -2 MINVALUE -100 START WITH -5` | `CreateSequenceStatement` | Required `name`, `persistence` (`SequencePersistence`), and ordered `options` (`SequenceValueChange`, `SequenceFlag`, `SequenceStorage`, `SetSequenceOwner`, `RestartIdentity`) with signs kept; each option once, no SEQUENCE NAME, LOGGED, or UNLOGGED option, and bounds checked with the server defaults for the type and direction. |
| PostgreSQL: `ALTER SEQUENCE IF EXISTS s NO MAXVALUE RESTART WITH 7 OWNED BY NONE` | `AlterSequenceStatement` | Required `name` and nonempty ordered `options` of the same kinds; explicit bounds must be ordered and contain explicit START and RESTART values; `ifExists` applies. |

### Triggers, rules, and policies

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE OR REPLACE TRIGGER audit AFTER UPDATE OF a ON t FOR EACH ROW WHEN (OLD.a <> NEW.a) EXECUTE FUNCTION log_change('x')` | `Definition\PostgreSql\Trigger\CreateTriggerStatement` | Required `name`, `table` (`TableReference`), `timing` (`Timing`: BEFORE, AFTER, INSTEAD OF), `events` (`TriggerEvents`: distinct INSERT, UPDATE, DELETE, TRUNCATE with optional UPDATE OF `columns`), `level` (`TriggerLevel`, STATEMENT by default), and `invocation` (`TriggerInvocation`: function name and the argument texts it receives); optional `transitions` (`TransitionTable` OLD or NEW TABLE names, AFTER triggers of one row change only), `condition` bound against the OLD/NEW row images the events supply, and `orReplace`. INSTEAD OF fires per row without condition or columns and TRUNCATE per statement; PROCEDURE is written as FUNCTION. |
| PostgreSQL: `CREATE CONSTRAINT TRIGGER check_parent AFTER DELETE ON t FROM parent DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION f()` | `CreateConstraintTriggerStatement` | An AFTER row trigger: required `name`, `table`, `events` (no TRUNCATE), `invocation`, and `checking` (`CheckingTime`); optional `referenced` FROM table and `condition`. It cannot be replaced, and NOT VALID or NO INHERIT is rejected. |
| SQLite: `CREATE TEMP TRIGGER IF NOT EXISTS tr AFTER UPDATE OF id ON t WHEN new.id > 0 BEGIN UPDATE t SET x = new.id WHERE id = old.id; END` | `CreateSqliteTriggerStatement` | The qualified `name`, `subject` table, `timing`, `event` (with UPDATE OF columns), and a `body` of bound statements in which OLD and NEW refer to the subject's row images; optional `when` condition, `temporary`, and `ifNotExists`. |
| PostgreSQL: `DROP TRIGGER tr ON users CASCADE` | `DropTableTriggerStatement` | Required trigger name and owning table, with drop behavior. |
| MySQL and SQLite: `DROP TRIGGER IF EXISTS audit` | `DropTriggerStatement` | Exactly one `name` and an existence policy. PostgreSQL uses `DropTableTriggerStatement` with a required owning table. |
| PostgreSQL: `CREATE EVENT TRIGGER guard ON ddl_command_start WHEN TAG IN ('DROP TABLE') EXECUTE FUNCTION stop_ddl()` | `CreateEventTriggerStatement` | Required `name`, `event` (`EventTriggerEvent`: ddl_command_start, ddl_command_end, sql_drop, table_rewrite, login), and `function`; optional `tags`, a list of `DdlCommandTag` matched without regard to case. Only one TAG filter is accepted; login takes none and table_rewrite only ALTER TABLE, ALTER TYPE and ALTER MATERIALIZED VIEW. |
| PostgreSQL: `ALTER EVENT TRIGGER audit ENABLE REPLICA` | `AlterEventTriggerFiringStatement` | Required `name` and `TriggerFiring`: `Origin`, `Replica`, `Always`, or `Disabled`. |
| PostgreSQL: `ALTER EVENT TRIGGER audit RENAME TO audit_ddl` | `RenameEventTriggerStatement` | Required current `name` and replacement `newName`; both are unqualified identities. |
| PostgreSQL: `ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER` | `ChangeEventTriggerOwnerStatement` | Required `name` and `newOwner`, which is a `NamedRole` or `SessionRole`. |
| PostgreSQL: `DROP EVENT TRIGGER IF EXISTS audit CASCADE` | `DropEventTriggersStatement` | Nonempty unqualified `names`, `ifExists`, and `DropBehavior`. |
| PostgreSQL: `CREATE RULE keep AS ON DELETE TO t WHERE OLD.locked DO INSTEAD NOTHING` | `CreateEmptyRuleStatement` | Required `name`, `table`, and `event` (`RuleEvent`: INSERT, UPDATE, DELETE); optional `instead` (ALSO by default), `condition` bound against the OLD/NEW images of the event, and `orReplace`. An action list of only empty commands binds here as NOTHING; a SELECT rule cannot do nothing. |
| PostgreSQL: `CREATE RULE audit AS ON UPDATE TO t DO ALSO (INSERT INTO log VALUES (OLD.a); NOTIFY changed)` | `CreateCommandRuleStatement` | Required `name`, `table`, `event`, and nonempty `actions`, each a native query, INSERT, UPDATE, DELETE, or `NotifyStatement` bound where OLD and NEW are the table's row images; optional `instead`, `condition`, and `orReplace`. A conditional rule cannot NOTIFY, RETURNING needs one action of an unconditional INSTEAD rule, and an ON SELECT rule must be `CREATE OR REPLACE RULE "_RETURN"` doing INSTEAD one query without WHERE. |
| PostgreSQL: `CREATE POLICY own ON docs AS RESTRICTIVE FOR UPDATE TO staff USING (owner = CURRENT_USER) WITH CHECK (owner = CURRENT_USER)` | `CreatePolicyStatement` | Required `name`, `table`, nonempty `roles` (`NamedRole`, `SessionRole`, or `PublicRole`, PUBLIC by default), `mode` (`PolicyMode`, PERMISSIVE by default), and `command` (`PolicyCommand`, ALL by default); optional `using` and `check` expressions over the table's columns. SELECT and DELETE policies take no WITH CHECK and INSERT policies no USING; AS accepts only PERMISSIVE or RESTRICTIVE. |
| PostgreSQL: `ALTER POLICY own ON docs TO staff USING (true)` | `AlterPolicyStatement` | Required `name` and `table`; optional replacement `roles`, `using`, and `check`, where null keeps the current value. |
| PostgreSQL: `DROP POLICY IF EXISTS p ON t` | `DropRelationMemberStatement` | A policy or rule `name`, its `table`, `ifExists`, and `DropBehavior`. |

Event-trigger alterations describe independent changes to database-level triggers.
`TriggerFiring::Origin` permits firing under the origin and local replication
roles; `Replica` selects the replica role, `Always` selects every role, and
`Disabled` prevents firing. `withFiring()` changes that policy without reading
the current replication role. Renaming requires `newName`, ownership transfer
requires `newOwner`, and removal owns its existence and dependency policies.
Each statement retains its required operands through immutable transformations.
Binding does not invoke a trigger function or apply the change.

### Publications and subscriptions

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE PUBLICATION pub WITH (publish = 'insert')` | `CreatePublicationStatement` | Required `name` and `options` (`PublicationOptions`: `publish` as a list of `PublishedOperation` insert, update, delete, truncate, and the Boolean `viaPartitionRoot`; null when not specified). Other option names, repeated options, and mistyped values are rejected. |
| PostgreSQL: `CREATE PUBLICATION pub FOR ALL TABLES` | `CreateAllTablesPublicationStatement` | Required `name` and `options`. |
| PostgreSQL: `CREATE PUBLICATION pub FOR TABLE ONLY t (a) WHERE (a > 0), u`, `CREATE PUBLICATION pub FOR TABLES IN SCHEMA sales, CURRENT_SCHEMA` | `CreateObjectsPublicationStatement` | Required `name`, nonempty `objects` (`PublicationMember`: `PublishedTable` with a `TableReference` or `OnlyTableReference`, optional distinct `columns` and a row `filter` over its columns; `PublishedSchema`; `PublishedCurrentSchema`), and `options`. Unprefixed names continue the previous kind and are written with their prefix; the list must start with TABLE or TABLES IN SCHEMA, and column lists cannot accompany a schema. |
| PostgreSQL: `ALTER PUBLICATION pub SET (publish_via_partition_root = true)` | `AlterPublicationOptionsStatement` | Required `name` and nonempty `options`; unspecified options keep their values. |
| PostgreSQL: `ALTER PUBLICATION pub ADD TABLE t, TABLES IN SCHEMA CURRENT_SCHEMA` | `AlterPublicationObjectsStatement` | Required `name`, `change` (`PublicationObjectChange`: ADD, SET, DROP), and nonempty `objects`; a dropped table takes neither columns nor filter. RENAME and OWNER TO bind to the catalog rename and owner statements. |
| PostgreSQL: `CREATE SUBSCRIPTION sub CONNECTION 'host=primary' PUBLICATION pub WITH (enabled = false, slot_name = NONE, create_slot = false)` | `CreateSubscriptionStatement` | Required `name`, `connection` (decoded text, not parsed), nonempty distinct `publications`, and `options` (`SubscriptionOptions`: Booleans `connect`, `enabled`, `createSlot`, `copyData`, `binary`, `twoPhase`, `disableOnError`, `passwordRequired`, `runAsOwner`, `failover`; `slotName` as a slot name or `NoSlot`; `SynchronousCommit`; `StreamingMode`; `OriginFilter`; null when not specified). Unknown, repeated, or mistyped options are rejected, as are connect = false with enabled, create_slot or copy_data = true and slot_name = NONE without enabled and create_slot = false. |
| PostgreSQL: `ALTER SUBSCRIPTION sub CONNECTION 'host=standby'` | `AlterSubscriptionConnectionStatement` | Required `name` and `connection`. |
| PostgreSQL: `ALTER SUBSCRIPTION sub SET (binary = true, slot_name = NONE)` | `AlterSubscriptionOptionsStatement` | Required `name` and nonempty `options` limited to slot_name, synchronous_commit, binary, streaming, disable_on_error, password_required, run_as_owner, failover, and origin. |
| PostgreSQL: `ALTER SUBSCRIPTION sub ADD PUBLICATION extra WITH (refresh = false)` | `AlterSubscriptionPublicationsStatement` | Required `name`, `change` (`PublicationListChange`: SET, ADD, DROP), nonempty distinct `publications`, and `options` limited to refresh and copy_data. |
| PostgreSQL: `ALTER SUBSCRIPTION sub REFRESH PUBLICATION WITH (copy_data = false)` | `RefreshSubscriptionStatement` | Required `name` and `options` limited to copy_data. |
| PostgreSQL: `ALTER SUBSCRIPTION sub DISABLE` | `AlterSubscriptionEnabledStatement` | Required `name` and `enabled` (ENABLE or DISABLE). |
| PostgreSQL: `ALTER SUBSCRIPTION sub SKIP (lsn = '0/14C0378')` | `SkipSubscriptionTransactionStatement` | Required `name` and `lsn`, a nonzero X/X position in canonical upper-case spelling, or null for NONE. |
| PostgreSQL: `DROP SUBSCRIPTION IF EXISTS sub CASCADE` | `DropSubscriptionStatement` | Required `name`, `ifExists`, and `behavior` (`DropBehavior`). RENAME and OWNER TO bind to the catalog rename and owner statements. |

### Foreign data and servers

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `CREATE FOREIGN DATA WRAPPER fdw HANDLER app.h OPTIONS (format 'csv')` | `CreateForeignDataWrapperStatement` | Required wrapper `name`, optional `handler` and `validator` function names, and initial `ForeignOption` values with unique names. Omitted functions and explicit NO HANDLER/NO VALIDATOR both mean absence. |
| PostgreSQL: `ALTER FOREIGN DATA WRAPPER fdw NO HANDLER OPTIONS (ADD format 'csv', DROP path)` | `AlterForeignDataWrapperStatement` | Required wrapper `name`; each support-function change is a `QualifiedName`, `FunctionChange::Keep`, or `FunctionChange::Remove`. Ordered option changes are `AddForeignOption`, `SetForeignOption`, or `DropForeignOption`. At least one change is required. |
| PostgreSQL: `DROP FOREIGN DATA WRAPPER fdw RESTRICT` | `DropForeignDataWrappersStatement` | Nonempty wrapper `names`, `ifExists`, and `DropBehavior`; separate from server removal. |
| PostgreSQL: `CREATE SERVER remote TYPE 'sql' VERSION 'v1' FOREIGN DATA WRAPPER fdw` | `CreateForeignServerStatement` | Required server `name` and `wrapper`, optional `serverType` and `version` text literals, unique initial `ForeignOption` values, and `ifNotExists`. |
| PostgreSQL: `ALTER SERVER remote VERSION NULL OPTIONS (SET host 'other')` | `AlterForeignServerStatement` | Required server `name`; `version` is a text literal, `ServerVersionChange::Keep`, or `ServerVersionChange::Remove`. Ordered `options` contain typed addition, replacement, or removal requests. At least one version or option change is required. |
| PostgreSQL: `DROP SERVER IF EXISTS remote, archive CASCADE` | `DropForeignServersStatement` | Nonempty unqualified server `names`, `ifExists`, and `DropBehavior`. |
| PostgreSQL: `CREATE USER MAPPING IF NOT EXISTS FOR CURRENT_USER SERVER remote OPTIONS (user 'reader')` | `CreateUserMappingStatement` | Required `UserMappingIdentity`, `ifNotExists`, and initial `ForeignOption` values with unique names. |
| PostgreSQL: `ALTER USER MAPPING FOR alice SERVER remote OPTIONS (SET user 'reader', DROP password)` | `AlterUserMappingStatement` | Required mapping `target` and nonempty ordered `options`: `AddForeignOption`, `SetForeignOption`, or `DropForeignOption`. |
| PostgreSQL: `DROP USER MAPPING IF EXISTS FOR PUBLIC SERVER remote` | `DropUserMappingStatement` | Required mapping `target` and `ifExists`; no option payload. |
| PostgreSQL: `IMPORT FOREIGN SCHEMA ext LIMIT TO (users) FROM SERVER remote INTO app` | `ImportForeignSchemaStatement` | Required `remoteSchema`, `server`, and `localSchema`; `selection` is `AllForeignTables`, `ImportOnlyTables`, or `ExcludeForeignTables`. Explicit selections require a nonempty list of `ForeignRelation` values. `options` is an ordered list of `ForeignOption` identifier/text-literal pairs. |
| PostgreSQL: `CREATE FOREIGN TABLE IF NOT EXISTS ft (a integer OPTIONS (column_name 'x') NOT NULL) INHERITS (p) SERVER s OPTIONS (schema_name 'r')` | `CreateForeignTableStatement` | A bound table `definition`, required `server`, wrapper `options`, `inherits`, `LIKE` `templates`, per-column `columnOptions`, and `ifNotExists`. Keys, foreign keys, and exclusions are rejected as invalid input. |
| PostgreSQL: `CREATE FOREIGN TABLE ft PARTITION OF p (a WITH OPTIONS NOT NULL) FOR VALUES IN (1) SERVER s` | `CreateForeignPartitionStatement` | Required `name`, `parent`, partition `bound` (hash, list, range, or default), and `server`; wrapper `options`, type-less column overrides, table constraints, and `ifNotExists`. |
| MySQL: `CREATE SERVER remote FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'db', PORT 3307)` | `CreateServerStatement` | Required nonempty `name`, `wrapper`, and `ServerOptions` with at least one of `user`, `host`, `database`, `owner`, `password`, `socket`, `port`; a repeated option keeps its last value; the password is recorded as written. An empty name or a port beyond a signed 64-bit integer is rejected. |
| MySQL: `ALTER SERVER remote OPTIONS (USER 'app')` | `AlterServerStatement` | Required server `name` and the changed `ServerOptions`; omitted options keep their previous values. |
| MySQL: `DROP SERVER IF EXISTS remote` | `DropServerStatement` | One foreign-server definition `name` and an existence policy. |

`UserMappingIdentity` pairs a foreign `server` name with a `NamedRole` or a
`MappingPrincipal` enum. The enum distinguishes the current user, current role,
session user, and public fallback mapping. `USER` denotes `CurrentUser`; the quoted
identifier `"CURRENT_USER"` denotes a named role. Binding retains these selectors
without reading the session's actual usernames or opening a connection. Each
mapping statement owns `withTarget()` to replace the user/server pair together.
Creation supplies initial options, alteration requires explicit changes, and
removal carries only the mapping identity and its existence policy.

Foreign-data wrapper creation and alteration have different operand domains.
Creation owns initial named text options. Alteration owns ordered changes:
addition and replacement require a `ForeignOption`, while removal has only a name.
The handler identifies a zero-argument function returning `fdw_handler`; the
validator identifies a function accepting `text[]` and `oid` whose return value is
ignored. Binding records those function roles without calling them. An ALTER's
`withChanges()` replaces function and option changes together, so removing the
last requested operation cannot leave an empty alteration.

Foreign server creation owns the wrapper name and its initial metadata. Creation's
`withDefinition()` replaces that wrapper, type, version, and options together.
Omitted versions and explicit VERSION NULL both declare an absent version. In an
alteration, absence of the VERSION clause means keep the existing version, while
VERSION NULL requests removal. `withChanges()` replaces version and option changes
atomically and rejects an empty request. Text metadata and options remain literal
operands for the wrapper; binding does not connect, validate credentials, or apply
changes. Removal records dependent-object behavior without expanding or executing
its effects.

Foreign schema imports retain remote relation qualification and descendant scope
for the foreign-data wrapper. These are remote selectors, so binding does not look
them up among local table declarations. A `ForeignOption` requires a name and a
PostgreSQL text literal; the selected wrapper defines the option's meaning.
Binding does not connect to the remote server, discover columns, or create local
tables. The supplied schema snapshot stays unchanged. Import selections and
options can be replaced immutably, and the remote server/schema pair can be
replaced together with `withRemote()`.

### Spatial reference systems

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'` | `CreateSpatialReferenceSystemStatement` | Required nonzero unsigned 32-bit `srid`, `SpatialDefinition`, and a mutually exclusive `CreationPolicy`: require a new definition, ignore an existing one, or replace it. |
| MySQL: `DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4120` | `DropSpatialReferenceSystemStatement` | Required nonzero unsigned 32-bit `srid` and `ifExists`; no declaration metadata. |

Spatial reference declarations are available in the selected MySQL 8+ grammars.
`SpatialDefinition` requires `name` and `definition` text literals. Optional
`Organization` pairs its name literal with an unsigned 32-bit authority identifier;
`description` is a separate optional literal. Attribute order has no semantic
meaning and duplicate attributes are rejected. Binding describes these operands
without interpreting the coordinate-system definition text or reading existing
spatial systems. Replacing metadata supplies a complete `SpatialDefinition`, so
required attributes cannot disappear during a transformation.

### Catalog operations

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| PostgreSQL: `ALTER COLLATION c RENAME TO d` | `RenameObjectStatement` | A typed `ObjectAddress` (named object, schema object, type name, routine, aggregate, operator class or family, rule, or trigger) and `newName`. |
| PostgreSQL: `ALTER DOMAIN d RENAME CONSTRAINT a TO b` | `RenameDomainConstraintStatement` | Required `domain`, `constraint`, and `newName`. |
| PostgreSQL: `ALTER TYPE t RENAME ATTRIBUTE a TO b CASCADE` | `RenameTypeAttributeStatement` | Required `type`, `attribute`, and `newName`; `DropBehavior`. |
| PostgreSQL: `ALTER POLICY IF EXISTS p ON t RENAME TO q` | `RenamePolicyStatement` | Required policy `name`, `table`, and `newName`; `ifExists`. |
| PostgreSQL: `ALTER AGGREGATE a(integer) SET SCHEMA s` | `SetObjectSchemaStatement` | A schema-scoped `ObjectAddress` and the destination `schema`. |
| PostgreSQL: `ALTER TEXT SEARCH DICTIONARY d OWNER TO CURRENT_ROLE` | `ChangeObjectOwnerStatement` | An owned `ObjectAddress` other than a relation and the `newOwner` (`NamedRole` or `SessionRole`). |
| PostgreSQL: `ALTER INDEX ix DEPENDS ON EXTENSION e` | `AddExtensionDependencyStatement` | A routine, trigger, materialized view, or index address and the `extension`. |
| PostgreSQL: `ALTER FUNCTION f(integer) NO DEPENDS ON EXTENSION e` | `RemoveExtensionDependencyStatement` | The same operands as the dependency it removes. |
| PostgreSQL: `COMMENT ON COLUMN app.t.id IS 'key'` | `CommentOnStatement` | A typed `ObjectAddress` for every commentable class (casts, operators, transforms, large objects, members, types by declaration) and the text `Literal`, or null for `IS NULL`. |
| PostgreSQL: `SECURITY LABEL FOR selinux ON TABLE t IS 'label'` | `SecurityLabelStatement` | An `ObjectAddress` of a class that accepts labels, an optional `provider`, and the label `Literal` or null. |
| PostgreSQL: `DROP TEXT SEARCH CONFIGURATION app.english` | `DropSchemaObjectsStatement` | A `SchemaObjectKind`, nonempty qualified `names`, `ifExists`, and `DropBehavior`. |
| PostgreSQL: `DROP SCHEMA IF EXISTS a, b CASCADE` | `DropNamedObjectsStatement` | Schemas, extensions, languages, publications, or access methods: nonempty `names`, `ifExists`, and `DropBehavior`. |

## Stored programs

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CREATE DEFINER = CURRENT_USER PROCEDURE app.p(IN a INT, OUT b INT) SQL SECURITY INVOKER BEGIN SET b = a; END` | `Definition\MySql\Program\CreateProcedureStatement` | A local or database-qualified `name`, ordered `ProcedureParameter`s (name, `DeclaredDomain`, `IN`/`OUT`/`INOUT`), `RoutineCharacteristics` (DETERMINISTIC, data access, SQL SECURITY, COMMENT; omitted ones take server defaults), an optional definer, IF NOT EXISTS (MySQL 8.0+), and a `ProgramStatement` body or, for another LANGUAGE (MySQL 8.1+), `ExternalRoutineCode` holding the decoded `AS '...'`/`$$...$$` text. Parameter names are distinct; RETURN is diagnosed. |
| MySQL: `CREATE FUNCTION f(a INT) RETURNS BIGINT DETERMINISTIC RETURN a * 2` | `Definition\MySql\Program\CreateFunctionStatement` | The procedure operands with `FunctionParameter`s and a required `returns` domain; a SQL body contains at least one RETURN and neither returns a result set nor commits, flushes, resets or prepares SQL. |
| MySQL: `CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW FOLLOWS other SET NEW.n = OLD.n` | `Definition\MySql\Program\CreateTriggerStatement` | Required `name`, BEFORE/AFTER `Timing`, `WriteEvent`, resolved `table`, and `ProgramStatement` body; optional `TriggerOrder` (FOLLOWS/PRECEDES, MySQL 5.7+), definer and IF NOT EXISTS. The body reads only the OLD/NEW rows its event supplies and assigns only NEW columns of a BEFORE trigger. |
| MySQL: `CREATE EVENT IF NOT EXISTS e ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01' ON COMPLETION PRESERVE DISABLE DO DELETE FROM t` | `CreateEventStatement` | Required `name`, a `OneTimeSchedule` (AT) or `RecurringSchedule` (EVERY interval unit, optional STARTS/ENDS; no microsecond units), and a body; `EventCompletion` (default NOT PRESERVE), `EventStatus` (ENABLE, DISABLE, DISABLE ON SLAVE/REPLICA), comment, definer and IF NOT EXISTS. |
| MySQL: `ALTER DEFINER = 'ops'@'%' EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP RENAME TO archive.e ENABLE DO BEGIN END` | `AlterEventStatement` | The event `name`, an optional new definer and an `EventAlteration` requesting at least one of schedule, completion, new name, status, comment and body; omitted properties stay unchanged. |
| MySQL: `DROP EVENT IF EXISTS app.daily` | `DropEventStatement` | One local or database-qualified event `name` and an existence policy. |
| MySQL: `ALTER FUNCTION app.f READS SQL DATA` | `AlterFunctionStatement` | Required routine `name` and `RoutineAlteration` containing optional language, data-access, security, and comment changes. |
| MySQL: `ALTER PROCEDURE app.p SQL SECURITY INVOKER COMMENT 'note'` | `AlterProcedureStatement` | The same characteristic-change domain, targeting a procedure rather than a function. |
| MySQL: `DROP FUNCTION IF EXISTS app.f`, `DROP PROCEDURE app.p` | `DropFunctionStatement`, `DropProcedureStatement` | One required `QualifiedName` and an existence policy. There is no overload signature or dependency policy. |
| MySQL: `CALL app.refresh(1, @x)` | `CallStatement` | Required procedure `QualifiedName` and ordered argument expressions; an omitted or empty argument list binds as no arguments. An empty or space-terminated procedure name is diagnosed. |
| PostgreSQL: `CALL app.merge(1, since => now())`, `CALL app.collect(VARIADIC ARRAY[2, 3])` | `CallProcedureStatement` | Procedure `name` with at most three components and ordered `ProcedureArgument`s (value, optional parameter name, variadic flag); positional arguments precede named ones, names are unique, only the last argument is variadic; `DISTINCT`, `ORDER BY` and `*` are `procedure-call` violations. |
| PostgreSQL: `DO $$BEGIN NULL; END$$ LANGUAGE plpgsql` | `DoBlockStatement` | Required text-constant `code` and optional `language` (null means the server default); more than one code block or language is an `anonymous-block` violation. The block is not run. |
| MySQL: `GET STACKED DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT` | `GetDiagnosticsStatement` | `DiagnosticsArea` (`CURRENT` when omitted) and a nonempty list of statement items, each a user variable target and a `StatementItem`. A bare name (local variable) is diagnosed outside stored programs. |
| MySQL: `GET DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT` | `GetConditionDiagnosticsStatement` | `DiagnosticsArea`, the condition number as a literal or user variable, and a nonempty list of condition items, each a user variable target and a `ConditionItem`. |
| MySQL: `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'x'` | `SignalStatement` | Required `SqlState` (not a completion class) and unique condition item assignments whose values are literals or user variables. `SQLSTATE VALUE` is the same form; a condition name is diagnosed outside stored programs. |
| MySQL: `RESIGNAL SET MYSQL_ERRNO = 5` | `ResignalStatement` | Optional `SqlState` and unique condition item assignments; the same value rules as `SIGNAL`. |

A `RoutineAlteration` records each requested characteristic independently. `null`
means that the characteristic stays unchanged. `SqlDataAccess` identifies no SQL,
SQL without declared data access, reading data, or modifying data;
`RoutineSecurity` selects definer or invoker privileges. `language` names the
routine language and `comment` is a MySQL text literal. Repeated declarations use
the last value for that characteristic, as in the database grammar. MySQL also
accepts an ALTER with every characteristic unchanged. `withChanges()` replaces
the complete request; binding does not execute the routine or infer whether its
body obeys the declared characteristics.

A MySQL stored program body is a tree of body elements in `Model\Definition\Routine\Body`. Each row below shows a definition whose body contains the listed elements.

| SQL | Body element | Required structure and applicable options |
|-----|--------------|----------------------------------------|
| MySQL: `CREATE PROCEDURE p() lbl: BEGIN DECLARE x INT DEFAULT 0; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND SET x = 1; SET x = 2; END lbl` | `BlockStatement` | An optional label and ordered declarations — `VariableDeclaration` (names, domain, default), `ConditionDeclaration` (error number or SQLSTATE), `CursorDeclaration` (bound query without INTO), `HandlerDeclaration` (CONTINUE/EXIT, conditions, statement) — in variable/condition, cursor, handler order with each name once, then statements. Parameters and variables resolve as `LocalVariableReference` before columns. |
| MySQL: `CREATE PROCEDURE p(c INT) BEGIN IF c > 0 THEN SET c = 1; ELSEIF c < 0 THEN SET c = -1; ELSE SET c = 0; END IF; CASE c WHEN 1 THEN SET c = 2; ELSE SET c = 3; END CASE; CASE WHEN c > 1 THEN SET c = 0; END CASE; END` | `IfStatement`, `SimpleCaseStatement`, `SearchedCaseStatement` | Nonempty ordered `ConditionalBranch`es (guard or comparison value, nonempty statements), the simple CASE operand, and an optional ELSE list. |
| MySQL: `CREATE PROCEDURE p() BEGIN DECLARE i INT DEFAULT 0; l: LOOP SET i = i + 1; IF i > 3 THEN LEAVE l; END IF; ITERATE l; END LOOP l; WHILE i > 0 DO SET i = i - 1; END WHILE; REPEAT SET i = i + 1; UNTIL i > 3 END REPEAT; END` | `LoopStatement`, `WhileStatement`, `RepeatStatement`, `LeaveStatement`, `IterateStatement` | Optional labels matching their end labels and not reused while enclosed; nonempty statements and the loop condition. LEAVE names an enclosing label and ITERATE an enclosing loop within the same handler. |
| MySQL: `CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE c CURSOR FOR SELECT 1; OPEN c; FETCH NEXT FROM c INTO a; CLOSE c; END` | `CursorOpenStatement`, `CursorFetchStatement`, `CursorCloseStatement` | A declared cursor name and, for FETCH, nonempty declared variable targets. |
| MySQL: `CREATE FUNCTION f(a INT) RETURNS INT DETERMINISTIC BEGIN RETURN a * 2; END` | `ReturnStatement` | The returned expression of a stored function. |
| MySQL: `CREATE PROCEDURE p() BEGIN DECLARE x INT; SET x = 1, @u = x; SELECT 1 INTO x; END` | `AssignmentStatement`, `SelectIntoStatement` | SET items in written order, at least one being a `LocalAssignment` or `TriggerRowAssignment` (DEFAULT is diagnosed); SELECT INTO with at least one local target and one target per known result column. |
| MySQL: `CREATE PROCEDURE p(x INT) UPDATE t SET n = x` | `EmbeddedStatement` | Any other statement, bound in the same semantic form as direct SQL, with program names visible; nested routine/trigger/event definitions, ALTER VIEW, USE, LOCK/UNLOCK TABLES, LOAD DATA and HELP are diagnosed. |

## Server inspection (SHOW)

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `SHOW DATABASES LIKE 'app%'`, `` SHOW SCHEMAS WHERE `Database` <> 'mysql' `` | `ShowDatabasesStatement` | Optional `filter`: a `PatternFilter` keeping the LIKE literal's spelling or a `ConditionFilter` whose references resolve to the listing's own result fields. One `Database` result field; SCHEMAS binds to the same operation. |
| MySQL: `SHOW EXTENDED FULL TABLES FROM app LIKE 'u%'` | `ShowTablesStatement` | Optional `database` (FROM and IN alike), optional `filter`, and `full`/`extended` flags; EXTENDED requires MySQL 8.0. The name field is labelled `Tables_in_<database>`, falling back to the schema default; FULL adds `Table_type`. |
| MySQL: `SHOW FULL COLUMNS FROM users LIKE 'id%'`, `SHOW FIELDS IN users IN app` | `ShowColumnsStatement` | Required `table` resolved through the schema (a trailing FROM/IN database overrides the table's qualifier), optional `filter`, `full` (adds collation, privileges, comment) and `extended` (hidden columns, MySQL 8.0). Six or nine typed result fields. |
| MySQL: `SHOW EXTENDED INDEX FROM users WHERE Non_unique = 0`, `SHOW KEYS IN users` | `ShowIndexesStatement` | Required `table`, optional WHERE `condition` only (no LIKE form), and `extended`. Fifteen result fields on MySQL 8.0 and later; `Visible` and `Expression` are absent on 5.6/5.7. |
| MySQL: `SHOW TABLE STATUS FROM app LIKE 'u%'` | `ShowTableStatusStatement` | Optional `database` and `filter`; eighteen result fields where only `Name` is never NULL. |
| MySQL: `SHOW OPEN TABLES IN app WHERE In_use > 0` | `ShowOpenTablesStatement` | Optional `database` and `filter`; four result fields with integer lock counters. |
| MySQL: `SHOW TRIGGERS FROM app LIKE 'audit%'` | `ShowTriggersStatement` | Optional `database` and `filter`; eleven result fields with a nullable creation timestamp. |
| MySQL: `SHOW EVENTS IN app WHERE Status = 'ENABLED'` | `ShowEventsStatement` | Optional `database` and `filter`; fifteen result fields whose schedule operands are nullable. |
| MySQL: `SHOW CHARACTER SET LIKE 'utf8%'`, `SHOW CHARSET`, `SHOW CHAR SET` | `ShowCharacterSetsStatement` | Optional `filter`; four result fields. Every spelling is written as CHARACTER SET. |
| MySQL: `SHOW COLLATION WHERE Sortlen > 1` | `ShowCollationsStatement` | Optional `filter`; seven result fields, `Pad_attribute` only from MySQL 8.0. |
| MySQL: `SHOW CREATE DATABASE IF NOT EXISTS app`, `SHOW CREATE SCHEMA app` | `ShowCreateDatabaseStatement` | Required unquoted `database` name and `ifNotExists`, which asks for IF NOT EXISTS in the returned definition. |
| MySQL: `SHOW CREATE EVENT app.daily` | `ShowCreateEventStatement` | Required `event` as a `QualifiedName` with at most a database qualifier; seven result fields. |
| MySQL: `SHOW CREATE FUNCTION calc`, `SHOW CREATE PROCEDURE app.sync` | `ShowCreateFunctionStatement`, `ShowCreateProcedureStatement` | Required routine `QualifiedName`; six result fields where the definition is NULL without the required privilege. |
| MySQL: `SHOW CREATE TRIGGER app.audit` | `ShowCreateTriggerStatement` | Required `trigger` name with at most a database qualifier; seven result fields including a nullable creation timestamp. |
| MySQL: `SHOW CREATE TABLE users`, `SHOW CREATE VIEW app.v` | `ShowCreateTableStatement`, `ShowCreateViewStatement` | Required `table` or `view` as a `TableReference` resolved or diagnosed against the schema, never aliased. |
| MySQL 5.7+: `SHOW CREATE USER 'app'@'localhost'`, `SHOW CREATE USER CURRENT_USER()` | `ShowCreateUserStatement` | Required `account`: an `AccountName` or `CurrentAccount::Authenticated`. The single result field is labelled `CREATE USER for <user>@<host>`, with `%` for an omitted host. |
| MySQL: `SHOW FUNCTION STATUS LIKE 'calc%'`, `SHOW PROCEDURE STATUS WHERE Db = 'app'` | `ShowRoutineStatusStatement` | Required `RoutineKind` and optional `filter`; eleven result fields with datetime maintenance instants. |
| MySQL: `SHOW FUNCTION CODE app.calc`, `SHOW PROCEDURE CODE sync` | `ShowRoutineCodeStatement` | Required `RoutineKind` and routine `name`; two result fields, an integer position and the instruction text. |
| MySQL: `SHOW ENGINES`, `SHOW STORAGE ENGINES` | `ShowEnginesStatement` | Six result fields identify the engine, availability, description, transaction support, XA support, and savepoint support. Synonymous syntax produces the same operation. |
| MySQL: `SHOW PLUGINS` | `ShowPluginsStatement` | Five result fields identify plugin name, status, type, library, and license. |
| MySQL: `SHOW PRIVILEGES` | `ShowPrivilegesStatement` | Three result fields describe privilege name, applicable object context, and description. |
| MySQL: `SHOW FULL PROCESSLIST` | `ShowProcessesStatement` | Eight connection-activity result fields and a `ProcessQueryText` policy; ordinary PROCESSLIST requests the first 100 characters of active SQL, while FULL requests complete text. |
| MySQL: `SHOW ENGINE INNODB STATUS`, `SHOW ENGINE ALL MUTEX`, `SHOW ENGINE 'ndb' LOGS` | `ShowEngineReportStatement` | Required `engine` (an unquoted name or `EngineSelection::All`) and `EngineReport` (STATUS, MUTEX, LOGS); three text result fields. Named engines are written as quoted identifiers. |
| MySQL: `SHOW PROFILE CPU, BLOCK IO FOR QUERY 7 LIMIT 5 OFFSET 2` | `ShowProfileStatement` | Ordered `categories` (`ProfileCategory`, repeats kept), optional numeric `query` literal, and optional `ProfileLimit` whose comma form is normalized to LIMIT/OFFSET. Result fields are `Status`, `Duration`, then the measured categories' counters in server order. |
| MySQL: `SHOW PROFILES` | `ShowProfilesStatement` | No operands; `Query_ID`, `Duration`, and `Query` result fields. |
| MySQL 8.1+: `SHOW PARSE_TREE SELECT 1` | `ShowParseTreeStatement` | Required nested `statement`, bound with the same schema but never executed; one `Parse_tree` JSON result field. Rejected on grammars before 8.1. |
| MySQL: `SHOW GLOBAL VARIABLES LIKE 'max%'` | `ShowVariablesStatement` | A `VariableScope` (LOCAL, SESSION and an omitted scope all select the session) and an optional `PatternFilter` or `ConditionFilter` over the `Variable_name` and `Value` result fields. |
| MySQL: `SHOW STATUS WHERE Value > 0` | `ShowStatusStatement` | The same scope and restriction operands as SHOW VARIABLES, over the server's status counters. |
| MySQL: `SHOW WARNINGS LIMIT 5 OFFSET 2` | `ShowDiagnosticsStatement` | A `DiagnosticSelection` (WARNINGS reports every condition, ERRORS errors only) and an optional `RowWindow`; returns `Level`, `Code`, and `Message`. |
| MySQL: `SHOW COUNT(*) ERRORS` | `ShowDiagnosticCountStatement` | A `DiagnosticSelection`; returns one integer field labelled `@@session.error_count` or `@@session.warning_count`. |
| MySQL: `SHOW GRANTS FOR 'app'@'%' USING 'reader'` | `ShowGrantsStatement` | The described `AccountName` or `CurrentAccount` (SHOW GRANTS without FOR describes the authenticated account) and the roles shown as active; USING requires MySQL 8.0. |
| MySQL: `SHOW BINARY LOGS` | `ShowBinaryLogsStatement` | No operands; SHOW MASTER LOGS is a synonym, and releases before 8.0 omit the `Encrypted` field. |
| MySQL: `SHOW BINARY LOG STATUS` | `ShowBinaryLogStatusStatement` | No operands; SHOW MASTER STATUS is the same request and is written for releases before 8.4. |
| MySQL: `SHOW BINLOG EVENTS IN 'binlog.000002' FROM 157 LIMIT 5` | `ShowBinaryLogEventsStatement` | Optional log file name, start position kept as a numeric literal, and `RowWindow`. |
| MySQL: `SHOW RELAYLOG EVENTS FOR CHANNEL 'east'` | `ShowRelayLogEventsStatement` | The binary log event operands plus an optional channel name (MySQL 5.7 and later; a line feed in the name is rejected). |
| MySQL: `SHOW REPLICA STATUS FOR CHANNEL 'east'` | `ShowReplicaStatusStatement` | A `ReplicationVocabulary` (REPLICA or SLAVE, each only on releases whose grammar spells it) that selects the result labels, and an optional channel; the reported fields follow the release. |
| MySQL: `SHOW REPLICAS` | `ShowReplicasStatement` | A `ReplicationVocabulary`; SHOW SLAVE HOSTS returns the same fields under `Server_id`, `Master_id`, and `Slave_UUID` labels. |
| MySQL: `DESCRIBE t 'a%'`, `DESC t c`, `EXPLAIN t` | `DescribeTableStatement` | The resolved table reference and an optional column name or pattern decoded from an identifier, string, hexadecimal or bit literal. Explaining a statement stays with `ExplainStatement`. |
| MySQL: `HELP 'contents'` | `HelpStatement` | The decoded search topic; identifier and string spellings produce the same request. |

Server inspection statements are in `Model\Statement\Inspection`. Their
`resultColumns()` returns the requested metadata shape, without reading the server.
`ServerTextColumn` uses an `EngineField`, `PluginField`, or `PrivilegeField` enum and
records the producing scope. Its VARCHAR and NULL facts follow the field's role;
for example, a plugin's library can be NULL for a built-in plugin. Process fields
use `ProcessColumn` for identity and activity, and `ProcessInfoColumn` for query
text. The latter retains preview versus complete text and is nullable when no SQL
is active. `withQueryText()` refreshes both the request and the resulting text-length
fact in a new statement.

## Replication and server administration

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `PURGE BINARY LOGS TO 'binlog.000042'` | `PurgeBinaryLogsToStatement` | A required text-literal `logName`, the first log file kept. `PURGE MASTER LOGS` produces the same request. |
| MySQL: `PURGE BINARY LOGS BEFORE '2024-01-01'` | `PurgeBinaryLogsBeforeStatement` | A required cut-off `moment` expression, recorded without evaluation. |
| MySQL: `CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 3306, IGNORE_SERVER_IDS = (2) FOR CHANNEL 'c'` | `ChangeReplicationSourceStatement` | A nonempty list of `SourceSetting`s, one per `SourceOption`, typed by value kind: `SourceText`, `SourceNumber`, `SourceFlag`, `IgnoredServers`, `PrivilegeChecks`, `PrimaryKeyCheck`, `AnonymousGtids` or `AnonymousGtidUuid`; and an optional channel (5.7 and later). `CHANGE MASTER TO` and `MASTER_` option names produce the same request; a repeated option keeps its last value (server IDs accumulate). Out-of-domain values are a `replication-option` input violation, and log coordinates with `SOURCE_AUTO_POSITION = 1` or source with relay coordinates a `source-coordinates` violation. |
| MySQL: `CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a), REPLICATE_WILD_IGNORE_TABLE = ('a.%') FOR CHANNEL 'c'` | `ChangeReplicationFilterStatement` | A nonempty list of `ReplicationFilter`s, one per `FilterRule`: `DatabaseFilter`, `TableFilter` (two-part names), `WildTableFilter` (patterns containing a dot, else a `replication-filter` input violation) or `RewriteFilter` of `DatabaseRewrite` pairs; an empty list clears the rule and a repeated rule keeps its last list. MySQL 5.7 and later; the optional channel from 8.0. |
| MySQL: `START REPLICA IO_THREAD UNTIL SOURCE_LOG_FILE = 'b.1', SOURCE_LOG_POS = 4 USER = 'u' FOR CHANNEL 'c'` | `StartReplicaStatement` | A possibly empty list of `ReplicaThread`s (empty starts both), an optional `UntilCondition` (`SourcePosition`, `RelayPosition`, `GtidBoundary` or `GapsClosed`), `ReplicationCredential`s in USER, PASSWORD, DEFAULT_AUTH, PLUGIN_DIR order (not with the SQL thread alone) and an optional channel. `START SLAVE` produces the same request; incomplete or mixed stop points are a `replica-until` input violation. |
| MySQL: `STOP REPLICA SQL_THREAD FOR CHANNEL 'c'` | `StopReplicaStatement` | A possibly empty list of `ReplicaThread`s (empty stops both; `RELAY_THREAD` is the receiver) and an optional channel. `STOP SLAVE` produces the same request. |
| MySQL: `START GROUP_REPLICATION USER = 'u', PASSWORD = 'p'` | `StartGroupReplicationStatement` | Optional `ReplicationCredential`s (USER, PASSWORD, DEFAULT_AUTH), each at most once. |
| MySQL: `STOP GROUP_REPLICATION` | `StopGroupReplicationStatement` | No operands. |
| MySQL: `RESET REPLICA ALL FOR CHANNEL 'c', BINARY LOGS AND GTIDS TO 5` | `ResetServerStatement` | A nonempty list of `ResetTarget`s: `BinaryLogReset` (`RESET MASTER` / `BINARY LOGS AND GTIDS`, optional first index 1–2000000000), `ReplicaReset` (`SLAVE` / `REPLICA`, `all` flag, optional channel) and, before 8.0, `QueryCacheReset`. Written in the vocabulary of the bound release. |
| MySQL: `FLUSH LOCAL TABLES t` | `FlushTablesStatement` | A possibly empty list of physical `TableReference`s (empty means every open table) and a `BinlogPolicy` (`Omit` for `NO_WRITE_TO_BINLOG`/`LOCAL`). |
| MySQL: `FLUSH TABLES t WITH READ LOCK` | `FlushTablesWithReadLockStatement` | A possibly empty table list (empty means a global read lock) and a `BinlogPolicy`. |
| MySQL: `FLUSH TABLES t FOR EXPORT` | `FlushTablesForExportStatement` | A nonempty table list and a `BinlogPolicy`. |
| MySQL: `FLUSH PRIVILEGES, RELAY LOGS FOR CHANNEL 'c'` | `FlushServerStatement` | A nonempty list of `FlushTarget`s (`ServerFlush` keywords, each checked against the release, or `RelayLogFlush` with an optional channel) and a `BinlogPolicy`. |
| MySQL: `INSTALL COMPONENT 'file://c' SET PERSIST c.v = 1` | `InstallComponentStatement` | A nonempty list of text-literal component URNs and optional `AssignedSetting` variable initializations, each GLOBAL (the default) or PERSIST with one value. |
| MySQL: `UNINSTALL COMPONENT 'file://c'` | `UninstallComponentStatement` | A nonempty list of text-literal component URNs. |
| MySQL: `INSTALL PLUGIN audit SONAME 'audit.so'` | `InstallPluginStatement` | Required plugin `name` and text-literal `library`. |
| MySQL: `UNINSTALL PLUGIN audit` | `UninstallPluginStatement` | Required plugin `name`. |
| MySQL: `ALTER INSTANCE ROTATE BINLOG MASTER KEY` | `RotateMasterKeyStatement` | A required `MasterKeyScope` (`InnoDb` from 5.7, `BinaryLog` from 8.0); any other identifier is an `instance-action` input violation. |
| MySQL: `ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_admin NO ROLLBACK ON ERROR` | `ReloadTlsStatement` | A `TlsChannel` (`Main` when omitted, or `Admin`) and `rollbackOnError`, false for `NO ROLLBACK ON ERROR`. |
| MySQL: `ALTER INSTANCE RELOAD KEYRING` | `ReloadKeyringStatement` | No operands. |
| MySQL: `ALTER INSTANCE DISABLE INNODB REDO_LOG` | `AlterRedoLogStatement` | Required `enabled` flag; only `INNODB REDO_LOG` is accepted as the target. |
| MySQL: `CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' REQUIRE SSL` | `CloneRemoteStatement` | Required `donor` (`AccountName` or `CurrentAccount`), number-literal `port` and text-literal `password`; optional text-literal `directory` and `CloneEncryption` (`Required` or `Refused`). |
| MySQL: `CLONE LOCAL DATA DIRECTORY '/tmp/clone'` | `CloneLocalStatement` | A required destination-directory text literal. |
| MySQL: `BINLOG 'YWJj'` | `ApplyBinlogStatement` | A required text literal containing an encoded binary-log event. |
| MySQL: `RESTART`, `SHUTDOWN` | `RestartServerStatement`, `ShutdownServerStatement` | Distinct operations with no value operands. |
| MySQL: `KILL CONNECTION 42`, `KILL QUERY 42` | `KillConnectionStatement`, `KillQueryStatement` | A required `connectionId` expression and a concrete operation identifying what to stop. |
| MySQL 8.0+: `LOCK INSTANCE FOR BACKUP` | `LockInstanceStatement` | No operands; table locks stay with `LockTablesStatement`. |
| MySQL 8.0+: `UNLOCK INSTANCE` | `UnlockInstanceStatement` | No operands; `UNLOCK TABLES` stays with `UnlockTablesStatement`. |
| MySQL 8.0+: `CREATE RESOURCE GROUP batch TYPE = USER VCPU = 0-3 THREAD_PRIORITY = 10 DISABLE` | `CreateResourceGroupStatement` | Name of 1 to 64 characters, `ThreadCategory` (`USER` or `SYSTEM`), ordered `CpuRange` list, optional thread priority inside the category's range, and `ResourceGroupState` (enabled when omitted). |
| MySQL 8.0+: `ALTER RESOURCE GROUP batch VCPU = 2 DISABLE FORCE` | `AlterResourceGroupStatement` | Name and the requested changes only: CPU ranges, priority, state and `FORCE`. |
| MySQL 8.0+: `SET RESOURCE GROUP batch FOR 14, 15` | `SetResourceGroupStatement` | Name and the thread identifiers as unsigned integer literals; no threads means the current thread. |
| MySQL: `DROP RESOURCE GROUP workers FORCE` | `DropResourceGroupStatement` | One group `name` and a `force` flag requesting reassignment of affected threads to their default groups. |

## Maintenance and loading

| SQL | Returned type | Required structure and applicable options |
|-----|---------------|------------------------------------------|
| MySQL: `CHECK TABLE users QUICK FOR UPGRADE` | `CheckTablesStatement` | Nonempty physical `tables` and ordered `CheckOption` values. |
| MySQL: `REPAIR LOCAL TABLE users QUICK USE_FRM` | `RepairTablesStatement` | Nonempty physical `tables`, ordered `RepairOption` values, and `BinlogPolicy`. |
| MySQL: `OPTIMIZE TABLE users`, `ANALYZE TABLE users` | `OptimizeTablesStatement`, `AnalyzeTablesStatement` | Nonempty physical `tables` and `BinlogPolicy`; each class identifies its own operation. |
| MySQL: `CHECKSUM TABLE users QUICK` | `ChecksumTablesStatement` | Nonempty physical `tables` and `ChecksumMode` selecting automatic, stored, or row-scanned checksums. |
| MySQL: `ANALYZE TABLE users UPDATE HISTOGRAM ON score WITH 100 BUCKETS AUTO UPDATE` | `UpdateHistogramStatement` | One required `table`, nonempty bound `columns`, optional `BucketCount` from 1 to 1024, `RefreshPolicy`, and `BinlogPolicy`. |
| MySQL: `ANALYZE TABLE users UPDATE HISTOGRAM ON score USING DATA '{}'` | `ImportHistogramStatement` | One required `table` and bound `column`, an opaque text-literal `data` operand, and `BinlogPolicy`. |
| MySQL: `ANALYZE TABLE users DROP HISTOGRAM ON score` | `DropHistogramStatement` | One required `table`, nonempty bound `columns`, and `BinlogPolicy`; no sampling or import operands. |
| MySQL: `CACHE INDEX users, incoming IN hot` | `CacheTableIndexesStatement` | Nonempty `targets`, each a `TableIndexes` with one physical `table` and optional `NamedIndexes`; required `cache` is `DefaultCache` or a `CacheName`. |
| MySQL: `CACHE INDEX users PARTITION (p0,p1) IN hot` | `CachePartitionIndexesStatement` | One `target`, required `AllPartitions` or nonempty `NamedPartitions`, and required `cache`. |
| MySQL: `LOAD INDEX INTO CACHE users, incoming IGNORE LEAVES` | `PreloadTableIndexesStatement` | Nonempty `PreloadTarget` requests; each contains `TableIndexes` and its own `ignoreLeaves` policy. The current cache assignment supplies the destination. |
| MySQL: `LOAD INDEX INTO CACHE users PARTITION (ALL) IGNORE LEAVES` | `PreloadPartitionIndexesStatement` | One `PreloadTarget` and a required partition selection. No explicit cache destination. |
| PostgreSQL: `VACUUM (FULL, ANALYZE) orders (id)` | `VacuumStatement` | `VacuumOptions` (verbose, skipLocked, bufferUsageLimit, analyze, freeze, full, disablePageSkipping, indexCleanup, processMain, processToast, truncate, parallel 0–1024, skipDatabaseStats, onlyDatabaseStats) and ordered `MaintenanceTarget`s (resolved table, optional columns). Legacy `VACUUM FULL FREEZE VERBOSE ANALYZE` binds to the same options; column lists require ANALYZE; incompatible combinations are `maintenance-option` violations. |
| PostgreSQL: `ANALYZE (SKIP_LOCKED) orders (id)` | `AnalyzeStatement` | `AnalyzeOptions` (verbose, skipLocked, bufferUsageLimit) and ordered `MaintenanceTarget`s; an empty list analyzes every relation. |
| PostgreSQL: `CLUSTER (VERBOSE)` | `ClusterAllStatement` | Only the `verbose` flag; reclusters every previously clustered table. |
| PostgreSQL: `CLUSTER orders USING orders_pkey` | `ClusterTableStatement` | Required resolved `table`, optional `index` name and `verbose` flag; the legacy `CLUSTER index ON table` binds to the same class. |
| SQLite: `ANALYZE`, `ANALYZE main.t` | `AnalyzeAllStatement`, `AnalyzeNamedStatement` | No operands, or one qualified `target` naming a schema, table, or index. |
| SQLite: `VACUUM main`, `VACUUM INTO 'copy.db'` | `VacuumDatabaseStatement`, `VacuumIntoStatement` | An optional `schema` name; VACUUM INTO also has a required `destination` expression. |
| PostgreSQL: `REINDEX INDEX app.ix`, `REINDEX TABLE app.t`, `REINDEX SCHEMA app` | `ReindexObjectStatement` | Required object name, `ReindexObjectKind`, and PostgreSQL rebuild options. |
| PostgreSQL: `REINDEX DATABASE`, `REINDEX SYSTEM` | `ReindexDatabaseStatement` | User-table or system-table index selection in the current database. An optional database name records an explicit name assertion. |
| SQLite: `REINDEX`, `REINDEX ix` | `ReindexAllStatement`, `ReindexNamedStatement` | Rebuild all indexes, or resolve a required SQLite index/table/collation name. |
| PostgreSQL: `CHECKPOINT` | `CheckpointStatement` | A checkpoint request with no value operands. |
| PostgreSQL: `COPY orders (id) FROM STDIN WITH (FORMAT csv) WHERE id > 0` | `CopyFromStatement` | Required resolved `table`, unique `columns` (empty for all), `input` endpoint (`CopyFile`, `CopyProgram` or `CopyClient`), `CopyOptions`, and an optional `where` expression. Legacy keyword options bind to the same options; repeated or unknown options and options COPY FROM rejects are `copy-option` violations. |
| PostgreSQL: `COPY orders TO PROGRAM 'gzip > /tmp/o.gz' (FORMAT csv, HEADER)` | `CopyToStatement` | Required resolved `table`, unique `columns`, `destination` endpoint and `CopyOptions`; FREEZE, DEFAULT, FORCE_NOT_NULL, FORCE_NULL, HEADER MATCH, ON_ERROR and WHERE are rejected. `TO STDIN` is written `TO STDOUT`. |
| PostgreSQL: `COPY (DELETE FROM orders RETURNING id) TO STDOUT` | `CopyQueryStatement` | Required row-returning `query` (a query, or a data-modifying statement with RETURNING), `destination` endpoint and `CopyOptions`; SELECT INTO and statements without result columns are `copy-option` violations. |
| MySQL: `LOAD DATA LOCAL INFILE 'f' REPLACE INTO TABLE t FIELDS TERMINATED BY ',' (a, @v) SET b = @v` | `LoadFileStatement` | `LoadFormat` (`DATA` or `XML`), file literal, resolved table, optional `LoadScheduling`, `LOCAL`, `LoadSource` (file or S3), optional `DuplicateRows`, optional partitions, a `LoadLayout` (character set, `FieldLayout`, `LineLayout`, skipped rows), column or user variable targets, and SET assignments. `ROWS IDENTIFIED BY` is the line terminator (spelled as the XML row tag), `IGNORE n ROWS` equals `IGNORE n LINES`, `CHARSET` equals `CHARACTER SET`, and repeated separators keep the last one. IN PRIMARY KEY ORDER, PARALLEL and MEMORY are ignored by the server without BULK and are not retained. URL, COUNT, COMPRESSION and multi-byte enclosure or escape characters are diagnosed. |
| MySQL 8.0+: `LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK`; MySQL 8.2+: `LOAD DATA FROM S3 's3://b/t.' COUNT 4 IN PRIMARY KEY ORDER INTO TABLE t PARALLEL = 8 MEMORY = 1G ALGORITHM = BULK` | `BulkLoadStatement` | `LoadSource`, file or prefix literal, resolved table, optional file count, key-order flag, optional `REPLACE`, partitions, `LoadLayout`, and the release-specific COMPRESSION (8.4+), PARALLEL and MEMORY (8.2+, memory in bytes); the S3 source is available from 8.2. XML, LOCAL, column lists, SET, IGNORE, LINES STARTING BY and multi-byte field terminators are diagnosed. |
| MySQL 8.0+: `IMPORT TABLE FROM '/tmp/t1.sdi', '/tmp/t2*.sdi'` | `ImportTableStatement` | A nonempty list of file pattern text literals. |
| PostgreSQL: `LOAD 'auto_explain'` | `LoadLibraryStatement` | Required text-constant `file`, kept in its written spelling; binding never opens it. |

Index-cache requests retain the explicit index selection, including `INDEX ()`,
separately from an omitted selection. These operands describe the request; MySQL's
storage engine currently applies cache assignment and preloading to all indexes
of the target table ([CACHE INDEX](https://dev.mysql.com/doc/refman/8.4/en/cache-index.html),
[LOAD INDEX INTO CACHE](https://dev.mysql.com/doc/refman/8.4/en/load-index.html)).
`ignoreLeaves` requests nonleaf pages only. The four cache
statement forms return the maintenance result roles `Table`, `Op`, `Msg_type`, and
`Msg_text`. Binding neither allocates a cache nor loads pages.

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
