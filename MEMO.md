# SQL Semantics and MySQL Memory

Updated: 2026-10-30, after the user's corrections of the generator shape and the PBT goal.

## Mandatory rules from the user's review (must be followed, no exceptions)

Added 2026-10-31 (the user's instruction after the CREATE TABLE step):

- The instruction sets the goal. Do not stop at intermediate steps to report "what changed in this step"; work continues until the goal of the memo is reached or an input only the user can give is missing.
- `tests/Support` is forbidden by the toolkit rule and is not permitted. It has no necessity: `Analysis::accepted` and `Operands::aggregateFree` compensate type defects (values the types accept that are not executable SQL) and are removed as the types are corrected; `Embedding`, `Columns` and `Bindings` are test setup and belong in the property tests themselves, with properties defined over statements. Remove the namespace.

These rules override every other note in this memo and every existing piece of code.
The user had to correct the agent repeatedly on these points; the corrections are
recorded here so that they are not lost and not reinterpreted.

1. **What PBT is.** Eris generators generate the types of SQL Semantics analysis
   results. Those generated values run MemoryDB's phases after analysis. The property
   is that no error occurs. The only allowed exception is an error that depends on
   database state, such as a table definition that does not exist. That is the whole
   property. Write it plainly; the user does not want a clever formulation.
2. **What a generator is.** Every class under `tests/Generator` is a final
   `XxxGenerator` that implements `Eris\Generator`. Its constructor composes one
   delegate generator from the generators of the type's operands, following the
   type's structure and invariants, and nothing else. `__invoke` and `shrink` forward
   to the delegate. There is no other method. The canonical shape is:

   ```php
   final class OrderItemGenerator implements ErisGenerator
   {
       private ErisGenerator $delegate;

       public function __construct()
       {
           $this->delegate = Generators::map(
               static fn (array $data): OrderItem => new OrderItem(sku: $data['sku'], quantity: $data['quantity'], unitPriceYen: $data['unitPriceYen']),
               Generators::associative([
                   'sku' => Generators::elements('SKU-A', 'SKU-B', 'SKU-C'),
                   'quantity' => Generators::choose(1, 100),
                   'unitPriceYen' => Generators::choose(0, 10000),
               ])
           );
       }

       public function __invoke($size, RandomRange $rand) { return ($this->delegate)($size, $rand); }

       public function shrink(GeneratedValue $element) { return $this->delegate->shrink($element); }
   }
   ```

3. **What must not exist.** `forms()`, `additionalForms()`, `executionForms()`,
   `operands()`, `fromOperands()`, `selecting()`, `withPrerequisites()`,
   `refused*()` and every other method beyond the three above are not part of a
   generator. Enumerated fixed statement forms such as `'AlterTable/253'` are not
   derived from the type's structure and break the generator. Narrowing the
   candidates of an enum and substituting component generators have no place
   either, not even through constructor arguments: they distort the property that
   is to be established. The agent's proposal to allow narrowing and substitution
   through constructor arguments was wrong.
4. **Enforcement.** A PHPStan rule checks the shape: under `Tests\Generator` every
   class is a final `XxxGenerator`, implements `Eris\Generator`, declares `__invoke`
   and `shrink`, and declares no other method. The rule is added first, every
   generator is made to pass it, and only then is the rest of the goal pursued.
5. **No invented roles.** The agent's remark that fixed statement forms belong to
   sql-faker was unrelated and wrong. sql-faker is the grammar-based SQL generator of
   the fuzz comparison; it has no role in the Eris generators of semantic types.
6. **Plain work.** The user is not asking for the property to be done well; the user
   is asking for it to be done plainly. Attempts to do it cleverly produced the
   errors above and cost the user time.


## The type property SQL Semantics must have (user's review of 2026-10-31, mandatory)

The user reviewed the PBT results and corrected the direction of the work. These points
are binding.

1. **The analysis-result types are type-safe and protected by invariants: a value that can be
   instantiated is executable SQL, except for conditions that depend on database state.**
   MemoryDB exists to measure whether SQL Semantics' structured types have this property.
   The PBT exposed that they do not: the generators instantiated values the server rejects.
2. **The `statementInformation` record (diagnostics and warnings) mixes three different
   things and is therefore wrong as a structure.**
   - Problems of the structure itself (`DuplicateColumn`, `Misuse`, `MisplacedPrivilege`, ...)
     are violations of the type's invariants. The constructor must refuse them; the analysis
     must fail with an error instead of returning a value.
   - `ParseFailure` and `ParseStop` are analysis errors. They are never part of a value.
   - Conditions that depend on database state (`MissingTable`, `MissingColumn`,
     `UndeclaredVariable`, ...) are MemoryDB's responsibility. State management belongs to
     MemoryDB; the type carries only the name. If the structure could be built, it must run.
   - `Deprecation` and the other release-dependent notices are properties of the type. Whether
     a warning is raised is decided by the consumer (MemoryDB) from the type and the release,
     not recorded in the value.
3. **MemoryDB must not compensate for the type.** Mapping recorded diagnostics to server
   errors, or filtering generated values after construction (`Analysis::accepted`,
   `Operands`), hides the broken property instead of fixing it. Both are removed as the
   types are corrected.
4. The work proceeds in this PR without further confirmation: classify every diagnostic and
   warning class (table below), then change the types, the analysis, MemoryDB and the
   generators category by category, verifying each step with the fuzz replay and the PBT.

### Stored program bodies are read, not executed (verified on 8.4.7 and 5.7.44)

CREATE PROCEDURE reads its body with the grammar and performs only the checks the parser
performs while reading: syntax (1064, including built-in function arity), unknown collation
(1273), cast precision and width (1426, 1439), unknown system variable (1193), unknown EXPLAIN
format (1791), unknown storage engine (1286). Everything that needs name resolution or
structural consistency is deferred to CALL: unknown tables, columns and functions, GROUP BY,
value counts, partitions, sql_mode values, SELECT ... INTO arity, duplicate columns, several
primary keys and empty definitions of CREATE TABLE, duplicate ALTER TABLE columns.

Consequently a body member has a different meaning from the standalone statement: it is bound
to definitions and given its exact semantics only when the routine is called (the user's
formulation). The model keeps the two apart:

- The standalone statement type carries the execution invariants (class I above).
- A body member is a stored form (`Deferred*`, the existing convention) whose invariants are
  the parser's reading-time checks only. Statement kinds that lack a stored form get one.
  MemoryDB constructs the executable type from the stored form when the routine runs and
  reports that construction's refusal as the server error.
- The reading-time checks the server performs in bodies and standalone alike are the
  `ParseFailure` class: they are analysis errors, never values.

### Progress of the type correction

| Step | State |
| --- | --- |
| CREATE TABLE (class I: `DuplicateColumn`, `MultiplePrimaryKeys`, `NoColumns`, `UnknownKeyColumn` against written columns, `DuplicateKeyColumn`, `SpatialKeyArity`, `FunctionalIndexKind`; `NullablePrimaryKey` from 5.7) | Done. `CreateTable` refuses, as a constructor invariant, the written problems every release refuses (`TableProblems::problem` for `GrammarRelease::MySql5651`; a nullable primary key is accepted by 5.6, so only the release-aware lowering refuses it). The lowering (`CreateTableRule::create`) computes the problem for the selected release in the server's order (5.x and 9.x check an empty definition before the keys, 8.x after them) and raises `RejectedInput(RefusedCreateTable)` carrying the stored form `DeferredCreateTable`; a routine body keeps the stored form. Before the written problem the lowering refuses a partitioning or subpartitioning expression the parser refuses (`RefusedCreateTail(InvalidPartitionExpression)`, with the origins so MemoryDB quotes the operand): the server reports it while reading, before any written problem and even inside CREATE PROCEDURE (verified on 8.4.7 and 5.7.44). MemoryDB executes a refused definition with `RefusedDefinitionCommand` in the server's order: the tables the query and functional keys read, the reading-time type and collation conditions, the engine, the database (8.0+), the tablespace, the partition clause checks the server runs before it looks the table up (`PartitionDefinitions::early`: temporary table 1562, engine 1178 from 8.0, name spelling 1567, method mix 1500, count 1492/1504/1499, VALUES kind 1480, repeated name 1517, MAXVALUE not last 1481; verified before an existing table, before IF NOT EXISTS and before a duplicate column on 5.7.44 and 8.4.7; 5.7 reports them before an unknown database, 8.4 after it), 9.x empty definition, the existing table or the IF NOT EXISTS note (8.0+), the functional key expressions, then the written problem; 5.x reports the written problem before the database and the table. Standalone, through PREPARE/EXECUTE (`PendingTable`) and through CALL (`Interpreter::definition`). A refused definition nested in SHOW PARSE_TREE reports the debug-only syntax error, after the reading-time conditions of its operands, as the server does. Compared with native 8.4.7 and 5.7.44 on 45 statements each (partitioning, nesting, PREPARE, CALL, written problems): all equal; the earlier 33-statement comparison on 8.4.7, 9.1.0, 5.7.44 and 5.6.51 was all equal before the partition step. Known differences: `CREATE TABLE t (CHECK (1))` is 3812 on 8.4 and 1113/4028 in MemoryDB; the partition expression refused inside a CREATE PROCEDURE body is reported without the `near '...' at line N` suffix (the body notice carries no source map); partition-level `ENGINE` mixing (1497) is not implemented; a zero partition count inside a CREATE PROCEDURE body (`DefinitionIssue::ZeroPartitions`, natively 1504 at CREATE PROCEDURE) raises an unmapped-diagnostic LogicException in MemoryDB (pre-existing). |
| Reading-time refusals (class I/E: repeated relation names, unknown system variables, unknown character sets and collations, type bounds, repeated SIGNAL items, storage and database option conflicts) and their parse-time or post-parse ordering | Done in the second step: the lowering raises each as a `RejectedInput` whose `aborts` records whether the server finds it while parsing; MemoryDB orders the diagnostics area from it (`ContinuingRefusal`, `Entry\ImplicitTables`, `Reader::earlier`). A legacy body keeps an unresolved expression collation as `DeferredCollated`; a standalone `SIGNAL` with a condition name, a view body with a variable, a DECLARE type after its DEFAULT and a refused type's own deprecations follow the server. The model unit suite and doctests pass; the MemoryDB unit suite has only the nine known missing system-table row providers; PHPStan is clean over both packages; the three smoke runs pass (8.4.7 1075/1077, 5.7.44 1026/1027, 5.6.51 1018/1018, the residuals being the two known generator shapes); the property suite reports 165 errors in the classes listed under the implementation state; the known ordering gaps are listed below. |
| Program definitions (class I: the stored program restrictions of a body, a missing RETURN, duplicate parameters, undefined conditions and cursors, undeclared GET DIAGNOSTICS and FETCH targets, scoped local assignments, labels, the language/body representation) | Done in the third step: `CreateProcedure`, `CreateFunction`, `CreateTrigger`, `CreateEvent` and `AlterEvent` refuse construction when `BodyConditions::refusal` finds a condition in the body (`BodyInputs::first` for the declaration-input conditions, `BodyStructure::located` and the language, binding, array and call-role rules for the completed body); `ParameterList` refuses a repeated name (`ParameterList::repeated` is static). The lowering refuses before constructing: `ParameterRule::parameters` at the repeated or (8.0+) refused parameter name, `BodyRefusal::checked` (used by `DefinitionRule::routine`, `TriggerRule::create`, `EventRule::create` and `alter`) with `RejectedInput(RefusedProgramBody)` whose `end` is the refused instruction and `aborts` is true, except the body-wide restrictions of functions and triggers (result set, autocommit, commit, dynamic SQL, FLUSH, RESET) and a missing RETURN, which the server finds once the whole body is read (`BodyConditions::aborts`, `end` = end of the definition). Parameters are lowered before the body, as the server reads them. `FunctionImplementation` carries the two conditions its own two fields decide (the LANGUAGE characteristic agrees with the body representation; an SQL body reaches a RETURN), so `FunctionImplementationGenerator` filters on them before `CreateFunctionGenerator` filters on the rest; `ProgramStatementGenerator` offers a RETURN among its depth-zero alternatives, without which a nested function body could never return (the 5.x generator smoke exhausted its filter budget there). `ProgramFacts` no longer reports a missing RETURN and `ParameterList::deriveRelation` no longer reports a duplicate; `BodyConditions::publish` and `first` are gone (`order` keeps the notice ordering). MemoryDB follows two more native orderings found by its unit suite: the host-resolution notes (1285) of every `user@host` recipient written before a privilege-level refusal precede the 1144 (`Entry\Account\Recipients`, in `ContinuingRefusal`), and PREPARE accepts a GRANT or REVOKE whose level the server refuses only at EXECUTE (`Command\Prepared\PendingRefusal`, raised through `DeferredPreparation`; the statement commits the open transaction first). Verification: model unit suite, doctests and PHPStan clean; MemoryDB unit suite with only the nine known system-table failures; generator smoke 8.4.7 1077/1077, 5.7.44 1027/1027, 5.6.51 1016/1018 (the two known flaky filters). |

### Reading-time refusals (second step of the type correction)

`tests/Support` is deleted: the 185 generators no longer filter their values through `Analysis::accepted`, the four operand filters are gone, and the unit tests build their statements directly. The property suite then measures the type defects directly (232 failing properties before this step).

Refused while reading, as the server does even inside a stored program body (verified on 5.6.51, 5.7.44 and 8.4.7), each now an analysis error raised by the lowering with a constructor invariant where the condition is release-independent:

- A relation name written twice in one FROM clause or table list (`RelationNames`, ER_NONUNIQ_TABLE): byte-equal spellings are a constructor invariant of `Select`, `DeferredSelection`, `Update`, `DeletionInput`, `LockTables`, `CacheIndex`, `LoadIndex`, `DropTable`, `FlushTables` and the maintenance statements, refused by `FromRule::checked` and the table-list lowerings; spellings that differ by letter case or by a written database equal to the current one stay contextual (`FromScope::unique`, `TableNames::record`, `DropTable::deriveStatement`). An alias may repeat across databases (`t1 a, d.t1 a` is accepted natively). `InputNames` and its test are deleted.
- An unknown system variable (`VariableRule::unknown`, ER_UNKNOWN_SYSTEM_VARIABLE): plain names, key cache names, component variables of 8.0+ and the legacy variable-with-component form follow `SystemNames::structured`. On 8.4 `SET a=@@b` with both unknown reports `b` (the value) first; 5.7 reports `a` first (known difference, the legacy target is a bare name resolved after lowering).
- An unknown character set or collation name in DDL, SET NAMES and conversions (`CharsetRule`), an unknown collation of a COLLATE operator on 8.0+ (`OperandRule::collated`; 5.6 and 5.7 resolve it when the body runs), and a collation of another character set in database options (`DatabaseOptions::mismatch`) and column definitions (`ColumnCollations::mismatch`, ER_COLLATION_CHARSET_MISMATCH; MemoryDB accepted the column form before this step).
- Type bounds (`TypeLimits::problem`) of columns (`ElementRule::sized`, also ALTER TABLE), parameters, declarations and RETURNS types (the server names no parameter, declaration or return type: `''`), and cast targets (`CastProblems::target` in `ConstructRule::cast`).
- A condition information item set twice (`Signal`/`Resignal` invariant, ER_DUP_SIGNAL_SET) and a storage option written twice (`StorageOptions::checked` invariant, ER_FILEGROUP_OPTION_ONLY_ONCE); `DatabaseEncryption` holds only `Encryption` (ER_WRONG_VALUE_FOR_VAR raised by the lowering).

The completion helpers that re-lower a statement to collect notices (`SyntaxWarnings::completed` and the `Syntax*` classes) catch these refusals and record them as the stopping failure. Known ordering gaps: a stored program restriction (CHECK/LOCK not allowed in stored procedures, 1314) is checked after lowering, so a body `CHECK TABLE t, t` reports 1066 where the server reports 1314; an undefined condition before a repeated relation in a body reports 1066 where the server reports 1319. `DO SUM(1)` is accepted natively and refused by MemoryDB (1111). Partition-level `ENGINE` mixing (1497) is not implemented.

### Where the server finds a refused condition: while parsing or after (verified on 5.6.51, 5.7.44 and 8.4.7)

A `RejectedInput` carries `aborts`. It records which of two moments the server finds the condition in, because MemoryDB orders the diagnostics area from it:

- `aborts = true`: found while parsing, and parsing stops. Nothing written after it is read. MemoryDB (`Reader::rejection`) collects the notices up to `end` and reports the error.
- `aborts = false`: either found while parsing without stopping (the server records it and keeps reading: an expression `COLLATE` name on 8.0+), or found after the whole statement is read (a condition checked at contextualization or execution). MemoryDB (`ContinuingRefusal`) collects every parse-time notice and parse-time error of the statement first, reports the first parse-time error if there is one, and otherwise the refusal. When no database is selected and an unqualified table name is written before `end` (`Entry\ImplicitTables`; common table expression names, SHOW targets with their own `FROM db`, and empty names excepted), the server opens that table first and MemoryDB reports `No database selected`.

Evidence (`warn.php`, `warn-nodb.php` in the scratchpad):

| Condition | 8.0+ | 5.6 / 5.7 |
| --- | --- | --- |
| CAST precision (`CAST(1 AS TIME(7))`) | parse-time, stops | parse-time, 5.7 records several (`[1681, 1426, 1426, 1064]`) |
| `CONVERT(... USING nosuch)`, `CHAR(... USING)`, `SET NAMES nosuch`, `CAST(... CHARACTER SET)` | parse-time, stops | parse-time, stops |
| Expression `COLLATE nosuch` | parse-time, continues (`SELECT 1 COLLATE zz, CAST(1 AS TIME(7))` -> `[1273, 1426]`; `SELECT CAST(1 AS TIME(7)), 1 COLLATE zz` -> `[1426]`) | resolved at execution after every table is open (`SELECT 'a' COLLATE zz FROM t` with no database -> 1046); a stored body keeps it (`DeferredCollated` with a plain scalar operand) |
| `collation_name` of DDL and `SET NAMES` | parse-time, stops (`CREATE TABLE t (a INT, b INT(300)) COLLATE zz` -> `[1681, 1273]`) | after reading (`CREATE TABLE t(a INT) COLLATE missing` with no database -> 1046) |
| Character set of a table, column or LOAD DATA | parse-time, stops (`CREATE TABLE t(a INT) CHARACTER SET missing` with no database -> 1115) | after reading (same statement -> 1046; `LOAD DATA ... CHARSET missing` -> 1046) |
| Unknown system variable | after reading (`SELECT @@nosuch, CAST(1 AS TIME(7))` -> `[1426]`; `SELECT 1 FROM t WHERE @@missing` with no database -> 1046; `SELECT @@missing FROM t` -> 1193) | after reading (5.7 the same); 5.6 `SET unknown=...` with later malformed input still names the variable (`VariablePrefix`) |
| Relation name written twice | after reading (`SELECT * FROM t, t WHERE CAST(1 AS TIME(7))` -> `[1426]`; with no database `SELECT 1 FROM t AS a, t AS a` -> 1046 but `SELECT 1 FROM d.t AS a, d.t AS a, t` -> 1066) | the same |
| Column type bounds and column collation mismatch | after reading (`CREATE TABLE t(a INT(11), b INT(300), c INT(11))` -> `[1681, 1681, 1681, 1439]`; with no database -> 1046) | the same |
| Parameter, DECLARE and RETURNS type bounds | parse-time, stops; the type's own deprecation precedes it (`CREATE FUNCTION f() RETURNS FLOAT(0,0)` -> `[1681, 1439]`); a DECLARE is checked after its DEFAULT value (`DECLARE a INT(256) DEFAULT (SELECT SQL_NO_CACHE 1)` -> `[1681, 1681, 1439]`) | the same |
| `ALL DISTINCT`, database option conflicts, `ENCRYPTION 'X'`, `SIGNAL ... SET x, x` | `ALL DISTINCT` after reading; the others parse-time | the same |
| Variable in a view body (`CREATE VIEW v AS SELECT @@nosuchvar`) | 1351 before the unknown name (`ViewRule::variables`) | the same |
| Condition name of a standalone `SIGNAL c` | 1319 before the SET items (`SignalRule::condition` refuses it outside a program) | the same |
| GRANT/REVOKE privilege outside its level (1144, 3619, 3835) | after reading, before the recipients are looked at (`GRANT SELECT(a) ON *.* TO absent, u` -> 1144; `LevelRule`); a stored body keeps the request. A global privilege at the database level (1221) is reported per recipient when the statement runs (`GRANT RELOAD ON d.* TO absent, u` -> `[1410/1133, 1221]`), so it stays an execution condition (`PrivilegeChecks::usage`) | the same (REVOKE ALL at the global level with a routine kind is refused only on 5.x) |
| `SELECT *` / `DO *` / `EXPLAIN SELECT *` without tables (1096) | after reading; a stored body keeps it (`QueryMeaning::of`, `InvocationRule::checked`) | the same |
| System variable read under a scope the catalog does not give (1238) | after reading, even inside a stored body on 8.0+ (`CREATE PROCEDURE p() SELECT @@SESSION.max_connections` -> 1238); assignments (1229, 1228, read-only 1238) when the statement runs, so a stored body keeps them (`VariableRule::scoped`) | reads too are kept by a stored body on 5.x |
| Set function or window function outside a query block (1111, 3593), named window nobody defines (3579) | after reading in KILL, PURGE, SET, INSTALL COMPONENT and SHOW filters (`StatementCalls`); `DO SUM(1)` and subqueries are query blocks; a named window is refused in DO and in query blocks without tables (`SELECT ROW_NUMBER() OVER w` -> 3579), while a query block with tables opens them first (`SELECT (SELECT 1 FROM t WHERE ROW_NUMBER() OVER w)` -> 1146) and INSERT/UPDATE/DELETE/HANDLER/CALL report their tables first, so those stay execution conditions; a stored body keeps all of them | the same |
| Stored program body conditions: `RETURN` outside a function (1313), duplicate parameter (1330), undefined condition (1319), undefined cursor (1324), undeclared GET DIAGNOSTICS or FETCH target (1327), `OLD` row update in a trigger (1362), statements no stored program may hold (1314, 1303, 1305, 1576), labels (1308, 1309, 1310), `LANGUAGE SQL AS '...'` (1064) | parse-time, stops (`CREATE PROCEDURE p() BEGIN RETURN 1; SELECT * FROM; END` -> 1313; `p(a INT, a INT) SELECT * FROM` -> 1330; `BEGIN SIGNAL c; SELECT * FROM; END` -> 1319; trigger `BEGIN SET OLD.a = 1; SELECT * FROM; END` -> 1362) | the same |
| Body-wide restrictions of functions and triggers: result set (1415), autocommit (1445), commit (1422), dynamic SQL, FLUSH, RESET (1336); missing RETURN (1320) | after the whole body is read (`CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1; RETURN 1; SELECT * FROM; END` -> 1064; the same with `COMMIT`, `SET autocommit = 1` and `PREPARE s FROM 'SELECT 1'` -> 1064; without the syntax error -> 1415, 1422, 1445, 1336) | the same |
| Body-level names (probed for the next step): a bare `SET x = 1` target that is neither a local nor a system variable (1193), a `LIMIT n` operand no program declares (1327), a locking clause naming no table of the query (3568, also standalone), a multiple-delete target written twice (1066, also standalone) | parse-time, stops (`CREATE PROCEDURE p() BEGIN SET x = 1; SELECT * FROM; END` -> 1193; `BEGIN SELECT 1 LIMIT n; SELECT * FROM; END` -> 1327; `BEGIN SELECT 1 FROM t FOR UPDATE OF u; SELECT * FROM; END` -> 3568; `BEGIN DELETE FROM d.t, d.t USING t; SELECT * FROM; END` -> 1066); aliases differing only by letter case are distinct (`SELECT 1 FROM t a, t A` is accepted) | not yet probed |
| GRANT/REVOKE level through PREPARE; recipient host notes | `PREPARE s FROM 'REVOKE ALL ON PROCEDURE * FROM u'` succeeds and EXECUTE answers 1144; `GRANT SUPER ON d.t TO u@host, v@other` answers `[1287, 1285, 1285, 1144]`: the host notes are parse-time, the level check follows (`u@'1.2.3.4'` and `v@'%'` get no note) | not probed |
| EXPLAIN INTO without FORMAT=JSON (6006/6007), EXPLAIN FOR CONNECTION with INTO (6009) or ANALYZE (1235) | parse-time at the options, before the explained statement is read (`EXPLAIN INTO @v SELECT * FROM` -> 6006; also inside a CREATE PROCEDURE body before a later syntax error); `EXPLAIN FORMAT=JSON INTO @v SELECT 1` is accepted; `EXPLAIN ANALYZE ... INTO` is a grammar error on 8.4 | 8.1 reads the explained statement first (recorded earlier in `SyntaxFormats::into`) |
| Multiple-delete target written twice with the same spelling (1066) | parse-time (`DELETE FROM t, t USING t`, `DELETE t, t FROM t`, `DELETE a, a FROM t a`, `DELETE FROM t.*, t.* USING t`, with `nosuch.t` twice, before a later syntax error); `DELETE FROM test.t, t USING t` with `test` current -> 1066 too (contextual, kept for execution); `t, T` -> 1109 unknown table (distinct) | 5.6 the same (`ChangeRule::completed`) |
| Trigger row words: `SET OLD.a` (1362), `SET NEW.a` in an AFTER trigger (1362), `NEW` read in a DELETE trigger and `OLD` read in an INSERT trigger (1363) | parse-time (`CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW BEGIN SET @a = NEW.a; SELECT * FROM; END` -> 1363); a FROM alias named `new` shadows the row word (`SELECT new.a INTO @a FROM t AS new` in an INSERT trigger -> 1146), so reads stay scope-resolved and only the assignments are a definition invariant for now | not probed |
| `EXPLAIN ... INTO @''` | the empty variable name (3061) is refused before the INTO/FOR CONNECTION rules | not probed |
| `EXPLAIN FORMAT=XML` (1791) | parse-time at the options, before the INTO target, the INTO rules and a later syntax error (`EXPLAIN FORMAT=XML INTO @'' FOR CONNECTION 0` -> 1791; `EXPLAIN FORMAT=XML SELECT * FROM` -> 1791; also inside a CREATE PROCEDURE body); the format names a release knows are TRADITIONAL and JSON, TREE from 8.0 | not probed |
| Bare `LIMIT n` / `INTO n` (1327) | inside a body the name is resolved while parsing (`BEGIN SELECT 1 LIMIT n; SELECT * FROM; END` -> 1327) and a parameter satisfies it (`CREATE PROCEDURE p(n INT) SELECT 1 LIMIT n` is accepted): a definition invariant (`ConditionBindings::instruction`). Outside a program the name is resolved with the query, after reading (`SELECT 1 LIMIT n FROM` -> 1064; a CTE nobody uses raises nothing), so it stays MemoryDB's resolution condition (`Session\Problem\Reading`) | not probed |
| Bare `SET x = 1` target in a body (1193) | once the SET statement is read, after the notices of its values (`CREATE PROCEDURE p() SET missing=WEIGHT_STRING('value' AS CHAR(1.5))` answers the fractional precision first, recorded earlier in `BodyFactsTest`); a variable of a closed inner block does not count (`BEGIN BEGIN DECLARE y INT; END; SET y = 2; END` -> 1193), a system variable does (`SET sql_mode = ''`) | 5.6 and 5.7 report the unknown target before reading its value (`BodyRefusal` ends the refusal at the item start there) |
| Locking clause naming no table of its query (3568) or one table twice (3569) | after reading, before the tables are opened (`SELECT 1 FROM t FOR UPDATE OF u WHERE` -> 1064; `SELECT 1 FROM t FOR UPDATE OF u` -> 3568 although `t` does not exist; `OF test.t` -> 1146, resolved with the tables; a common table name is accepted); inside a body while parsing (`BEGIN SELECT 1 FROM t FOR UPDATE OF u; SELECT * FROM; END` -> 3568). Still a derived misuse resolved by MemoryDB: a reading-time refusal through `Locking\Conditions` on the live lowering duplicated origins and misjudged set operations, JSON tables and derived tables (the walk was written for a fresh lowering in `SyntaxWarnings`), so the refusal needs the derivation's `LockedTables::check` instead | not probed |
| Multiple-delete target naming no input table (1109) | after reading, before the tables are opened (`DELETE a FROM t WHERE` -> 1064; `DELETE a FROM nosuch` -> 1109; an alias hides the table name: `DELETE t FROM t AS x` -> 1109); while parsing inside a body (`BEGIN DELETE a FROM t; SELECT * FROM; END` -> 1109). `TargetReferences::missing` (absent under every name comparison) is a `DeletionInput` invariant refused by `ChangeRule::multiple`; a target that matches only under another letter-case setting stays contextual (`ChangeFacts::targets`) | not probed |

Reproduced forms behind the remaining "syntax error" property failures (`probe-gen.php` in the scratchpad executes a generator's values and prints the refused SQL): `PREPARE s FROM 'text'` and `FROM @v` (the prepared text is data the server parses when the statement runs); `SET NAMES = 1`, `SET NAMES := NOW(103)`, `CHAR SET x` as plain variable names (the SET keywords are not variable names: `RefusedNamesAssignment`); `CREATE FUNCTION ... AS $text$...$text$` and `LANGUAGE name` on releases without the library body (release forms of routine bodies and characteristics); body statements a stored program cannot hold (`START GROUP_REPLICATION`, `CREATE VIEW`, `DROP TABLE` inside CREATE PROCEDURE: `ProgramRestrictions`); and parameter types written with repeated or contradictory modifiers (`BIGINT(28) ZEROFILL ZEROFILL SIGNED`, `INTEGER(41) UNSIGNED SIGNED`), whose native treatment is still to be probed.

Known gaps recorded for later: a window that refines a name nobody defines is refused while reading (3579), although the server resolves the window's own ORDER BY and PARTITION BY items first (`SELECT 1 WINDOW w AS (missing_window ORDER BY (1,2))` answers 1241 natively and 3579 in MemoryDB; a `ColumnUse` there answers 1054 natively); a body `SIGNAL c SET MESSAGE_TEXT = @@nosuch` without a declaration of `c` reports 1193 where the server reports 1319; a duplicate ENUM value written before a refused parameter type (`p(x ENUM('v','v'), a INT(256))`) loses its 1291 note, because the note is MemoryDB's and the refused statement is never published; `SELECT missing COLLATE \`\`` reports 1273 where the server reports 1054 first; a body `SELECT 1 COLLATE zz` on 8.0+ followed by a syntax error keeps `[1273, 1064]` as the server does.

### Classification of the diagnostic and warning classes

The 164 diagnostic classes and 7 warning classes, classified by where their condition belongs. `M/` is `SqlSemantics\Platform\MySql\Statement\`, `S/` is `SqlSemantics\Statement\`.

| Class | Count | Meaning |
| --- | --- | --- |
| I | 107 | Type invariant: the constructor refuses the value with `InvalidConstruction`; the analysis fails with an error. |
| I/S | 3 | Both: the structural part becomes a constructor invariant, the part that needs the installed table moves to MemoryDB. |
| E | 32 | Analysis error: the analysis raises an error; the class and its recording disappear from the values. |
| S | 24 | Database state: removed from the type; MemoryDB checks its own catalog, session and variables. |
| N | 5 | Release-dependent notice: a property of the type; MemoryDB raises the warning from the type and the release. |

| Diagnostic or warning | Class |
| --- | --- |
| `M/Account/Problem/AccountNameFailure` | I |
| `M/Account/Problem/InvalidFactorPair` | I |
| `M/Account/Problem/InvalidUserAttribute` | I |
| `M/Account/Problem/MisplacedPrivilege` | I |
| `M/Account/Problem/NumberOutOfRange` | I |
| `M/Account/Problem/PriorityOutOfRange` | I |
| `M/Account/Problem/RefusedColumnLevel` | E |
| `M/Account/Problem/RejectedPasswordHash` | E |
| `M/Account/Problem/RepeatedTlsAttribute` | I |
| `M/Account/Problem/ResourceGroupNameTooLong` | I |
| `M/Account/Problem/Role/RefusedCreateRole` | I |
| `M/Account/Problem/Role/RefusedGrantRoles` | I |
| `M/Account/Problem/RoleOrPrivilegeMismatch` | E |
| `M/Account/Problem/RoutineColumnPrivilege` | I |
| `M/Account/Problem/UnknownGrantColumn` | S |
| `M/Account/Problem/User/RefusedRenameUser` | I |
| `M/Alter/Problem/DuplicateColumn` | I/S |
| `M/Alter/Problem/PartitionEntryRefused` | E |
| `M/Alter/Problem/RepeatedTable` | I |
| `M/Alter/Problem/TableExists` | S |
| `M/Alter/Problem/UnknownAlterChoice` | I |
| `M/Alter/Problem/UnknownColumn` | S |
| `M/Call/Problem/Aggregate/AggregateNestingConstraint` | I |
| `M/Call/Problem/FrameConstraint` | I |
| `M/Call/Problem/InvalidJsonDocument` | I |
| `M/Call/Problem/InvalidJsonPath` | I |
| `M/Call/Problem/InvalidNamedConstant` | I |
| `M/Call/Problem/InvalidWindowCount` | I |
| `M/Call/Problem/NamedArgument` | I |
| `M/Call/Problem/ReservedFunction` | I |
| `M/Call/Problem/Type/ArgumentDomainMismatch` | S |
| `M/Call/Problem/UnsupportedWindowing` | I |
| `M/Call/Problem/Window/NestingConstraint` | I |
| `M/Call/Problem/WindowConstraint` | I |
| `M/Call/Problem/WindowOperandConstraint` | I |
| `M/Call/Problem/WindowRefinement` | I |
| `M/Call/Problem/WrongArgumentCount` | I |
| `M/Dml/Handler/RefusedRead` | E |
| `M/Dml/Handler/RefusedSubquery` | E |
| `M/Dml/Problem/DuplicateColumn` | I |
| `M/Dml/Problem/EvaluationWildcard` | I |
| `M/Dml/Problem/GeneratedColumnWrite` | S |
| `M/Dml/Problem/Insert/InsertGrouping` | I |
| `M/Dml/Problem/LoadConflict` | I |
| `M/Dml/Problem/Read/OrderingPosition` | I |
| `M/Dml/Problem/RepeatedDeleteTarget` | E |
| `M/Dml/Problem/UnknownDeleteTable` | I |
| `M/Dml/Problem/ValueCountMismatch` | I |
| `M/Dml/Problem/WriteMisuse` | I |
| `M/Dml/Problem/ZeroFileCount` | I |
| `M/Expression/Problem/CharacterCastRange` | I |
| `M/Expression/Problem/CollationMismatch` | S |
| `M/Expression/Problem/IllegalCollationMix` | S |
| `M/Expression/Problem/InvalidCharacterString` | I |
| `M/Expression/Problem/InvalidJsonCast` | I |
| `M/Expression/Problem/NotSupportedYet` | I |
| `M/Expression/Problem/OperandColumns` | I |
| `M/Expression/Problem/RefusedNumberLiteral` | I |
| `M/Expression/Problem/RefusedScalar` | E |
| `M/Expression/Problem/RefusedTemporalLiteral` | I |
| `M/Expression/Problem/SpatialArithmetic` | S |
| `M/Expression/Problem/TimeZoneConversion` | S |
| `M/Expression/Problem/TooBigPrecision` | I |
| `M/Expression/Problem/UnknownCharset` | S |
| `M/Expression/Problem/UnknownCollation` | S |
| `M/Hint/Comment/HintError` | N |
| `M/Name/AmbiguousAlias` | I |
| `M/Name/EmptyObjectName` | I |
| `M/Name/InvalidProjectionAlias` | I |
| `M/Name/MissingTableQualifier` | I |
| `M/Name/Problem/MismatchedDefinitionName` | I |
| `M/Name/Problem/NameTooLong` | I |
| `M/Notice/Deprecation` | N |
| `M/Notice/HeartbeatPeriod` | N |
| `M/Notice/NameConversion` | N |
| `M/Notice/ParseFailure` | E |
| `M/Notice/ParseStop` | E |
| `M/Notice/TrimmedName` | N |
| `M/Partition/Problem/DefinitionIssue` | I |
| `M/Partition/Problem/InvalidPartitionExpression` | I |
| `M/Partition/Problem/MissingPartitions` | I |
| `M/Partition/Problem/RefusedKeyAlgorithm` | I |
| `M/Partition/Problem/RefusedScalar` | E |
| `M/Query/Problem/AggregateInOrdering` | I |
| `M/Query/Problem/CountMismatch` | I |
| `M/Query/Problem/Misuse` | I |
| `M/Query/Problem/NonGroupedColumn` | S |
| `M/Query/Problem/Operand/RefusedIntoSetOperand` | E |
| `M/Query/Problem/Operand/RefusedQuery` | E |
| `M/Query/Problem/Operand/RefusedSelection` | E |
| `M/Query/Problem/Operand/RefusedSetClause` | E |
| `M/Query/Problem/Operand/UnenclosedUnion` | E |
| `M/Query/Problem/Option/RestrictedSelectOption` | I |
| `M/Query/Problem/OrdinalOutOfRange` | I |
| `M/Query/Problem/SelectOptionConflict` | I |
| `M/Query/Problem/UndeclaredVariable` | S |
| `M/Query/Problem/UnknownPartition` | S |
| `M/Query/Problem/UnknownQualifier` | I |
| `M/Query/Problem/UnpartitionedTable` | S |
| `M/Replication/Problem/RefusedLogSubquery` | E |
| `M/Replication/Problem/RefusedPurgeOperand` | E |
| `M/Replication/Problem/RefusedSetting` | I |
| `M/Replication/Problem/RefusedSourceNumber` | I |
| `M/Routine/Event/Problem/OperandWidth` | I |
| `M/Routine/Event/Problem/RefusedOperand` | E |
| `M/Routine/Problem/AssignedConditionOperand` | I |
| `M/Routine/Problem/EmptyEventAlteration` | I |
| `M/Routine/Problem/LanguageBodyMismatch` | E |
| `M/Routine/Problem/ParameterScopeMismatch` | I |
| `M/Routine/Problem/ProgramProblem` | I/S |
| `M/Routine/Problem/RefusedProgramBody` | I |
| `M/Routine/Problem/ScopedLocalAssignment` | E |
| `S/Reference/Column/AmbiguousColumn` | S |
| `S/Reference/Column/MissingColumn` | S |
| `S/Reference/Table/ConflictingTables` | removed: a context declares each relation once (`AnalysisContext` invariant) |
| `S/Reference/Table/MissingTable` | S |
| `M/Server/Database/Problem/CharsetConflict` | I |
| `M/Server/Database/Problem/InvalidEncryption` | I |
| `M/Server/Database/Problem/InvalidUpgradeName` | I |
| `M/Server/Database/Problem/ReadOnlyConflict` | I |
| `M/Server/Problem/BucketCountOutOfRange` | I |
| `M/Server/Problem/FractionalNumber` | I |
| `M/Server/Problem/HistogramTables` | removed: the server answers the request in result rows; MemoryDB builds them |
| `M/Server/Problem/NonUniqueTable` | I |
| `M/Server/Problem/RefusedKillOperand` | E |
| `M/Server/Problem/RefusedSpatial` | I |
| `M/Server/Problem/RefusedXaIdentifier` | I |
| `M/Server/Problem/SpatialProblem` | I |
| `M/Server/Problem/StorageProblem` | I |
| `M/Server/Problem/UnknownHistogramColumn` | removed: the server answers the request in result rows; MemoryDB builds them |
| `M/Table/Problem/Column/SridRange` | I |
| `M/Table/Problem/Creation/DuplicateKeyColumn` | I |
| `M/Table/Problem/Creation/FunctionalIndexKind` | I |
| `M/Table/Problem/Creation/InvalidIndexName` | I |
| `M/Table/Problem/Creation/RefusedCreateTail` | E |
| `M/Table/Problem/Creation/SpatialKeyArity` | I |
| `M/Table/Problem/DuplicateColumn` | I |
| `M/Table/Problem/ExplicitIndexOrder` | I |
| `M/Table/Problem/Generation/InvalidAttribute` | I |
| `M/Table/Problem/Generation/RefusedOperand` | I |
| `M/Table/Problem/IncorrectColumnName` | I |
| `M/Table/Problem/InvalidColumnAttribute` | I |
| `M/Table/Problem/MultiplePrimaryKeys` | I |
| `M/Table/Problem/NoColumns` | I |
| `M/Table/Problem/NullablePrimaryKey` | I |
| `M/Table/Problem/RefusedJsonAttribute` | I |
| `M/Table/Problem/UnattachedEnforcement` | E |
| `M/Table/Problem/UnknownIndexParser` | S |
| `M/Table/Problem/UnknownKeyColumn` | I/S |
| `M/Table/Problem/ViewColumnCount` | I |
| `M/Table/Problem/WrongRelationKind` | S |
| `M/Table/Problem/ZeroKeyPrefix` | E |
| `M/Type/Problem/CastSizeInput` | E |
| `M/Type/Problem/InvalidTypeSize` | I |
| `M/Utility/Problem/GeneratedColumnEntry` | E |
| `M/Utility/Problem/InvalidExplainTarget` | I |
| `M/Utility/Problem/RefusedConnectionExplain` | I |
| `M/Utility/Problem/RefusedExplain` | E |
| `M/Utility/Problem/RefusedExplainInto` | I |
| `M/Utility/Problem/RefusedNamesAssignment` | I |
| `M/Utility/Problem/UnknownExplainFormat` | I |
| `M/Utility/Problem/UtilityMisuse` | I |
| `M/Utility/Show/RefusedFilter` | E |
| `M/Variable/Problem/UnknownSystemVariable` | S |
| `M/Variable/Problem/UnstructuredVariable` | I |
| `M/Variable/Problem/VariableMisuse` | S |
| `M/View/Problem/BodyRestriction` | I |
| `M/View/Problem/RefusedBody` | E |
| `M/View/Problem/RefusedColumns` | E |
| `M/View/Problem/RefusedExistenceClause` | I |
| `M/View/Problem/RefusedQuery` | E |

## Goal and authority

Build MySQL Memory so that it correctly executes SQL Semantics' meaning-bearing,
structured values after analysis, and verify its behavior against real MySQL.
The required verification consists of **two mechanisms**:

1. PHP-Fuzzer generates SQL through SQL Faker and compares execution on MemoryDB
   with execution on real MySQL started through Testcontainers.
2. PHPUnit/Eris generates SQL Semantics values and executes MemoryDB's phases
   after SQL Semantics analysis. PHPStan rules guarantee corresponding generators
   exist for all semantic model types.

The user's latest clarification governs this memo. It supersedes the previous
"three checks" description and the previous blanket permission to accept SQL
errors in PBT. SQL -> semantic model -> SQL reconstruction is **not required**.
Its earlier test results do not satisfy either mechanism above and must not be
reported as progress toward their acceptance conditions.

This memo records requirements, observed implementation state, and remaining
work separately. Existing code, previous agent plans, test counts and old goal
labels do not create requirements. Historical notes remain in Git history rather
than being repeated here as competing instructions.

## Required verification

### PHP-Fuzzer: compare execution with real MySQL

```text
PHP-Fuzzer input -> SQL Faker -> the same generated SQL
                                  |              |
                                  v              v
                              MemoryDB       real MySQL
                                             via Testcontainers
                                  |              |
                                  +-- compare ---+
                                   execution results
```

The normal fuzz path uses MemoryDB and **one** real database. The rule about
real-database differences does not require three databases, two native servers
per trial, or a second native execution for every generated input.

Only differences that also occur between real database executions under
comparable versions, settings and input histories may be accepted as differences.
A MemoryDB limitation, a plausible "environmental"
explanation, or two values having the same PHP type is not sufficient. Any
exception needs evidence for the particular real-database variability and must
remain limited to that variability. Deterministic result content must still
match. Do not delete rows, columns, diagnostics or other mismatches to obtain a
passing comparison.

SQL errors in this differential check are observable execution results: compare
the actual errors and any partial results. Matching native and MemoryDB SQL
errors are distinct from PBT's success property below. MemoryDB implementation
failures must fail the fuzz run even if a transport presents them as SQL errors.

The two required fuzz acceptance results are:

- A successful **15-minute run without supplied corpus seeds**, starting with an
  empty corpus and still generating SQL through SQL Faker.
- Successful execution comparison for **all SQL Faker seeds establishing 100%
  grammar coverage** of the applicable grammar. Verify the corpus against SQL
  Faker's coverage contract; neither a file count nor a partial replay proves
  success. Do not omit failing productions or narrow the grammar to pass.

Record the source revision, database release, grammar/profile, commands,
completed inputs, elapsed time and failures. A recorded mutation RNG seed is
useful for reproduction and is not a supplied corpus seed. Supported-version
obligations remain; a separate 900-second run for every conceivable combination
of release, mode and driver was an agent-created matrix, not a user requirement.

Keep the comparison direct, using the existing SQL Faker, database and
Testcontainers APIs. Do not restore the rejected observation/fixture/lifecycle
framework, a dedicated Diff suite, or per-input catalog/state synchronization.
Keep the generated history available for reproducing state-dependent failures.

### Eris: execute generated semantic values after analysis

```text
Eris generators for all semantic types
    -> composed, meaning-bearing concrete analysis results
    -> MemoryDB's post-analysis phases
    -> successful operation
```

Every meaning-bearing, structured model type in `sql-semantics` and
`sql-semantics-mysql` needs a corresponding real Eris generator, including nested
values, interfaces and enums in the model. Generators must be used in executable
compositions. Merely constructing objects, checking their PHP classes, or
referencing generator classes does not establish correct execution.

PHPStan must guarantee the model-to-generator mapping. Discover the model
inventory independently of the generator inventory so that a missing generator
cannot disappear from the checked set. Keep the requested configurable
class-unused rule, with its selected classes configured in NEON. Static existence
and reachability checks do not prove that all value domains are exercised.

Start execution with a complete concrete result of SQL Semantics analysis.
MemoryDB must consume its semantic information through the phases after analysis.
Do not reconstruct SQL and analyze it again, or recover missing meaning from
retained SQL text or parser ASTs. Typed construction/publication in generators
must produce the complete semantic input needed by those phases.

**SQL errors are not generally acceptable in PBT.** An error caused by runtime
state, such as a referenced table not existing, may be allowed. This is not a
permission to catch every `SqlError`: non-state-dependent SQL errors must fail,
as must representation errors and implementation failures. If generated
prerequisites satisfy the statement's state dependencies, the generated sequence
must complete successfully.

Generate dependent inputs through Eris composition, for example a table
definition followed by an insertion for that definition. Preserve those
relationships during standard Eris shrinking. The `then()` callback executes the
generated inputs; it must not reconstruct their dependencies. Fixed expected
error codes, account snapshots, source-text markers and scope-cleanup assertions
can belong to other tests, but they do not define this execution property.

Property units are the **concrete types returned by `analyze()`**, not each small
nested type and not one aggregate search for all statements. Use this structure:

```text
packages/mysql-memory/tests/
├── Generator/
│   ├── Semantics/   # Mirrors the core semantic model types
│   └── MySql/       # Mirrors the MySQL semantic model types
└── Property/
    ├── AlterDefaultRoleProperties.php
    ├── ChecksumTableProperties.php
    ├── FlushTablesProperties.php
    └── <ConcreteAnalysisResult>Properties.php
```

There are no `MySql` or `Statement` subdirectories under `Property`, and no
`tests/Eris` directory. Each property class has a runnable `satisfies*` method
and starts from its concrete generator, such as
`new AlterDefaultRoleGenerator($release)`, rather than a generic factory selected
by a class-string. Nested generators compose into executable roots. Use Eris's
own generation and shrinking; do not relocate rejected custom machinery.

Concrete-type separation allows independent counterexample searches and budgets.
The 15-minute requirement belongs to PHP-Fuzzer; it is not a per-property Eris
requirement. Report actual PBT trials and duration without inventing a new gate.

## Current implementation state

Snapshot after the generator, error-mapping, property and fuzz work of 2026-10-30/31 on branch
`feat/mysql-memory` (delivery through [PR #665](https://github.com/k-kinzal/ztd-query-php/pull/665),
still a draft). This snapshot is **not** an acceptance claim; the open decisions below
need the user's answer.

| Area | Observed state | What remains unproved or incorrect |
| --- | --- | --- |
| Generator shape | `rules/GeneratorShapeRule.php` is registered: every class under `tests/Generator` is a final `XxxGenerator` implementing `Eris\Generator` with `__construct`, `__invoke` and `shrink` only. All generator files pass it. | None known. |
| Generator inventory | `SemanticCatalogue` requires a generator for every public model type reachable from the statements through constructor parameters, including types named only in constructor PHPDoc (analysis information and source spans excluded): 1,095 types, 1,095 generator files, none outside the inventory. The notice and catalog generators the integration tests used were removed and the tests rewritten on the real catalog and the notice enums. | 39 inventory generators are composed by no other generator or test (interfaces whose users compose their implementers directly, aliases, `Leg`/`Path`, `HintTable`, `Escape`, contract types); they exist for the inventory only, so the unused-class check no longer covers `tests/Generator`. |
| Generator smoke (8 values per generator) | 8.4.7: 1,076 of 1,078 generators pass; 5.7.44: 1,025 of 1,028; 5.6.51: 1,015 of 1,019 (the rest are skipped as not applicable to the release). Every diagnostic generator composed by `DiagnosticGenerator` constructs values at 8.4.7 and 9.1.0 (60 samples each); the role refusals, deferred EXPLAIN and the recursion floor of CREATE TABLE were corrected for that. | Residual: `Update` and `WindowRefinement` (release mixing at 8.4.7); `Update` and `CreateFunction` (predicate exhaustion at 5.7.44); `InsertQuery`, `QuantifiedComparison`, `CreateFunction`, `OrderedSetOperation` (5.6.51). |
| PBT policy | Each property allows only the `SqlError` codes listed in its `MISSING_STATE` (database objects, declarations, credentials, installed components, replication state, server capabilities); every other error fails. Statement generators keep only statements whose analysis records no parse-level refusal and no structural problem (`Tests\Support\Analysis`). | Whether analysis-level refusals may be excluded from generation, and whether server capabilities count as database state, are open decisions (below). |
| Diagnostic-to-error mapping | Every `Diagnostic` the model declares (153 generator arms, 12 samples each at 8.4.7, 5.7.44 and 5.6.51) maps to a server error through `Session\Problem\Errors::error`, except three the server answers in result rows or resolves while planning (`HistogramTables`, `UnknownHistogramColumn`, `ConflictingTables`). The new mappings were verified on an 8.4.7 server: CREATE ROLE anonymous or repeated (1396), GRANT of roles to an anonymous grantee (3876), to itself (4027) or of an anonymous role (3524), RENAME USER pairs that cannot succeed (1396 for the first such source), user attributes (3982), column privileges on an unknown column (1054), EXPLAIN ANALYZE TRADITIONAL (1235), EXPLAIN INTO formats (6006/6007), unknown EXPLAIN formats (1791). `MisplacedPrivilege::$error` is now typed to the five error names the analysis uses. | The EXPLAIN utility misuse carries no format name, so the unknown-format and INTO-format errors are reported without it. The RENAME USER failing pair follows the server's in-order processing observed for single failures. |
| Property suite | After the second step (`tests/Support` deleted, every statement value executed as generated): 165 errors in the full run of 2026-10-31 (`XDEBUG_MODE=off php -d memory_limit=8G vendor/bin/phpunit --testsuite property` in `packages/mysql-memory`). By class: GRANT/REVOKE privilege-level mismatches (17), `No tables used` (10), GLOBAL/SESSION scope of a variable (9), stored-program body restrictions reported by the server only when the program runs (trigger result sets, ITERATE/LEAVE labels, cursors, RETURN placement, duplicate parameters, commits; 12), syntax of values the server reads as text (7), repeated relation names the constructor does not see (6: aliases across a FROM list and a locking clause, CREATE TABLE LIKE, DROP VIEW), aggregates in KILL/PURGE/SHOW (5), collations unavailable in the release or mismatching a charset (7), size parameters of tablespace options (4), EXPLAIN INTO formats (4), LOAD DATA BULK combinations (4), partition definition counts (4), JSON attribute and path text (4), executable types the compiler lacks (4), and single occurrences listed in the run. | Each class is the next step: make it a constructor invariant, a reading-time refusal with the right `aborts`, or a MemoryDB state check, and correct the generator that still composes it. |
| Native fuzz path | `ExecutionTarget` compares every reply exactly except: SHOW STATUS counters observed to differ between two fresh servers started one after the other (`build/native-twins`), the startup counters of the image, SHOW VARIABLES instance identity (host name, derived file names, server UUID) and connection/statement identifiers, SHOW ENGINE INNODB STATUS logical sections, and SHOW ENGINE INNODB MUTEX record form. | The connection/statement identifiers (`pseudo_thread_id`, `statement_id`) and the startup counters follow the image's readiness connections, not server variability; keeping them is a decision for the user. The 5.6/5.7 InnoDB monitor is not parsed (`InnoDbStatus::logical` returns null), so those releases cannot pass that comparison. |
| Native grammar replay (8.4.7) | 2,180 seeds (the 100 % production manifest): 2,161 pass. 19 fail and are set aside under `build/native-replay-847-failing` with one log per run (`build/native-replay-847-run*.txt`). | Failing seeds: SHOW [GLOBAL\|SESSION\|LOCAL] STATUS (counters MemoryDB does not model: handler, binlog cache, connection and InnoDB internals), SHOW ENGINE ALL STATUS/MUTEX, SHOW RELAYLOG EVENTS (relay log named after the host), CREATE FULLTEXT INDEX (duplicate-name check order), SHOW INDEX/KEYS (index comment lost), REPAIR TABLE USE_FRM on a temporary table, DESC ANALYZE (plan text), SHOW PROCESSLIST (connection ids), SHOW OPEN TABLES, CREATE AGGREGATE FUNCTION under LOCK TABLES (fixed after the run, not re-verified), XA BEGIN after work outside the transaction, SHOW PROCEDURE/FUNCTION STATUS (creation time). |
| Seedless native fuzz (8.4.7) | 15-minute empty-corpus run: 300 generated inputs verified, no failing case (`build/native-seedless-847.txt`). | The run used the SHOW VARIABLES exception above; without the connection/statement identifier part it would stop at the first SHOW VARIABLES. |
| Ordinary checks | PHPStan over the whole package (32 minutes per run): the complete run on the final tree reports only the five `Tests\Support` namespace findings (decision 5 below); every generator passes the shape, coverage, strict and toolkit rules. Unit suite (final tree, 6 processes, 5 minutes): 14,389 tests, 9 failures, all `SystemSchemasTest` (104–184 declared system tables without row providers per release, a known gap); no timeouts. The `sql-semantics-mysql` tests of the refactored rules and of the two retyped model classes pass. | The `Tests\Support` namespace (decision 5) is the only PHPStan finding left; the system-table row providers are a MemoryDB gap. |

### Decisions needed from the user

1. Statements whose analysis records a parse-level refusal (`ParseFailure`) or a structural problem other than a missing object are not generated for execution (`Tests\Support\Analysis::accepted`). MemoryDB turns those problems into errors that do not depend on database state, so the property cannot hold for them. The alternative is a second property that checks the mapped error.
2. Server capabilities are treated as database state: debug-only statements (SHOW PROCEDURE CODE, SHOW PARSE_TREE), XA START/END options the server never supports, components and plugins that are not installed, replication that is not configured.
3. Server refusals that depend on the value written, not on state, remain failures: for example `Incorrect usage/placement of 'SQL_CACHE'`, `Variable 'MYSQL_ERRNO' can't be set to the value of 'NULL'`, invalid SRS definitions. Many were removed by generating valid values (temporal literals, SQLSTATEs, charsets, collations, system variables, EXPLAIN formats, JSON attributes, JSON paths, replication filter patterns, component URNs, password hashes); the remaining 60 are listed in `build/property-nonstate.txt`.
4. Operands typed only at run time (parameter markers, user variables) reach MemoryDB's compiler with no resolved type and raise a `LogicException` instead of executing or failing as the server would. This is a MemoryDB defect class, not a generator issue.
5. `Tests\Support` is a namespace prefix the toolkit lint forbids; the helpers (`Analysis`, `Operands`, `Embedding`, `Bindings`, `Columns`) live there because the rename was declined. A permitted name is needed.
6. Exact comparison of SHOW STATUS cannot pass without modelling InnoDB handler statistics and the image's provisioning connections; the memo's rule leaves only the two options above.

### Existing verification evidence and its limits

Local logs under `packages/mysql-memory/build/` are evidence for particular runs, not
portable completion gates:

- `native-replay-847-loop.txt` and `native-replay-847-run*.txt`: the iterative replay of the 2,180 seeds.
- `native-seedless-847.txt`: the 15-minute seedless campaign.
- `native-twins/twinseq-*.txt`: SHOW STATUS, SHOW VARIABLES and SHOW ENGINE replies of two fresh servers started one after the other (8.4.7 with and without a write history, 5.7.44).
- `property-nonstate.txt`: the server refusals of the last property run that do not depend on database state.

An earlier set of "two-server" logs measured one reused container twice and was discarded; `Testcontainers::run()` returns the running container of the same class, so the experiments stop the first server before starting the second.

State after the CREATE TABLE step of the type correction (2026-10-31):

- `sql-semantics-mysql`: whole-package PHPStan clean (seven `problem()`/`refusal()`/`accepts()` methods received their unit tests); unit suite green except `StopReplicaTest::testRenderWritesTheThreads` (`RELAY_THREAD` is rendered as written; last changed in an earlier commit of this branch, not by this step); doctests green (the five "Reading the problem" examples read the problem from the stored body through `TableProblems::problem`).
- `mysql-memory`: doctests green; property suite 86 failing properties (104 before this step); none of them concerns CREATE TABLE's written problems. The remaining CREATE TABLE failure is `Undeclared variable` in a standalone definition (a stored-program-only operand accepted by the type: the S/E classes still to correct); CREATE PROCEDURE on 5.6 fails in a generator invariant (`SetPassword`).
- Generator smoke (8 samples per generator): 8.4.7 1,080 generators, 2 failing (release mixing in `UpdateGenerator` and `WindowRefinementGenerator`, as before); 5.7.44 1,030 generators, 5 failing (`UpdateGenerator` and `CreateFunctionGenerator` cannot satisfy their release predicate within Eris's retry budget; two more flow generators than before reach that limit).

### Body-level names (fourth step of the type correction, in progress)

Done so far: multiple-delete targets written twice (`DeletionInput` invariant, `ChangeRule::targets` refusal with `RepeatedDeleteTarget`, `DeferredMultipleDelete` keeps only the missing-target, read-only, input-column and sampling conditions), EXPLAIN INTO without FORMAT=JSON and FOR CONNECTION with ANALYZE or INTO (`Explain`, `ExplainConnection` and `DeferredExplain` invariants, `ExplainRule::modern` refusals at the options, after the INTO target name check; MySQL 8.1 reads the explained statement first), the trigger row assignments (`TriggerRows` as a `CreateTrigger` invariant and `TriggerRule::create` refusal; `TriggerAssignments` keeps only the column resolution), unknown EXPLAIN format names (`Explain`, `ExplainConnection` and `DeferredExplain` accept only TRADITIONAL, JSON and TREE; the lowering refuses the names a release lacks before the INTO target), bare `LIMIT`/`INTO` names a body does not declare (`ConditionBindings::instruction`, so a definition invariant through `BodyConditions`), and bare `SET` targets of a body that are neither locals nor system variables of the release (`BodyNames`, refused by `BodyRefusal::checked` and filtered by the five definition generators; release-dependent, so not a type invariant). MemoryDB's `Command\Explain\Options` is deleted: every option it mapped is now refused while reading. Not done: the locking clause (see the evidence row); the standalone `LIMIT n` stays MemoryDB's resolution condition; reads of `NEW`/`OLD` in a trigger stay scope-resolved.

Fifth step, started right after that run: a multiple-delete target naming no input table under any name comparison (`TargetReferences::missing`) is a `DeletionInput` invariant refused by `ChangeRule::multiple` (after reading standalone, while parsing in a body); `DeferredMultipleDelete` keeps only the read-only, input-column and sampling conditions and its generator composes a read-only target from a derived or JSON table alias; MemoryDB's `Entry\Dml\Targets::statement` keeps only the repeated-name and database checks.

The full property suite after the fourth step (2026-11-01, `XDEBUG_MODE=off php -d memory_limit=8G vendor/bin/phpunit --testsuite property`): 151 errors. By message class: multiple-delete target naming no input table (12, made a `DeletionInput` invariant right after this run), collation not valid for the character set (10), repeated relation names under contextual comparison (7), syntax errors (12, two forms), function precision above 6 (6), unresolved locking-clause names (5), `AT LOCAL` unsupported (5), invalid JSON path (5), SELECT option placement (5), incorrect argument type to a variable (5), size parameters (5), `No executable type` compiler failures (13 over nine classes), invalid JSON attribute (3), LOAD DATA option combinations (5), and single occurrences (unknown database or collation, datafile path, group function misuse, cpu id, UPDATE with ORDER BY, UPGRADE DATA DIRECTORY NAME, generation marker, GTID_ONLY value, and others).

The property subset of the definitions, MultipleDelete and EXPLAIN after this step (all releases) still fails in these classes: a multiple-delete target that matches no input under any name comparison (1109, `TargetReferences::missing`; natively to be probed for its moment), repeated relation names that differ only by letter case or by the current database (1066, contextual), database-qualified locking-clause names MemoryDB resolves before the tables (3568 where the server answers 1146/1049), SELECT options written twice or misplaced (`SelectOptionConflict`), function precision above 6 (`TooBigPrecision`), `REQUIRE_ROW_FORMAT` values other than 0 and 1, invalid JSON attribute text, invalid event `AT` literals, and `EXPLAIN ANALYZE ... INTO` on 8.1 (the generator now skips it).

The property subset of the five program definitions after the third step (`tests/Property/Create{Procedure,Function,Trigger,Event}Properties.php`, `AlterEventProperties.php`, all releases) fails only in these classes, all parse-time refusals natively (evidence table above):

- A locking clause naming no table of the query (3568, `QueryError::UnresolvedLockedTable`), also standalone: an invariant of the query (`FOR UPDATE OF t` names a table or alias of its FROM clause, byte-equal spelling; the contextual database-qualified forms stay).
- On 5.6 the `CreateProcedureGenerator` still reaches `SetVariables`' invariant `A standalone password assignment is represented by SetPassword.` through `StatementGenerator` -> `SetVariablesGenerator` (its tuple predicate covers the first item only when the list has one item; the body form must be checked).

### Known implementation gaps

Missing system-table row providers (9 unit failures), the unknown-type compiler
failures above, SHOW STATUS/PROCESSLIST/OPEN TABLES counters and identities, the
5.x InnoDB monitor parser of the fuzz target, EXPLAIN ANALYZE plan text, the index
comment of CREATE INDEX in SHOW INDEX, REPAIR TABLE USE_FRM on temporary tables,
the duplicate-key-name check order of CREATE FULLTEXT INDEX, XA START after work
outside a global transaction, and the earlier recorded findings (prepared execution
after its default database is dropped, implicit base-table locks for views, global
read locks, UPDATE target-table subquery restrictions, KILL connection identities,
legacy performance_schema removal, partial KEY partition routing, legacy temporary-table
IMPORT, oversized VARCHAR conversion, nondeterministic ALTER defaults, replication
receiver state).

## Work required to reach the goal

Work on the verification design across the relevant scope **before** returning
to one-counterexample-at-a-time fixes. The next work is:

1. **Correct PBT's success criterion across all properties.** Replace the blanket
   `SqlError` acceptance with handling limited to errors actually caused by
   runtime state. Other SQL errors and implementation failures must fail. Ensure
   generated prerequisites lead to successful execution and that a test can
   expose a non-state SQL error instead of swallowing it.
2. **Audit generator completeness and composition.** Check the independently
   discovered semantic inventory, the corresponding generators, their use in
   executable roots, valid combinations, dependent state and shrinking. Include
   nested, conditional and diagnostic-bearing values. Their mere appearance in
   separate error-code tests does not establish this PBT property. Correct the
   generators or semantic model where the required property reveals a gap;
   do not suppress failures or omit difficult types. Run the complete PHPStan
   coverage/unused checks and the corrected concrete-type PBT suite.
3. **Audit fuzz comparison exceptions.** Review `ExecutionTarget::status()`,
   `environment()`, `engines()` and `latches()`. Retain only differences supported
   by real-database variability, with the exception limited to what the evidence
   establishes. Remove unsupported relaxations. Keep one real database in the
   normal comparison path; this audit does not authorize a three-database design.
4. **Resolve exposed implementation defects.** Resume CHECKSUM and the other
   reproduced findings after the verification mechanisms enforce the intended
   contracts. Add relevant regression tests and compare actual native replies.
   Implement missing behavior rather than empty providers or output masking.
5. **Run ordinary checks on the resulting revision.** Resolve the full-suite
   failures and run the relevant repository quality checks. Distinguish focused
   passes, a full-suite pass and checks that predate later edits.
6. **Meet both native fuzz conditions.** Verify the SQL Faker 100%-coverage seed
   inventory and successfully compare all its inputs; separately run the same
   differential target from an empty corpus for 15 minutes without a failing
   case. Fix discovered counterexamples and repeat the affected verification.
   State the releases/profiles actually tested and remaining version coverage.
7. **Deliver and report accurately through PR #665.** Report the two required
   mechanisms, corrected PBT results, PHPStan coverage, ordinary checks and the
   native fuzz evidence. Remove reconstruction from the PR/goal completion
   narrative. Do not declare completion while these requirements remain unmet.

SQL reconstruction work is outside this task's acceptance scope. This memo does
not require deleting unrelated renderer functionality or existing package tests;
it removes reconstruction as a requested workstream and completion condition.

## Corrections the user has repeatedly had to make

| User instruction or correction | Mistake that must not recur |
| --- | --- |
| First refresh the overall verification system and structure, then refine individual behavior. | Continuing local fixes while the verification design remains incorrect, or treating a plan/one working path as the completed refresh. |
| Use the concrete values returned by SQL Semantics `analyze()` as executable PBT roots. | Testing small leaf types alone even though they must compose into a complete request to execute MemoryDB. |
| Put property classes directly under `tests/Property`; show the intended tree clearly. | Retaining unnecessary `MySql`/`Statement` nesting or answering about file contents when the objection is the directory structure. |
| Separate searches by concrete analysis-result type for counterexample efficiency. | Putting every statement into one aggregate property/factory, or confusing generator coverage with property granularity. |
| Use a concrete generator such as `new AlterDefaultRoleGenerator($release)`. | Hiding the root behind `StatementGenerator::accountRequests($release, SomeType::class)` and similar generic selectors. |
| Use standard Eris generation, composition and shrinking. Delete `tests/Eris`. | Building custom sampling/execution/shrinking machinery or relocating the rejected machinery under a different directory. |
| Test successful consumption by the phases after analysis. | Substituting `assertInstanceOf`, expected error codes, account snapshots, source markers and cleanup assertions for the requested execution property. |
| Generate dependencies inside generators. | Building the semantic input or its prerequisite sequence in `then()` and losing coherent shrinking. |
| Provide generators for all meaning-bearing, structured semantic types and enforce their existence with PHPStan rules. | Covering only selected statements, counting files as behavior coverage, or allowing unused generators to give the appearance of completeness. |
| Only state-dependent SQL errors may be allowed in PBT; other SQL errors are failures. | The agent's broad interpretation that any SQL refusal is acceptable without generated prerequisites. The current catches still embody this mistake. |
| PHP-Fuzzer must use SQL Faker and compare MemoryDB with a real DB started through Testcontainers. | Replacing that check with reconstruction, a private assurance framework, state restoration, or locally asserted expected outcomes. |
| Accept only differences that also occur between real DB executions; this does not mean using three databases. | Arbitrary "environmental" exemptions, type-only checks without evidence, or adding a second native server to every trial. |
| Pass SQL Faker's 100%-grammar-coverage seeds and run seedless fuzz for 15 minutes. | Reporting a partial corpus, focused native probes, a different fuzz target, or an invented version/profile/time matrix as fulfillment of the request. |
| SQL -> semantic model -> SQL reconstruction is unnecessary; the latest clarification removes it. | Continuing to call it one of three required checks or counting its successful runs toward this goal. |
| State current status, shortages and the actual goal accurately. | Promoting agent-generated plans into user requirements, preserving contradictory historical notes as instructions, or reporting local passes as overall completion. |

Earlier project conventions continue to apply: use a dedicated worktree and PR;
write artifacts in English; preserve protected files; follow the other `sql-*`
packages' ordinary test organization. Keep semantic meaning in the relevant typed
structures rather than a generic `Operation`/`Facts`/`SourceMap` aggregate or a
parser/text fallback. The public database API uses PDO or mysqli with an explicit
MySQL version. Do not restore the rejected `mysql-memory/docs`, `resources`, `bin`
or auxiliary test directories as workarounds. These conventions do not add new
verification mechanisms beyond the two specified above.
