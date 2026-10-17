# Constructing semantic structures directly

SQL Faker exercises the SQL-to-execution path. The direct structure campaign exercises a different input: objects built using SQL Semantics' public constructors, without first generating or parsing SQL. It tests whether the semantic model's types and invariants are sufficient for its consumer. It does not measure grammar coverage; SQL Faker owns that measure.

From `packages/mysql-memory` in a repository checkout:

```sh
composer test:property
composer fuzz:structures -- 100
composer fuzz:structures -- 100 example-seed
```

The campaign discovers concrete types from the reviewed semantic value inventories. Its generator interprets native constructor types and PHPDoc collection types, chooses enum cases, and builds fresh nested objects. A readonly field's declared `@var` type takes precedence over the corresponding constructor parameter: for example, a constructor may accept a broad list and check that every element is a select item before retaining it as a `non-empty-list<SelectExpression|Star|TableWildcard>`. Constructor invariant violations cause another construction attempt. The generator has finite depth and attempt budgets; exhaustion is reported as an incomplete trial and never as a successful execution. Primitive values and collection lengths are bounded, so passing a finite campaign is evidence about the sampled inputs.

Before choosing a nested type, the generator checks whether its constructor types admit a value within the remaining depth. Interface choices and constructors that require nested objects sample a depth from the type-admissible range, including the maximum: recursive types can produce small inhabitants without always expanding every operand to the full limit. Nullable values, empty collections and default arguments provide terminating alternatives. The selected root can use the shared 2,000-attempt cap. Each nested constructor retains a smaller depth-dependent cap that includes its descendants, preventing one nested alternative from taking the entire root search budget. These bounds inspect types only; they do not inspect SQL diagnostics or execution outcomes. A selected root is never replaced by a different root when generation fails.

A rejected constructor keeps its completed arguments so the next trial can resample one argument instead of rebuilding unrelated children. Repeated rejection by the same invariant switches that candidate to complete resampling until the rejected constraint changes. The invariant is identified by its constructor type and check call site, rather than a message containing generated names. Detailed messages remain in the rejection report. Both strategies use the same shared attempt cap. They inspect constructor types and invariant refusals only; they do not use MySQL outcomes to choose or discard values. Exhaustion remains a failure. The bounded search can still exhaust; neither a passing inventory run nor a repaired seed establishes that every random construction search will finish.

`Scalar` and `RowOperand` are separate roles. A row contains at least two fields; a subquery with a fixed multi-column projection has a row role, while an unknown catalog-dependent width remains a resolution dependency. Ordinary comparisons and membership constructors reject incompatible fixed shapes. Stored programs additionally need operand roles whose preparation is deferred until an instruction is reached. These values preserve their complete operands and do not implement `Scalar`. A stored definition can therefore retain a row-valued condition, assignment or projection without making that value valid in an ordinary scalar position.

`DeferredSelection` retains a SELECT's complete item list, modifiers, input relation, WHERE and HAVING predicates, grouping, ordering and row limit. `DeferredOrderItem` and `DeferredGrouping` preserve row-valued and deferred sort operands while ordinary items retain their scalar or output-ordinal roles. `DeferredAssignments` retains all SET items and their written scopes. Their consumers bind names and subqueries from those structures in the invocation's current state. Direct native tests reuse the same stored definition and CALL object before and after creating a table, adding a column, populating it and dropping it. Standalone selections with deferred operands are refused as ordinary query values; their typed `RefusedSelection` diagnostic retains the semantic dependencies needed to select the correct SQL error. It retains no source SQL or parser AST. Other query forms, including set operations and window clauses, still use their existing query rules; this extension does not establish the deferred-operand property for every query form.

Deferred CAST/CONVERT operands, IF arguments and CASE branches retain their complete expression roles. An unselected result branch can still require preparation when its enclosing SELECT is reached. The same stored instruction is tested while skipped, reached and skipped again; a successful skipped call does not erase prior conditions on every release.

CREATE TRIGGER histories distinguish an absent database, an absent table, a populated target and a repeated definition. Legacy releases report a missing target table even when its database does not exist; modern releases report the missing database. MySQL 5.6 also rejects a second trigger for the same table, timing and event. These are observations of database state, not constructor rejection rules.

Explicit SHOW database identities must be nonempty; the empty database operand of SHOW OPEN TABLES remains a listing filter. View column lists have a release-dependent domain: legacy releases accept names that modern releases reject, including an empty name or trailing whitespace. Composition checks exclude combining that legacy column domain with modern query forms. Modern input lowering retains a typed column refusal, the view identity and its enclosing stored-program identity. The consumer can therefore resolve the default-database dependency without consulting SQL. CREATE/DROP VIEW also carry the function/trigger restriction imposed by their implicit transaction boundary.

`MultipleDelete` takes a `DeletionInput` that couples its input occurrences with
its target namespace. An explicit target list must be nonempty, contain no
identical repetitions, and name occurrences that the input can expose. Omitting
that list selects every visible input occurrence, retaining aliases and explicit
database qualification. Nested query tables do not become outer DELETE targets.
An omitted database and case comparison remain context-dependent; a target
absent under every supported comparison is rejected before an `Operation` exists.
Input lowering reports such SQL through `RefusedDeleteTargets`, which retains
typed relations, predicates, common tables and the enclosing program name for
diagnostic ordering. It is not a statement and retains no source SQL or AST.

This namespace constraint does not yet establish the entire deletion domain:
for example, the updatability of a derived-table target also needs its ordinary
statement and deferred stored-instruction roles distinguished. A matching native
refusal does not close that remaining construction issue.

The generated root is a MySQL statement or an ordinary expression placed in a SELECT item. The runner derives its semantic facts against a fresh in-memory session and sends the result directly to statement execution. Every parameter occurrence receives an explicit NULL value and its type before derivation, so the fixture supplies bindings without relying on unresolved parameter types. Separate properties exercise non-NULL bindings and their occurrence order. The generator has no list of supported SQL commands and does not use execution success or semantic diagnostics to select inputs.

Typed optimizer hints are read from the original structure independently of the
previous SQL input. Typing-related SET_VAR values are applied before domain and
parameter inference, then restored. A request with parameter occurrences uses
the same preparation checks as the protocol adapter before direct execution;
resource-group conditions follow a preparation error, while ordinary hint and
SET_VAR conditions precede name resolution. Database-selection histories and
populated table histories reuse the same hinted objects on both sides.

Direct submission reports the semantic warnings of a newly submitted statement, including deprecations, just as SQL submission does. Only execution of a previously created stored-program statement suppresses those initial warnings. A separate inventory check requires an explicit command route for every public MySQL statement variant. Internal parser entry points are rejected during input lowering, before a statement is published. Debug-only commands retain their dependency on the server build and produce its native client rejection.

Replication heartbeat notices are resolved against the global network timeout
supplied in the semantic session snapshot. Every written option retains its own
notice, even when a later option replaces its value or a later input operand is
rejected. The consumer records these conditions without recalculating them. A
native transition test submits the same directly constructed request after
changing the timeout above and below its period, then restores the timeout.

For MySQL 5.6 and 5.7, `UpgradeDatabaseName` consumes a `LegacyDatabaseName`, whose decoded component excludes the fixed `#mysql50#` marker. Its constructor checks the directory spelling and the character and byte limits; an ordinary database identifier cannot occupy this role. Stored-program constructors reject this instruction, including in an unreachable branch. Its command moves table declarations and data to the upgraded name, retains view and trigger references, and removes stored routines and events. SQL Semantics supplies the 5.7 deprecation fact. Differential histories against isolated servers check the resulting rows, metadata, warnings and errors, including invalid references after the move. A separate test executes the same directly constructed object before creation, against a populated legacy database, and after its successful move, with an unrelated request label.

Stored-program constructors exclude every table HANDLER operation, including
operations in unreachable branches. Condition handlers declared with DECLARE
remain a separate supported role. Input lowering retains the declaration header
and the version-specific stopping boundary: 5.6/5.7 refuse a HANDLER operation
keyword, while later versions first require the complete HANDLER statement.

Generated-column constructors reject DEFAULT, ON UPDATE, AUTO_INCREMENT,
SERIAL DEFAULT VALUE, COLUMN_FORMAT, STORAGE and the SERIAL type. These
combinations are rejected by MySQL even inside unreachable stored instructions.
SQL lowering and direct construction share the same attribute rule. Column
collations retain their lookup diagnostics before table resolution. For ordinary
columns, later DEFAULT and ON UPDATE clauses replace earlier clauses; an
incompatible earlier clock expression does not invalidate a compatible final
clause. Ordinary-column constructors now exclude clock defaults and updates on
non-DATETIME/TIMESTAMP types, mismatched fractional precision, SRID on nonspatial
types, and AUTO_INCREMENT/SERIAL DEFAULT VALUE on nonnumeric types. Floating
AUTO_INCREMENT remains available for legacy releases. Input refusals retain the
column name and typed table or enclosing-program database scope, preserving the
version-specific error boundary without publishing a contradictory specification.
These constraints do not establish every column-definition invariant.

Engine attributes on columns, indexes, tables and tablespaces share a constructor invariant: quoted text must be empty (reset) or a complete JSON document, with finite numbers and no more than 100 nested containers. An invalid document is rejected even inside an unreachable stored-program instruction. SQL input lowering applies the same rule and retains a typed operand diagnostic before publishing a statement. Memory formats that diagnostic without parsing JSON again. The same directly constructed secondary attribute is tested before its table exists, against a populated table, and after the table is dropped.

Every failure record includes its seed index, concrete root class, exception, and constructor rejection counts. To replay one trial from the same revision, give its index as the final argument:

```sh
php fuzz/structures.php 1 example-seed - 8.4.7 22
```

The arguments are `COUNT [SEED] [CLASS|-|@each] [RELEASE] [START] [native|compare]`. Omitting the seed chooses a random seed and prints it before the first trial. An explicit class selects a root from the same discovered inventory. `@each` visits the sorted root inventory in order, wrapping after its end; the initial record reports the number of root types. Use at least that many trials to exercise construction of every root type. This enumerates model types and does not measure SQL Faker grammar coverage. Reproduction requires the same revision because changes to types and constructors change deterministic choices.

```sh
composer fuzz:structures -- 244 all-model-roots @each
```

The constructor search shares one 2,000-attempt cap across each root and its
children. When a constructor rejects an argument combination, the search retains
its completed child values and resamples one argument for the same concrete type
and depth. This lets constraints involving several fields be explored without
repeatedly rebuilding unrelated children. Constructor rejection is the only
reason to retry; an accepted root is submitted unchanged, and later profile or
execution failures remain failures.

The default `auto` release selects a compatible test environment before execution. To state that selection explicitly:

```sh
composer fuzz:structures -- 244 all-model-roots @each auto
```

This mode renders the already constructed model under candidate grammar releases and lexical modes, parses and lowers that rendering, and checks structural correspondence. It does not derive facts or execute statements while selecting a profile. A retained observable spelling fixes the candidate profile; otherwise the candidate modes cover the interpretations of `NOT` and `||`. Named parameter markers select the named-parameter extension. If no candidate preserves the structure, the trial fails in the `profile` phase and the failure records each candidate's reason. Inputs are never regenerated because of this result. Once selected, the profile is used for one execution; an execution error does not cause another profile to be tried. The memory executor receives the constructed structure directly, without parsing the rendered SQL. An explicit release keeps the previous fixed-environment behavior.

The acceptance criteria are stated in the [acceptance contract](acceptance.md).
A local run reports SQL errors as unresolved, separately from profile failures,
generation failures and internal exceptions. It cannot establish agreement with a
native database. Exit status 2 means incomplete generation; status 1 means an
unresolved trial; status 0 requires every local trial to return a completion or
rows.

Use `compare` to check **every** generated structure against an isolated
Testcontainers MySQL server, including structures that return successful results:

```sh
php fuzz/structures.php 244 all-model-roots @each auto 0 compare
```

This mode requires `mysqli`. It compares buffered rows, column names and origins, protocol
types, lengths, flags, character sets, decimals, affected rows, insert IDs, errors
and stored warnings. Memory receives the original constructed object with
unrelated invalid session text. Only the native side parses rendered SQL. A
process-list request also receives its observable request label as runtime context
on both sides; memory does not parse that label or retain it in semantic values.
Parameter occurrences become native prepared markers and receive explicit NULL
values; their fixture types follow the release's preparation rules. Quoted text
that resembles a marker remains unchanged. Both connections use UTF-8 and the
same connection collation and SQL mode.
Completed earlier rowsets survive a later failure; an incomplete rowset is not
published by a buffered client. Connection termination is compared explicitly.
After termination, neither side can expose its stored warnings through that
connection, so the observation records them as unavailable rather than empty.

The initial fixture also shares container configuration, the initial `test`
database, login password, client host and thread identity, installed help data,
routine installation dates and resource-group capabilities. Discovery uses a
separate administrative connection before the generated statement runs. It
never imports that statement's response or any routine implementation.
After discovery, both servers reset enabled binary logs before the generated
structure is observed. The administrative table-cache flush does not write to
the log. Generated operations retain their log effects; subsequent positions,
filenames, sizes and events are compared exactly. Resetting initial fixture
state does not implement logging for writes or permit normalizing their output.

The summary distinguishes `matchedSuccess`, `matchedError`, `different` and
`failed`. Exit status 0 requires a complete native match for every
requested trial, including every recorded transition. A SQL error is accepted only with that native evidence. For
example, `SIGNAL SQLSTATE '45000'` is a valid structure whose intended outcome is
an error; treating it as an implementation failure would make the property
impossible. A native success, different error, missing warning or metadata
difference still fails. No exception is accepted solely by error number or class.
Generation, profile and infrastructure failures cannot count as matches.
This exit status establishes native agreement for the sampled outcomes. It does
not establish the separate constructible-structure gate: matching rejections
still require an intentional-condition explanation, a state-transition witness,
or a structural repair. See the [acceptance contract](acceptance.md).

When both initial observations agree on error 1046 (no database selected), the
runner creates and selects `structure_fixture` on both sides, then submits the
same original object again. The `transitions` field retains all three complete
observations, including the two fixture commands. No generated node is replaced
or discarded. A difference at any step fails the trial; an unsuccessful fixture
is an infrastructure failure. The summary's `databaseTransitions` counts the
structures, observations and differences separately from the initial outcomes.
This removes one known state barrier across the entire root inventory. It does
not resolve table or account dependencies, or classify a later matching error as
structurally valid. Those later conditions remain visible for investigation.

Each trial has a fresh native server so partial effects cannot leak into another
trial. `MYSQL_MEMORY_STRUCTURE_NATIVE_PORT` optionally fixes its published port;
otherwise Testcontainers chooses it. Every comparison record includes the SQL,
profile and both original observations. Comparison copies may use explicit,
recorded exception contracts: legacy missing-library OS errno 2/11, and physical
engine reports validated by the same rules as SQL differential fuzzing. The
engine contract requires an empty memory report and a recognized native payload;
metadata, warnings, errors and connection termination remain exact. Tests compare
the same constructed engine requests against two independent native instances
for each supported release and preserve both original payloads. Other
instance-specific differences remain failures until their contracts are established. Separate state-transition properties use the same constructed object under
changed session modes, declarations or stored-program scopes. A single matching
rejection is not evidence that every part of its structure was reached.

The older `native` mode remains an error-only diagnostic audit. It compares error
number, SQLSTATE and message after local rejection, records `matchingError`,
`different` and `failed`, and does not change the local run's nonzero exit status.
It is useful for investigation but does not replace the complete `compare` gate.

The unrestricted campaign currently finds counterexamples. The `property` test suite contains bounded regression properties for repaired model domains, including positional and named parameter binding across nested scopes, decoded strings across SQL escape modes, and system-variable assignments followed by reads in the same session. `SystemTarget` is an assignment operand; only `SystemVariable` is a general read expression, and its scope excludes `PERSIST` and `PERSIST_ONLY`. Query and composite-expression constructors also reject known combinations of features that cannot coexist in any supported grammar generation, including WITH combined with PROCEDURE ANALYSE anywhere in its operand graph.

Every MySQL statement constructor checks the composition of its nested structures before returning. An inventory-driven architecture test checks that this is the final action of each constructor and that no earlier return bypasses it. The same composition check runs in composite expressions. The check rejects contradictory uses of `HIGH_NOT_PRECEDENCE` and `PIPES_AS_CONCAT`, including across separate select items, assignments, and subqueries. It also rejects the known combinations of pre-8.0 features with features introduced in 8.0 or later. This is a structural consistency check, with no access to a memory database or execution results; its feature catalogue is not a complete language-availability specification. A consistent structure can still require a different release or SQL mode from the session used by a trial.

A node object represents one occurrence in a statement. Statement and composite-expression constructors reject repeated node identities, including sharing through different wrappers or subqueries, before any operation or execution is built. Equal values constructed as separate nodes remain valid, and names, enum cases and other immutable non-node values can be shared. A node may also be used in separate statements: the invariant is local to each statement graph. The direct occurrence property checks both constructor rejection of shared generated operands and execution of equal, separately constructed operands.

Contiguous exclamation marks have an observable meaning beyond their spacing. A `PairedNot` unary operator represents `!!` as one negation; two separate `Not` operators represent `! !`. The model preserves both values and deprecation counts without consulting input SQL during execution. A select-item layout cannot join independent negations or split a pair, since that would change its expression. The direct negation property composes these operators over numeric and NULL operands and checks values and warnings across five release profiles.

The bounded lexical-mode property constructs expressions and selects the matching session mode before execution, then checks their results over the 8.0, 8.4 and 9.1 releases. XOR has no symbolic spelling. A modern JSON table or ODBC escape cannot be used as a single parenthesized table factor; parentheses around an unaliased derived query belong to the query. KEY subpartitioning requires explicit columns even though top-level KEY partitioning permits an empty list.

`EXPLAIN` accepts the `Explainable` statement role: queries, INSERT/REPLACE, UPDATE and DELETE. Condition numbers use the same literal/variable/name domain as condition information. PREPARE sources, replication credentials and log file names require quoted text, and CREATE/DROP INDEX reject duplicate ALGORITHM or LOCK clauses. A select item with an observable spelling retains its `spellingProfile` (grammar release and lexical interpretation). Construction checks that each spelling slot is one complete token, that the layout has no leading or trailing trivia, and that its tokens have the same grammatical meanings as the rendered expression. A self-named item or a canonical spelling keeps no layout. This validation uses temporary parser results and retains no source SQL or AST. SQL lowering supplies the original language profile; direct construction defaults to MySQL 8.4 when a layout is supplied without an explicit profile. All observable spellings in a composed statement must declare a compatible profile, and their lexical interpretation must agree with operators elsewhere in the graph. A named parameter cannot be combined with a spelling that explicitly requires native parameter markers. Execution must use that profile. The direct layout property checks values and column names across five releases and different lexical modes.

Passing these bounded regressions does not establish the property for the whole semantic model. Known outstanding categories include release-dependent forms, literal-only positions, and structures that produce state-independent SQL errors. Quantified comparisons reject null-safe equality; native MySQL rejects `<=> ALL/ANY/SOME` as syntax. A set-chain operand rejects unparenthesized ORDER BY, INTO and PROCEDURE ANALYSE. The MySQL 5.6 left-operand LIMIT form remains constructible, but cannot coexist with modern-only forms such as EXCEPT. SQL_CACHE cannot occur in the query blocks of an expression subquery; parentheses around a top-level legacy query remain allowed. Derived tables use a separate query grammar and retain invalid cache placement as a semantic diagnostic, including when nested inside an expression subquery.

Numeric-looking user-variable names stay names across an assignment and a later read. PHP coerces some string array keys to integers, so semantic session maps accept `array-key` and normalize keys back to strings before applying name rules. The direct property uses the same memory session for both constructed expressions, including boundary-sized decimal names and names with leading zeros.

Literal-only positions also constrain how their values are represented. Passwords, account comments, foreign-server strings, replication strings, spatial-reference attributes and tablespace encryption values require quoted text. LOAD character sets require an explicit name. UPDATE and DELETE ordering holds scalar expressions rather than output-column ordinals. A view cannot contain PROCEDURE ANALYSE, and ALTER VIEW cannot carry CREATE VIEW's IF NOT EXISTS flag. Replication synonyms require a renamed option in the current vocabulary; legacy option structures reject options introduced after 5.7. Modern GRANT AS and multiple locking clauses cannot coexist with legacy-only forms. These checks run in semantic constructors, before execution.

Spatial-reference diagnostics retain the offending decoded value for a line feed. Semantic derivation orders literal errors and repeated attributes by their occurrence, before identifier ranges and complete-definition checks. Memory consumes that ordered diagnostic stream and formats the retained value; it does not rediscover the offending attribute from SQL. Differential tests compare competing errors, warnings and multibyte truncation against 8.0, 8.4 and 9.1.

Authentication factors use the `Factor` enum: exactly the second and third factors. `FactorStep` and `AlterRegistration` cannot contain another number or an alternative spelling such as `02`. A factor change names each factor at most once; adding both requires the second before the third. SQL input with another operand is rejected during lowering, before any statement containing it is published. Completed factor and account operands retain their diagnostic order before later malformed input. LOAD counts, ignored-row counts and parallelism use the decimal `NUM` token domain (0 through 2147483647, with leading zeros preserved); their option-specific value checks remain separate. LOAD filenames and compression names require quoted text. REVOKE accounts cannot carry authentication clauses; GRANT retains its legacy authentication forms. SQL input rejected at these boundaries remains an SQL error, with native error positions and legacy LOAD database-selection precedence checked against five isolated server releases.

These failures remain visible in the campaign until the corresponding type or invariant is addressed. A SQL rejection cannot be counted as a successful trial merely because it has a server error number; state dependence must be demonstrated separately.

Duplicate common table names are semantic diagnostics with an aborting notice after the duplicate definition. This includes duplicates nested inside an unused CTE. Direct construction and SQL lowering derive the same notice; lowered definitions additionally carry integer source ranges so warnings retain input order. Execution consumes this notice rather than rediscovering duplicate CTE names. Identifier comparison keys preserve case when the context uses case-sensitive comparison, consistent with name equality.

Source ranges are computed once per parse node in a transient weak-key cache. Only integer offsets enter the published source map. Deeply nested input therefore does not repeatedly flatten token lists, and discarded syntax trees are not retained by the range recorder. Tests exercise deep trees, empty synthetic tokens and collection of discarded trees.

A written VARCHAR or VARBINARY declaration requires a length. Expression inference uses `StringFamily` when the length is not yet known, retaining its binary/national classification and any known character set. This descriptor implements `TypeDescriptor`, not `DeclaredName` or `Node`, so it cannot occupy a column, routine parameter, variable or return declaration. CAST without a length and character-set conversion retain valid expression facts. The direct cast property constructs these expressions across the five supported release profiles.

DELETE targets reject the legacy equals-sign alias available to some read relations. SHOW PROFILE query identifiers use the decimal NUM token domain, and SHOW CREATE EVENT accepts at most a database qualifier. Stored-program bodies exclude BINLOG client statements; expression subqueries exclude PROCEDURE ANALYSE. Composition checks include functional indexes, dynamic privileges, external routine bodies, random passwords and routine IF NOT EXISTS as modern features, and repeated TEMPORARY and routine COLLATE DEFAULT as legacy features. A single node requiring both generations is rejected as well as conflicts between separate descendants.

UPDATE normalizes unparenthesized comma lists into its ordered table list. LOAD normalizes the canonical COUNT spelling across keyword and identifier grammar forms. A routine inspection name has at most a database qualifier, a dollar-quote tag uses identifier characters without dollar signs, and a resource-group priority uses a decimal NUM token with its separate sign. These invariants preserve values and names through rendering and reconstruction.

Index options are checked against their index kind in both CREATE INDEX and table definitions: WITH PARSER belongs to FULLTEXT, while USING is unavailable to FULLTEXT and SPATIAL. A standalone PROCEDURE ANALYSE query requires a FROM clause; an INSERT source can retain the no-FROM form and receive its SQL rejection during execution. Repeated RESET targets use a consistent vocabulary for each target kind, while different target kinds retain their separate rename dates. GRANT and REVOKE role and account lists expose their existing nonempty invariant in their public collection types, so type-driven construction does not repeatedly sample an empty list.

A statement rejected at a later token can already have produced semantic conditions. Completed-prefix lowering returns typed `Deprecated` and `ParseFailure` values with integer offsets. Name decoding uses the input language profile, including backslash handling. Unknown collations in expressions continue parsing; unknown character sets and definition collations abort it. Cast-target rules are shared with ordinary semantic derivation. Their precision and array errors abort parsing in 8.4 and 9.1, but continue in 8.0. Memory records the ordered conditions and returns the first error without recovering names or target types from SQL.

MySQL query bodies implement `Query`, while `QueryRoot` implements the standalone `Statement` and `Explainable` roles. Read an analyzed SELECT through `$operation->statement->query`; construct a standalone query with `new QueryRoot($query)`. Subqueries, INSERT sources and view definitions retain query bodies directly. This distinction puts position-specific invariants in the model: MySQL 5.7 admits a no-FROM filter in a subquery or INSERT source even though the same standalone SELECT needs a modern profile. MySQL 5.6 does not admit that subquery form. Profile selection must therefore precede execution of directly generated structures.

A cursor declaration also holds a `QueryRoot`, because CURSOR FOR takes the statement grammar position. The query-role property constructs filtered scalar subqueries directly across the profiles that admit them, including an outer SQL_CACHE query on 5.7. The statement inventory requires that no query-body variant implements the standalone statement role and that each statement has an explicit command route. View rebinding resolves its retained query in a view-definition structure, preserving that query's grammatical position.

Replication source log positions accept decimal number spellings; relay log positions retain the hexadecimal form permitted by their grammar. JSON_TABLE column collations must name a collation, and CLONE directory operands must be quoted text. Composition checks also separate legacy password hashes, authenticated GRANT lists and repeated WITH options from modern authentication factors, random passwords and dynamic privileges. These checks reject contradictory models during construction, without consulting database state.

MySQL 5.6 password assignments can appear in a SET list alongside variable assignments. Memory validates the password operand before applying the list and consumes its typed account and password fields directly. The direct property checks mixed password and user-variable assignments without SQL submission. Native histories additionally check invalid hashes and the values retained after rejection.

Completed replication keyword reductions retain their deprecation notices before a later malformed operand. Legacy command-prefix resolution also preserves the missing-database error of CREATE TABLE and the refusal position of a comma after a standalone SET PASSWORD. These input diagnostics do not add SQL or parser nodes to the published structure.

The direct constructor campaign exposed a missing resolved type for `ST_COLLECT(NULL)`. Spatial aggregate facts now retain the GEOMETRY field, binary collation, width and precision, including for NULL input. `ST_COLLECT` permits DISTINCT together with OVER. Memory collects the independently evaluated geometry inputs, ignores NULLs, retains duplicate or distinct inputs as requested, chooses the corresponding multi-type for homogeneous points, lines or polygons, and retains mixed types as collection members. Its error handling checks malformed geometry and unknown or differing spatial reference systems. Direct properties exercise generated coordinates and multiplicities; native comparisons also check grouped and windowed results, metadata and errors on MySQL 8.0, 8.4 and 9.1.

Quoted operands of replication commands, plugin installation, SHOW patterns and undo tablespaces reject hexadecimal and bit spellings when the grammar requires a quoted string. JSON DEFAULT responses exclude the NULL literal, which has its own response variant. ODBC escapes exclude an unparenthesized comma list or another escape. Stored-program members and SHOW PARSE_TREE exclude transaction BEGIN, whose grammar entry differs from START TRANSACTION and a compound block.

Generation constraints include GROUP BY directions, UPGRADE PARTITIONING and REDOFILE as legacy forms, and ST_COLLECT, modern optimizer hints, constraint enforcement and SHOW PARSE_TREE as modern forms. These constraints describe incompatible language features, without inspecting execution state. Failed constructor attempts remain visible in the generator's rejection counts.

GRANT retains `Grantee` operands instead of the general `UserSpecification`. A grantee holds only an `Account` and optional legacy `Identification`; it cannot contain ALTER USER password management, additional authentication factors or USER(). Existing constructor input using `UserSpecification` is validated and converted. The direct property constructs an account and grant without SQL input and checks the retained privilege on five releases. Role lists also expose their permitted operand union, excluding ALL from their retained type. The generator therefore uses the actual operand domains rather than spending its construction budget on impossible shallow alternatives.

A rejected RESET binary log index is carried by `RefusedSetting::number`. Its invariant requires a numeral exactly for the out-of-range index diagnostic. Both full-statement derivation and completed-prefix lowering create this diagnostic; memory formats the retained value without recovering it from the enclosing statement or SQL. Prefix lowering also retains START SLAVE deprecation at the accepted keyword, including when comments separate it from START.

The native rejection audit also receives the full runtime SQL mode. Lexical profile flags alone do not describe options such as NO_ZERO_DATE or ONLY_FULL_GROUP_BY. An explicitly selected release retains the default memory session mode on both sides; auto-selected profiles retain their explicitly chosen mode. An isolated native regression checks a zero date under NO_ZERO_DATE.

Direct REVOKE properties generate account lists with repeated names and execute the constructed statements without SQL input. Repeated occurrences remain distinct nodes: the second occurrence can fail after the first removes its grant. MySQL 5.x retains successful changes when another occurrence fails; 8.x and later restore the account state. Errors and warnings remain in account order, and the first error is returned even when `max_error_count` limits stored conditions. Native histories compare these diagnostics and subsequent SHOW GRANTS results on all five supported MySQL grammar releases.

The native histories also check connection history that is separate from committed account grants. On 8.4.7 and 9.1.0, an empty database-level REVOKE can reuse a preceding database privilege value for an account without a grant there. A refused database grant operation can leave TRIGGER as that value; a new connection and FLUSH TABLES clear it. Memory retains this observed connection state independently of rollback. These transitions were established through public SQL observations, without consulting the server implementation. They require no SQL or parser nodes in the semantic structure.

Completed INSERT/REPLACE DELAYED modifiers retain their deprecation notices even if a later operand is refused. Prefix lowering interprets the completed grammar reduction and publishes a typed notice; execution does not scan the SQL for the modifier. Native histories check these warnings across five releases.

User-variable assignment checks character length separately from diagnostic byte length. MySQL 5.6 permits empty and long assignment names; later releases refuse empty names and names above 64 characters while still allowing reads of absent variables with those names. GET DIAGNOSTICS records an assignment error, keeps earlier assignments and stops the remaining assignments while completing the statement. Direct properties construct variable assignments, diagnostic reads and subsequent variable reads without SQL input, including multibyte names at the character boundary. Native histories compare those transitions, errors and result metadata across all five releases.

Stored-program creation separates executable nodes from display text. A directly constructed procedure, function, trigger or event renders its metadata from its typed operands; it does not parse the session's last SQL string. Lowered definitions can retain their original metadata spelling outside the semantic graph. Direct properties create and invoke procedures and parameterized functions, activate triggers through inserts, and alter events with unrelated invalid text in the session. Their resolution context includes the current database, temporary tables and refreshed view declarations.

CLONE INSTANCE passwords and directory names require quoted text, as their grammar positions do not admit hexadecimal or bit strings. The constructor checks this representation independently of runtime plugin availability or filesystem state. A replay of the direct generator checks that the resulting statement retains its structure under its selected language profile.

Client packet splitting distinguishes whitespace after a terminator from a separate comment-only statement. Empty statements have their own completion and diagnostic behavior. Stored routine display text retains its terminating semicolon when another packet statement follows. Native histories check these packet and metadata observations on five releases.

CREATE USER checks account occurrences in written order. Modern IF NOT EXISTS skips replacement credential validation for an account that already exists and retains its note among the other account diagnostics. Invalid occurrences do not reserve an account name. When another occurrence fails, modern releases restore account state while legacy releases retain successful creations. Native histories check competing plugin errors, repeated names and later account state; the first recorded error remains the statement error.

View preparation opens referenced tables before checking the target database and view name, then resolves columns and stored functions. It uses the original operation's typed references and diagnostics throughout; it does not extract and parse the view's query text. Direct properties exercise missing relations, columns and routines with unrelated session text, and create and read generated literal views.

View declarations retain the column types exposed by materialization, including narrowed integer fields and the release-specific empty binary column for a NULL expression. Merged views retain their expression types. Rebinding a view after its source table changes uses the newly derived declaration types, preserving the same distinction. Native histories compare values, PDO metadata, SHOW COLUMNS and rebinding on five releases.

LOAD row tags accept both quoted text and radix literals. Their operand domain differs from compression names and other positions that require quoted strings. A supplied SQL Faker seed checks that lowering a hexadecimal row tag produces its typed value and reaches the normal missing-table diagnostic.

Debug-only SHOW PARSE_TREE requests still inspect account names before their release-build rejection. Both direct construction and SQL submission check the retained account operands without executing the wrapped statement. The SQL path defers its final debug-only refusal until the semantic operands are available.

Ordered error notices also retain their `NoticePhase`. Reading CAST ... AT LOCAL raises a syntax-stage condition; rejecting a partition expression or missing partition definitions requires contextualization. SHOW PARSE_TREE stops before that latter stage in release builds. Both SQL submission and direct execution consume the retained phase to preserve earlier conditions and the final debug refusal. Unknown conversion character sets and column collations retain aborting notices before that boundary. This distinction records when a diagnostic arises and adds no execution eligibility test to Semantics.

Legacy view destination checks use the query's `IntoPosition` and typed operands to distinguish variables before INTO from its targets and later predicates. The same select-modifier rule supplies their preceding conditions. Execution no longer parses session text to locate that boundary. Direct properties construct these views with unrelated session text; native comparisons retain the earlier modifier conflicts and warnings, including an INTO followed by malformed input.

Unknown EXPLAIN formats retain their decoded name in `UnknownExplainFormat`, including when only a prefix of the input can be lowered. The completed FORMAT reduction produces an aborting notice before later operand errors or warnings. Database character-set and collation options also produce ordered semantic diagnostics before execution, including release-specific collation availability. Memory bounds only the displayed diagnostic; the semantic value retains the complete name.

Composition checks distinguish MySQL 5.6-only ALTER IGNORE from validation modifiers, generated columns and JSON types introduced in 5.7. Index visibility requires a modern grammar and cannot accompany the removed UPGRADE PARTITIONING action. These rules reject incompatible structures at construction, before the property runner chooses a profile or executes them.

Condition numbers and SIGNAL/RESIGNAL information values admit variable reads, not assignments. Their public operand unions exclude `VariableAssignment`. Although the legacy grammar reaches its assignment production in these positions, native 5.6 and 5.7 reject that operand after reading it. Lowering preserves this refusal and its location before publishing a structure. A direct generator therefore cannot construct the rejected operand. The CREATE TABLE `START TRANSACTION` option also requires a modern grammar and cannot accompany SQL_CACHE.

CAST ... AT LOCAL has no public scalar type because every supported server rejects it. Lowering returns a typed input refusal rather than publishing an intrinsically invalid expression. Its continuation policy preserves later grammar conditions at the input boundary. On 8.4 and 9.1, BINARY around that refused operand stops after its own warning; grouping preserves this boundary, while enclosing arithmetic does not. A stored-program statement stops at completion, without an extra diagnostic, and preceding declarations retain their lexical-scope errors. Native histories check these rejected SQL inputs. Direct properties instead construct valid casts, grouping and arithmetic with unrelated session text, then check their values and warnings. Completed-prefix lowering also retains duplicate CTE names at the end of the repeated definition, including nested unused definitions, before a later syntax refusal.

Unknown system-variable diagnostics retain the complete decoded name. Memory limits only the displayed name to 64 bytes, stops at NUL and preserves the release-specific handling of partial characters. Native histories compare reads, condition numbers and assignments on five releases.

Character-type attributes use one warning rule for ordinary derivation and completed-prefix lowering. BINARY, ASCII and UNICODE warnings retain attribute order before a later refused operand. Unknown table-option character sets and collations similarly become ordered, aborting semantic notices before the table's query or partitioning. Legacy execution separately checks whether an unqualified table target has a selected database. Direct properties vary option order, database selection and qualification with unrelated session text; native histories compare these transitions across five releases.

Legacy subqueries can repeat LIMIT, but an already ordered block can carry a later clause only when it has an ordinary FROM relation. No-FROM and DUAL blocks put their ORDER BY in the outer clause position and cannot add another clause after it. Constructors reject these impossible combinations while retaining the valid repeated-limit forms. SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS positions use decimal number syntax, separately from hexadecimal positions accepted in other replication commands. Direct generator replays check these repaired constructor domains.

RENAME USER resolves each retained `CurrentUser` occurrence when that rename is reached. On modern releases, renaming the executing account updates that connection's current identity immediately, including when a later pair fails and the account changes are rolled back. Other connections and USER() retain their login identity. Legacy releases retain successful account renames on failure and keep the connection identity unchanged. Memory holds the current user and host as an `Identity` value, separately from the login name. Direct properties generate rename chains, failures and subsequent identity reads without SQL input; native histories compare account state and independent connections across five releases.

Database READ ONLY operands retain their exact numeric spelling, but their constructor requires the value converted by that option to be 0 or 1 (or DEFAULT). This preserves accepted inputs such as `0.9` and `1e0` while refusing `2`, including when nested in an ALTER DATABASE structure. Lowering reports refused SQL at the operand before constructing the option, and completed database prefixes retain preceding charset conditions. A type-driven property constructs the options without SQL or execution-based filtering and executes them against an existing database. This property checks the operand domain; enforcement of a database's read-only state is still outstanding.

Syntax error excerpts are converted from the client character set to the system character set before their 80-byte bound. Legacy releases retain complete characters; modern releases can retain incomplete bytes. Error packets and stored conditions use different conversions for those bytes. Native histories compare both observations across five releases and UTF-8, latin1 and binary clients, including preceding warnings. This encoding is confined to the input and protocol boundaries and adds no SQL or parser data to the semantic structures.

Public collection types expose the nonempty invariants already enforced by their constructors, including replication options, account lists and INSERT rows. Type-driven construction therefore does not spend attempts producing empty operands that cannot be retained. Consumer code can rely on these declarations when reading a first member. EXPLAIN ANALYZE, INTO and FOR DATABASE also participate in generation compatibility checks, preventing a modern EXPLAIN option from enclosing a legacy-only operand. Direct generator replays retain both failures as regressions; SQL errors from the resulting valid structures remain separately reported rather than counted as successful execution.

Database option diagnostics retain READ ONLY conflicts, resolved character-set conflicts and invalid encryption operands. The option list determines their order before execution opens the database. Completed-prefix lowering applies the same rules before later malformed input. Direct properties vary repeated numeric spellings and competing invalid options with unrelated session text; native histories compare full and partial statements on MySQL 8.0, 8.4 and 9.1. These ordered rejection checks establish semantic information and consumer behavior; they do not turn state-independent rejections into successful trials of the whole-model construction property.

Database collation names retain release-specific lookup and display behavior. Charset conflict diagnostics carry resolved charset identities, with legacy display names selected only when formatting the error. UTF8MB3 and UCS2 collation deprecations precede a later option conflict or syntax refusal. Native comparisons distinguish MySQL 9.1's lowercase `utf8_` alias prefix from the case-insensitive canonical names and the aliases accepted by 9.0.1 and earlier releases.

SQLSTATE values now enforce their lexical domain during construction: five uppercase letters or digits, excluding success class `00`. SQL input with an invalid operand produces a typed `Construction\RejectedInput` during lowering; memory maps that diagnostic at its input boundary rather than executing an invalid semantic structure. The primitive generator includes both accepted and rejected five-character strings for every string position, without consulting execution. A bounded direct property generates warning, not-found and exception states, constructs handlers and SIGNAL statements, then checks that the handler runs and the procedure continues. Neither operation construction nor execution reparses generated SQL. Other state-independent model defects remain unresolved.

When a later SQLSTATE operand is refused, completed program declarations and statements retain their lexical scopes for earlier condition-name and declaration diagnostics. This prefix uses the ordinary semantic rules and remains inside lowering; it is not a second execution model. Memory separately checks the connection database at the completed routine name. Native histories compare this order, preceding warnings, embedded NUL and multibyte condition values on five MySQL releases. The complete rejected operand remains in the typed diagnostic; release-specific truncation and character-set conversion affect only its displayed message.

Condition error codes enforce a nonzero integer prefix at construction, before their 32-bit conversion. SQL lowering rejects zero prefixes at the operand boundary and preserves earlier diagnostics, including when the subsequent input has invalid syntax. Handler matching consumes the semantic `ErrorCode::number()` value; it does not reinterpret numeric spellings using PHP casts. A direct construction property generates integer, hexadecimal, fractional and exponent spellings, declares named conditions, and verifies that SIGNAL reaches the matching handler and execution continues. Native histories compare these forms and the diagnostics retained when a routine declares conditions.

Resource-group commands accept a shared `ResourceGroupName` operand. Its constructor rejects NUL and enforces the 64-byte limit after supplementary Unicode characters become question marks. It retains the decoded spelling separately from the converted identity; memory consumes the identity directly. Empty names and surrounding whitespace are preserved. Direct properties generate independent names for create, alter, assign and drop histories. SQL lowering reports overlong names as a typed input refusal, preserving preceding character-conversion warnings, rather than publishing an invalid command. The parser rejects NUL inside quoted identifiers, while string literals retain it. These checks constrain the name domain; CPU ranges and other whole-model invariants still require further work.

Result-column names derived from string values stop at the first NUL after leading controls are removed; the literal value itself remains unchanged. Explicit string aliases containing NUL keep their decoded operand and render as string aliases, while their resolved output name stops at NUL. Leading nongraphic alias characters are removed with a typed warning. Generated direct queries verify that an outer query can bind these names through a derived table, without parsing SQL.

Resource-group priorities expose their signed numeric value, including leading-zero spellings. Their accepted range depends on platform support: without thread-priority support, native MySQL ignores even out-of-range values with warning 3659 and stores zero. Therefore the semantic structure retains the requested value and kind without deciding that runtime capability. Memory consumes these operands and its explicit `resourcePriorities` setting. Direct properties exercise 180 generated histories with priorities enabled and another 180 with priorities disabled, including otherwise invalid values. The differential fixture probes the native capability once before generating SQL and configures memory accordingly; it still compares errors, warnings and rows exactly.

Routine `ParameterList` construction rejects repeated case- and accent-insensitive parameter identities. The comparison weights are sampled through SQL and shared with memory's general_ci implementation. `Parameter` excludes NUL. Empty names, names longer than 64 characters and names ending in an ASCII space are legacy-only forms: composition rejects them together with modern-only features, and modern SQL lowering refuses them before publishing the list. A bounded direct property generates parameter-list histories and checks positional argument binding without source SQL. Supplementary-character conversion and other stored-program invariants remain outstanding.

Parameter and local-variable references use the same case- and accent-insensitive identity during semantic resolution and execution. The innermost declaration wins; trigger columns retain their separate column-name comparison. An unqualified select expression can resolve to either a column or a program variable, so it retains a validated noncanonical layout until resolution determines its result name. Program-variable results use the complete expression spelling, including identifier quotes, parentheses and unary plus. Ordinary column results use the decoded column name. Direct properties generate distinct parameter lists and differently spelled equivalent references, then check values and result names without submitting SQL to memory. This preserves required information in the semantic structure rather than recovering it in the planner.

`INTO` and `LIMIT` variable operands also resolve against their enclosing declarations during program definition. Their facts retain the declaration and type, so memory no longer suppresses an erroneous undeclared-parameter diagnostic. MySQL 5.6's observable leading comments use a validated layout gap; constructor checks reject a gap that adds expression tokens. Other releases keep their existing leading-trivia rule.

The direct factor regression varies account state while reusing the same constructed factor change, with unrelated session text. A missing account and an existing account without an additional factor produce their respective plugin/account errors. Native histories confirm these state-dependent outcomes and the refusal order for invalid factors across MySQL 8.0, 8.4 and 9.1. This bounded regression does not turn the unrestricted campaign's other SQL errors into successful trials.

A standalone `PARTITION` entry in the MySQL 5.6 and 5.7 grammars is rejected during input lowering. It is not a public `Statement` type. Partition clauses remain available inside table definitions and alterations; removing the client entry does not remove table partitioning. The input refusal carries a typed reason and keyword offset, without retaining SQL or parser nodes. An unfinished program is checked at the input boundary, preserving earlier declaration conflicts, system-variable lookups, temporal errors and warnings. Completed declarations are checked in their own block scope; references to outer scopes are left to complete-program analysis. Differential histories cover both valid partition clauses and rejected entries across five releases.

Database encryption uses the two-case `Encryption` enum. An optional decoded spelling must convert to the same setting, so arbitrary quoted values cannot enter `DatabaseEncryption`. Lowering retains invalid input only as a typed refusal and preserves earlier conditions. Conversion distinguishes operand validation from the resulting setting: `N` disables encryption, while a validated NUL-suffixed operand enables it, including `N\0`. The direct generator constructs 60 options across modern profiles, checks rendering correspondence, creates and alters databases without submitting SQL, and verifies the stored setting and SHOW result. Native histories additionally check metadata, session defaults, table inheritance, explicit overrides and temporary-table errors. These checks exercise semantic values and their consumers; they do not make the whole-model construction property pass.

Legacy INSERT lowering retains optimizer hints in the semantic head. A qualified wildcard in its column list requires a legacy profile, so construction rejects combinations with modern-only hints. Profile selection checks the rendered structure against the selected grammar before direct execution; the executor still receives the constructed objects.

`GrantRoles` and `RevokeRoles` expose nonempty lists of `GrantedRole`, rather than the general privilege union. Static privileges and column lists cannot inhabit those role operands. Lowering refuses such SQL before publishing a statement, retaining decoded diagnostic operands and integer source positions only. Recipient-name failures precede role-list failures; role errors retain list order and can be followed by later program warnings. Direct properties generate role operands from their public constructor types, create the required accounts, and verify REVOKE completion with unrelated session text. Anonymous role operands remain legal for REVOKE; native probes show that granting an anonymous account as a role fails even when it exists. This separate GRANT-domain defect remains open. These bounded histories do not classify the unrestricted campaign's unresolved SQL errors as successes.

`GrantPrivileges` and `RevokePrivileges` now require `Privilege` operands: static privileges, dynamic privileges or ALL. A role with a host cannot enter those lists. Input lowering reports its decoded diagnostic and source position, preserving preceding recipient failures and warnings. A completed unqualified privilege target retains its need for a default database at the input boundary; the connection supplies that state. Direct properties generate privilege operands from constructor types and execute REVOKE IF EXISTS with an existing account, using table scope for column privileges and global scope otherwise. Missing grants remain the command's documented warning behavior. These bounded histories do not establish the validity of every privilege/level combination in the unrestricted campaign.

CHECK subquery preparation uses semantic table references and expression diagnostics. A comparison's column-count diagnostic retains the comparison node that produced it, allowing the consumer to distinguish explicit row conflicts from widths derived from subqueries. Native histories check missing referenced tables, existing targets, foreign-key targets and invalid subquery projections before constraint rejection. The model still permits forbidden CHECK expressions; correcting their diagnostic order is not a constructor-domain guarantee.

`Reset` retains written targets for rendering and diagnostics and exposes their combined meaning through `effectiveTargets()`. The last explicit binary-log index survives a later omitted `TO`; the last replica target supplies `ALL` and the channel, including their absence. The consumer resets binary logs before the replica, preserving that state change when the replica fails. `ResetBinaryLogs` accepts only integral indexes from 1 to 2000000000, and `ResetReplica` excludes line feeds in channel names. Invalid SQL operands are refused before these structures are published. Line-feed diagnostics retain the decoded text operand, so their message does not require a statement tree. Direct constructor properties generate both target types and execute repeated targets with unrelated session text; legacy replica fixtures explicitly provide a nonzero server ID. Native histories check target combination, resulting log files and competing input failures on MySQL 8.0, 8.4 and 9.1. These bounded properties do not resolve the whole-model campaign's remaining structural defects.

The `SourceOptionKind` domain limits `SOURCE_CONNECTION_AUTO_FAILOVER`, `GTID_ONLY` and `REQUIRE_ROW_FORMAT` to their respective Boolean numeric interpretations. Constructor generation cannot publish other values. Rejected SQL retains a `RefusedSourceNumber` containing the kind, numeral and input position; syntax excerpts are formatted at the input boundary. `REQUIRE_ROW_FORMAT` continues reading after its value error, preserving later grammar conditions. Channel prerequisites remain execution state: GTID mode, auto-positioning and row format determine whether an accepted switch can be enabled. Successful changes retain these settings across statements and ordinary RESET REPLICA; ALL removes them. A bounded property samples the switch subdomain of generic constructor-generated `SourceOption` values and executes them with explicit channel prerequisites, without input SQL. Native histories compare both unconfigured and configured channels. GTID mode transitions themselves still have a separate catalog limitation: the current variable catalog treats its intermediate modes as Boolean values, so those transitions are not established by these channel tests.

`PARSE_GCOL_EXPR` is an internal MySQL 5.7 parser entry, not a public `Statement` variant. Completed input produces a typed `GeneratedColumnEntry` refusal containing its keyword offset only. Earlier operand and declaration conditions still precede that refusal, including when later text has a lexical error. Nested PREPARE diagnostics use the prepared input and restore the enclosing session text afterward. Native histories check comments, stored programs, prepared input and warning order. Generated columns inside CREATE TABLE retain their ordinary typed expressions and execution behavior.

Multiple-table DELETE flattens unparenthesized comma lists during construction, as UPDATE already does. Parenthesized groups remain nested relations. Direct properties vary grouping, statement form and fixture size across four releases and verify that each target row is deleted once. Replication position operands also participate in the shared language-generation invariant: legacy operands and current operands cannot coexist, even when a RELAY spelling is unchanged. Current MASTER synonyms remain distinct from legacy operands. These repairs remove two profile failures from the `entry-20261015` campaign; its SQL errors remain unresolved outcomes.

Resource-group CPU availability is explicit instance state through `resourceProcessors`. A direct property executes the same constructed CPU range against an eight-processor instance and a thirty-two-processor instance, checking the state-dependent error and successful group creation. The differential fixture reads the native default group's CPU domain before generated input and configures memory with the same count. It still compares invalid CPU identifiers and reversed ranges exactly. A saved unseeded range failure and native histories on 8.0, 8.4 and 9.1 exercise this configuration path.

`SET NAMES = expression` is an input refusal, not a public `SetItem` alternative. Its diagnostic retains only offsets and a local-variable shadowing fact. Direct properties exercise accepted character-set assignments and local variables named `names` with unrelated session text. Native histories preserve declaration scopes, earlier errors, warning order and the different rejection boundaries of 5.6 and later releases. SQL Parser also rejects invalid adjacent comparison punctuation at its first byte, so these lexical refusals never become semantic expressions.

A directly constructed `Help` statement consumes only its typed search name and the instance's initial help catalog. The direct property uses the general constructor generator for search operands, sets unrelated session SQL text, and executes the same structure against empty and populated catalogs. The result shape remains data-dependent, as the semantic model already records. Native tests compare keyword ambiguity, topic precedence, wildcard matching, category contents and result metadata using independently authored fixtures as well as installed help data. This bounded property does not change the unresolved status of the whole-model campaign.

`ExplainConnection` requires an integer-token operand and retains its resolved `connectionId`. Decimal tokens narrow to an unsigned 32-bit identity; hexadecimal tokens first saturate at the signed 64-bit limit. Memory consumes that identity for both lookup and diagnostics. Fractional and oversized decimal SQL operands become typed input refusals, including before later malformed text. A bounded property extracts constructor-generated positive numeric operands and executes the same direct statement before and after its target connection exists. This establishes the numeric-operand domain and its state dependence; ANALYZE/INTO combinations remain separate model defects; stored-program member checks exclude non-standalone connection explanations.

`AlgorithmOption` and `LockOption` constrain decoded names to their finite domains. Unknown SQL operands become typed input refusals before an option is published, including in stored programs. The same domain rule preserves the first refusal before later malformed input or deprecation warnings. Named DEFAULT requires a legacy profile, while INSTANT requires a modern profile; constructors reject composites that require both generations. Direct properties exercise 210 option/casing combinations against explicit table state without submitting generated SQL. Native histories check 202 observations across five releases. These properties cover standalone algorithm selection and lock selection when adding a secondary index; other action/algorithm/lock compatibility constraints remain unresolved.

Composition checks also exclude legacy database-directory upgrades from modern SHOW PARSE_TREE requests and ambiguous unmarked row quantities immediately after a leading INTERVAL. The latter requires an explicit grouping or ROW keyword. Type-driven campaign seeds reproduce both rejected candidates and verify that replacement constructions preserve a language profile. This is structural correspondence, not a claim that unrelated runtime SQL errors are valid property outcomes.

EXPLAIN format names are constrained to TRADITIONAL, JSON and TREE, with case-insensitive meaning. TREE requires a modern profile and cannot compose with legacy-only operands. Unknown SQL format names are typed input refusals before either EXPLAIN structure is published; completed-format notices preserve preceding program errors and later-input precedence. Direct properties vary accepted casing and scalar query values and use an explicitly existing idle connection. ANALYZE/INTO combinations remain a separate unresolved domain.

A stored-program member cannot be `ExplainConnection`: MySQL has no non-standalone `EXPLAIN FOR CONNECTION` form. The common statement-sequence check rejects it in blocks, branches and routine bodies before publishing their structures. SQL lowering reports the corresponding input refusal, preserving prior declarations and warnings and preceding later malformed input. Ordinary query explanations retain their stored-program role; format support that depends on the server release or session is not an execution-eligibility certificate in Semantics.

Calendar-invalid DATE and TIMESTAMP literals can depend on `ALLOW_INVALID_DATES`. Direct properties execute the same constructed literal under both modes: they require error 1525 without the mode and the retained date/time value with it. Native histories compare SELECT, NAME_CONST, procedure creation and invocation across five releases, including out-of-range components and fractional carry. Month and day bounds still apply under the permissive mode. Arbitrary temporal strings and offset-bearing timestamp literals remain separate unresolved model and execution domains.

DATE literal construction now requires a bounded representation accepted by at least one calendar mode. A decoded date cannot carry arbitrary text, a time part, a month above 12 or a day above 31. Zero components and calendar-invalid dates within those bounds remain representable because their acceptance depends on session modes. SQL lowering returns a typed `RefusedTemporalLiteral` for excluded operands. A rejected SIGNAL item also retains its preceding condition and completed items as semantic structures, allowing earlier binding errors to be resolved without retaining SQL or parser nodes. Input checks preserve program scope, grammar conditions and warning order before reporting the refusal. TIME uses the same typed refusal with its own representation domain; TIMESTAMP remains open work.

ODBC temporal escapes retain their string operand when it is outside the temporal domain. A known resolution context supplies `noZeroDate`, `noZeroInDate` and `allowInvalidDates`; the same pre-Operation escape can therefore resolve to a string or a temporal value, with the corresponding output name. Layout admissibility still describes the value's possible representations independently of one session. No SQL or parser node is retained to recover these facts.

The ODBC direct-construction properties include arbitrary non-temporal text and reuse of the same zero-component or calendar-invalid date under both calendar settings. A separate native MySQL 5.6.51 observation loses its connection on `SELECT NAME_CONST('value', { d '2024-00-01' })` after `SET sql_mode=''`; a new disposable server reproduced the loss. This remains an unresolved comparison, not a successful execution or an exception contract. The ordinary multi-statement ODBC test omits NAME_CONST value-operand observations on 5.6 so the lost connection cannot invalidate every later observation. The SQL Faker acceptance runners remain unchanged.

TIME literal construction excludes arbitrary text, out-of-range durations and compact operands with twelve or more digits. It retains clock, compact and day-and-clock forms, including signed zero and fractional seconds. Whitespace can separate days from a clock or follow a negative sign before a delimited clock. Direct properties execute constructed durations without source SQL under different calendar modes. The input boundary carries the temporal form with its decoded refused text, preserving diagnostic ordering. Deprecated whitespace warnings remain a separate unresolved comparison; the constructor does not remove otherwise accepted representations to avoid them.

Key prefix lengths are decimal integer tokens rather than arbitrary numerals; their spelling must fit the NUM token domain, independently of column-dependent prefix limits. The shared composition invariant also recognizes VECTOR, table engine attributes and automatic tablespace extension as modern features, and table `COLLATE DEFAULT` as a legacy feature. This excludes combinations for which no supported language profile exists while retaining each independently meaningful option. An expression subquery cannot wrap a query-expression INTO destination. Constructor examples and the type-driven `time-20261015` regressions exercise these boundaries. Its 1,225-trial campaign now has no generation, profile or internal failures, but 756 SQL-error trials still require structural repairs or demonstrated state dependence; those trials are not successes.

Unsupported aggregate-window diagnostics retain the semantic expression that owns the problem. Memory resolves names and windows within each query block before reporting its owned diagnostic; Plan also consumes the same association when compiling a call directly. Neither consumer infers ownership from SQL text or a parser tree. This preserves missing-column and missing-window precedence for GROUP_CONCAT and DISTINCT aggregates. Native routine histories also show why unconditional constructor rejection would be incorrect: creating a procedure with an unsupported window expression succeeds, and a branch that is not taken does not execute it. The complete comparison checks the resulting SQL refusal for scalar roots; local error-only trials remain unresolved without that comparison.

`WEIGHT_STRING` cast lengths admit positive integer tokens, including hexadecimal
spellings. Zero and fractional tokens are refused before a structure is
published. The semantic value exposes the resolved unsigned 32-bit cast length;
derivation and compilation consume that meaning instead of casting token text
independently. Positive tokens that narrow to zero remain valid. Direct
properties include leading zeros, hexadecimal digits, wraparound and the maximum
unsigned decimal token. Native histories check constructor refusals, warning
order and numeric spellings across five releases. The four-argument form also
exposes resolved result length, weight count and formatting flags. Its option
conversion accepts fractional and exponent spellings, reads their leading digits,
and narrows after overflow saturation; Plan does not interpret those token strings.

Packet-limit properties execute large non-NULL weights directly, including
32-bit boundaries and a short result buffer with a large weight count. Allocation
is bounded before padding. The same constructed values are compared against native
servers with packet limits of 4096 and 65536 bytes, preserving values, complete
metadata and warnings. This includes the legacy character-capacity narrowing and
the distinction between a binary cast's overflow and its subsequent weight
overflow. The result type carries the declared capacity and corresponding string
or blob field. These observations cover the sampled casts and options; they do not
establish every collation or close the whole-model construction gate.

The same directly constructed `DROP TABLE IF EXISTS` request is also checked
before and after selecting a database. Both native and memory return 1046 when
the unqualified name has no current database, then complete with a missing-table
note after `USE`. The structure remains unchanged; this is a state-transition
witness rather than a classification inferred from one error.

Column resolution preserves an absent table qualifier separately from a missing
column in every supported release. The compiler consumes that distinction from
the semantic resolution; it does not infer it from its own row offsets. A
non-lateral derived table also has this tableless scope even when its surrounding
query lists the named table. Direct constructions of column uses, `VALUES`,
`DEFAULT` and `PURGE` operands are checked against five native releases, including
MySQL 9.1's table-only diagnostic spelling for database-qualified references.

`PurgeLogsBefore` excludes queries anywhere in its moment expression, including
an unselected CASE arm. This is a composition constraint: native servers also
reject such operands while defining a procedure, even in a branch that would
never execute. Ordinary column references retain their deferred resolution.
SQL lowering produces a typed `RefusedLogSubquery` with a diagnostic position,
and input processing preserves the release-specific refusal boundary, earlier
conditions and warning order. No invalid statement is published for a later
AST check. Native histories exercise complete and unfinished expressions,
stored programs and prepared input across five releases; constructor tests
exercise scalar, EXISTS, IN and quantified queries through nested expressions.

Table identities exclude empty table names and explicitly empty database
qualifiers in their constructors. This is a role constraint: an absent database
qualifier remains session dependent, and empty correlation names, DELETE aliases,
CTE declarations and listing filters retain their own domains. Input lowering
reports a typed name-role refusal without retaining an invalid statement. Legacy
releases refuse the operand while parsing; modern releases finish grammar
interpretation first. Native histories include unfinished input, prepared
statements, untaken procedure branches, earlier temporal errors and valid empty
aliases.

Character-set conversion derives encoding-name failures and incompatible known
encodings from the retained `ConvertCharset` operands. The notices distinguish
name lookup during grammar interpretation from comparison during modern
contextualization; the latter belongs to the completed ALTER TABLE action list.
Memory consumes these facts before opening the table, while preserving the
legacy requirement for a selected database. Direct constructions are checked
against native outcomes before and after creating and populating the same target
table. Unknown-name agreement remains diagnostic evidence, not a certificate that
arbitrary encoding names satisfy the constructible-structure gate.

Replication channel operands share a semantic name domain. The effective name
ends at its first NUL; an LF within that name is refused by every public channel
constructor. An LF after NUL, an empty default-channel name and CR remain valid.
The retained text supplies rendering, while consumers use the semantic name for
lookup. Input lowering reports the same typed refusal before publishing an
invalid statement, including inside an untaken stored-program branch or a debug
wrapper. Native comparisons check complete results and preceding deprecation
warnings across five releases. Direct construction tests submit the original
objects with unrelated session text. These name-domain checks do not establish
that all configured replication-channel histories are implemented.

XA identifier refusals retain the native excerpt position as typed diagnostic
data. Length limits still apply to decoded bytes, and the format range depends
on the release. Fractional format spellings remain valid: their leading numeric
value identifies the transaction. Input comparisons include following syntax
errors and routine declarations. A direct state-transition test preserves the
same `XaCommit` object before and after preparing its transaction; both native
and memory change from the unknown-transaction error to successful completion.

Database-state properties reuse constructed view, cache and privilege requests
before and after creating their fixtures. An unqualified name requires a current
database before key-cache or privilege compatibility checks. A missing qualified
view reports a missing table on 5.6 and 5.7 and a missing database on later releases.
The successful transitions include view inspection, the default key cache and
routine grants. Separate comparisons retain IMPORT's later file-access failures
and invalid privilege-level errors; agreement with those errors does not establish
their construction validity or physical file support.

Result encoding retains the collation chosen by `SET NAMES`, independently of
later changes to `collation_connection`. Direct `character_set_results` assignment
resets that choice. Result construction uses the retained encoding for queries,
listings, maintenance, handlers and diagnostics, including individual result sets
within a routine. Native histories check metadata and values on five releases.

Routine parameters, function return declarations and local variables share a
type/collation invariant. A named `COLLATE` requires a character set carried by
the declaration itself. Explicit character-set attributes, national strings and
binary types can supply it; an ordinary character type without such an attribute
cannot. Known character sets and collations must agree. The legacy `COLLATE
DEFAULT` form remains an absent override. Unknown names still require lookup;
their diagnostic agreement alone does not certify a valid declaration.

Lowering checks this domain before publishing a declaration, including completed
parameters and return types before an unfinished body. Only a typed refusal and
its position escape the input stage. Accepted explicit collations participate in
the semantic types of parameter and local-variable references. Directly
constructed procedures, local declarations and function return types are created
and invoked with unrelated input text, comparing result values, metadata and
conditions against five native releases. Separate input histories check competing
syntax errors and warnings. These domain tests supplement the whole-model
construction campaign; they do not replace it.

Explicit deprecated collation names also supply semantic notices. Input processing
retains those notices before a later declaration refusal. Routine execution keeps
notices from loading the definition across statements in the body. Repeated calls,
routine alteration and separate sessions check the lifetime of that loaded state;
table flushes do not reload the definition. This state uses the retained typed
declaration rather than parsing the routine's display text.

Generic function calls expose their native-function classification through
`FunctionCall::native($release)`. A generic call whose name is a grammar keyword
is distinct from `KeywordCall`: it can bind to a stored function, and reports the
native name-collision diagnostic while that function is absent. Qualified calls
always resolve in the named database. Compilation, output metadata and expression
lifetime decisions use this semantic classification. They do not infer the role
from which built-in evaluators happen to be installed.

The function-resolution transition test retains the original call objects before
and after creating their stored definitions. It compares rows, column metadata
and notices across five MySQL releases, including an unqualified native `ABS`
call alongside a qualified stored `ABS` call. Wire histories also exercise repeated
loads, dictionary invalidation and stored functions evaluated for each outer row.

`Explain` and `ExplainConnection` require an explicit JSON format when they carry
an INTO variable. This relationship is a constructor invariant. SQL input that
violates it produces a typed `RefusedExplainInto` diagnostic instead of publishing
a contradictory statement. The diagnostic keeps the decoded format and the
release-specific ANALYZE refusal, with no SQL or parser node. MySQL 8.1 reads the
explained operand's notices before its INTO refusal; later releases reject the
completed options first. The input comparison checks both orders, including debug
wrappers, unfinished operands and untaken procedure branches.

The whole-model comparison fixture sets `timestamp = 1700000000` in both sessions
before observing a constructed value. Statement clocks therefore read identical
SQL-controlled state despite separate container startup and observation times.
This fixture does not normalize returned clock values or exempt `SYSDATE`, which
has a different contract from statement clocks.

HANDLER reads use the original semantic predicate and parameter bindings. The
session supplies the handler-to-table mapping through semantic settings, so column
resolution happens in SQL Semantics against the current declaration snapshot.
The consumer builds a typed projection for result metadata without rendering or
parsing SQL. Native transition tests reuse the same read before OPEN, after OPEN,
after CLOSE and after reopening, with populated rows, qualified aliases, index
seeks and missing predicate columns. Native metadata also retains key membership
and the release-specific original table name.

Condition lookup precedes runtime handler checks for directly constructed SIGNAL
and RESIGNAL statements, just as it does on the SQL-input path. A transition test
reuses one RESIGNAL object outside a declaration scope, inside a declared procedure
and inside an active handler. Native comparisons distinguish an undefined name,
an inactive handler and the explicitly signalled SQLSTATE. A handled SIGNAL also
invalidates stale diagnostics across the routine boundary without removing notices
raised for the current routine load.

Replication channel lookup converts supplementary Unicode characters to the
system character set and terminates names at NUL. The semantic conversion warning
retains the full decoded operand and the release-specific message. Completed input
also retains the warning before a later syntax refusal. Channel creation counts
characters rather than bytes, checks FILE repository restrictions before the
64-character limit, and preserves earlier heartbeat notices. Native comparisons
include ASCII, two-, three- and four-byte characters and both MySQL 5.7 repository
modes. The channel-size error is evaluated when the command runs: MySQL accepts a
procedure containing that command and raises the error when it is called. This
regression does not close the broader construction or named-channel history gates.

INFORMATION_SCHEMA.PROCESSLIST usage is lifetime SQL history. Its counter increases
for each materialized table occurrence; its last-use timestamp records the memory
server's own clock in microseconds. Neither SET timestamp nor FLUSH STATUS rewrites
that event history. System table definitions remain available while planning, but
their rows are materialized only when an iterator needs them. Constant false
predicates, impossible unsigned equalities, zero limits and constant false inner
joins therefore leave the history and deprecation notices untouched. Rewinding a
materialized occurrence does not count another access. Native histories also check
preparation versus repeated execution, self joins, UNION and failed resolution.

The complete STATUS comparison retains the exact change of that lifetime counter
from each instance's independent baseline. The event timestamp must remain unchanged
during the listing, and zero is permitted exactly when there has been no access.
Independent native instances and memory additionally validate each event timestamp
against the observing server's own SYSDATE(6) bounds. One instance starts with an
extra prior access to exercise unequal baselines. All four listing forms retain
metadata, warnings and every row. The release catalogs now include separate 8.1,
8.2, 8.3 and 9.0 inventories rather than borrowing a later release's names. The
combined native checks pass 12 tests and 1,953 assertions across nine releases.

`JSON_TABLE` requires a nonempty correlation name in its public constructor.
Missing or empty aliases in SQL are refused during lowering, including in an
uncalled routine body and before competing later errors. The ordinality column
retains a signed BIGINT value domain independently of its reported display width
of ten. The plan consumes the resolved relation slots for both ordinary and nested
JSON columns; it does not derive their types again. Direct native comparisons
include quoted aliases, subtraction from the counter and nullable nested rows.

Trigger listing histories reuse the same structures before database creation,
in an empty database and after creating a trigger. They compare all metadata,
warnings and rows, including constant-false predicates and legacy database lookup
ordering. Trigger creation uses the statement timestamp, including `SET timestamp`,
and stores the instant in UTC. SHOW TRIGGERS and INFORMATION_SCHEMA.TRIGGERS display
it in the reading session's time zone, retaining hundredths; MySQL 5.6 reports NULL
because that release does not store a creation timestamp. These histories exercise
consumer behavior after successful state changes rather than relying on matching
errors in an empty catalog.

TIMESTAMP constructors now reject operands without both date and clock fields,
out-of-range components, malformed displacements, and values that overflow
fractional rounding. Compact and relaxed representations retain their decoded
operand; no SQL or parser tree is stored. Calendar-invalid days and zero dates
remain available where session modes permit them. A displaced value with a zero
month or day cannot identify an instant and is rejected independently of modes.
The consumer applies release-specific displacement support and session time zones
using the semantic components. Literal precision comes from the semantic literal
type, including when an explicit displacement follows the fractional digits.
`TimestampDomainTest` keeps the same fifteen query structures across four mode and
time-zone states on five releases. `TimestampInputTest` also checks malformed
input against competing syntax and stored-program conditions. These focused
checks do not replace the whole-model acceptance gates.

Derived tables require a `Name` for their correlation name. Missing aliases cannot
be represented by a published `DerivedTable`; input lowering reports the typed
refusal instead. An empty quoted alias remains valid. Parenthesized legacy query
bodies receive the outer alias before becoming relations. The alias regression
compares the same eighteen constructed queries before table creation, after
inserting rows, and after updating them, across five releases. It checks rows
and every wire field, including the original table name retained by MySQL 5.6
when it materializes a derived query. Input tests separately cover competing
database and duplicate-name conditions, later malformed input, and uncalled
procedure bodies.

Anonymous-GTID source options accept `OFF`, `LOCAL`, or a string beginning with
36 characters in textual UUID form. Invalid strings cannot be published as a
`SourceOption`; input lowering retains a typed refusal containing only the
decoded operand. MySQL accepts trailing bytes after that UUID prefix, so the
constructor preserves them. The direct native regression reuses nine original
setting objects with GTIDs off, then on, then off again on MySQL 8.0, 8.4 and
9.1 (81 observations). It asserts successful execution in the enabled state,
the state-dependent refusals in the disabled states, and matching diagnostic
notes. The separate input regression compares UUID boundaries and ordered
setting refusals, including long and NUL-containing diagnostic values.

MySQL 5.6 storage requests resolve their explicit engine or the session's
`default_storage_engine` before looking up files and tablespaces. The same
57 original structures are compared in four combinations of default engine
and `NO_ENGINE_SUBSTITUTION` (228 native observations). Unknown engines either
fail immediately or warn before substitution; built-in engines then report
their unsupported operation. A separate input regression replays the 36
previously differing SQL Faker storage inputs with substitution both enabled
and disabled (74 wire observations, including setup). These checks establish
engine-selection behavior; they do not claim NDB Cluster support.


Foreign-server histories retain the same `CreateServer`, `AlterServer` and
`DropServer` objects across absent, present and removed states. The checks include
result counts, diagnostics and names containing multibyte characters or more than
64 characters. MySQL 5.6 retains earlier conditions until a new condition replaces
them and reports zero for an absent conditional drop. Later versions report one.
Memory preserves the original semantic name; its server registry distinguishes the
original lookup name from the 64-character stored name refreshed by `ALTER SERVER`.
Missing-server diagnostics have a separate 64-byte display limit. These state
transitions are verified by 300 direct observations across five native releases.

Generic function-call construction checks the versioned native signatures. An
unqualified call such as `ABS()` cannot be constructed when no supported MySQL
release admits its argument count. Qualified stored calls remain available, as
do names that have a stored-function interpretation in another supported release.
SQL input outside the current release's signature domain produces a typed input
refusal before a `FunctionCall` is published. Native comparisons check argument
counts, aliases, reserved names, nested calls, unfinished statements and preceding
or following warnings on five releases. This is an operand-domain invariant;
it does not certify execution or resolve the availability of a stored function.

Composition also intersects those signature domains with each other and with
retained grammar requirements. `X()` and `SQL_CACHE` cannot share a supported
interpretation, nor can zero-argument `X()` and `REGEXP_LIKE()` in the same query.
Their containing structure is rejected by its constructor. Compatible stored
calls retain their state dependence: native transition tests reuse the identical
call object before function creation, after creation and after removal, comparing
complete outcomes while memory's session text contains unrelated invalid input.

Replication connection settings survive a metadata write failure. Source ports
are read as unsigned 64-bit magnitudes, narrowed to 32 bits, and preserve the old
setting when the resulting port is zero. The TABLE repository rejects values
above its signed range. A failed ordinary reset then prevents subsequent changes
from opening the connection metadata; `RESET REPLICA ALL` clears that state.
Native histories compare the leading error, following conditions, competing
GTID/compression/relay errors and recovery on three modern releases.

Heartbeat options reject numeric literals above 4,294,967 seconds in their public
constructor. SQL lowering retains the typed refusal and the warnings raised before
it. Legacy unconfigured replicas preserve a valid heartbeat's timeout warning
before their connection error. Deprecation messages retain the server's singular
compression-option spelling without changing the plural SQL keyword. Input
comparisons exercise 80 statements across eight releases.

Legacy password statements share the account-name limits of MySQL 5.6. A password
assignment inside a SET list and the per-account PASSWORD EXPIRE form cannot be
combined with an account name requiring a newer release. A standalone password
assignment uses `SetPassword`; `SetVariables` represents a password only alongside
other assignments. The constructor regressions replay the two previously failing
whole-model generation seeds. Native histories reuse each original request before
account creation, after creation and after removal; memory receives no SQL input
for those requests. These repairs do not certify all generated SQL errors as
state-dependent.

Replication vocabulary notices now belong to semantic derivation. Statements,
source options and each RESET target retain their own occurrence warning, including
legacy options under a current-vocabulary header. A later UNTIL condition error
therefore keeps the preceding notices. Execution no longer interprets those
spellings to reconstruct warnings. Native checks compare 64 input histories across
MySQL 8.0 through 8.3, including completed statements and input refusals.

EXTRACT resolves its version-specific field width in SQL Semantics. Calendar
units convert a typed TIME relative to the statement date; clock and composite
day-clock units accept numeric and textual clock values, retaining date components
only when the value represents a datetime. Week extraction uses Sunday-based
week numbering. Direct comparisons exercise all 20 units with 13 operand forms
on five releases, comparing 1,300 complete observations without submitting the
rendered SQL to memory. Result-column widths remain available in the semantic
facts rather than being inferred again by the consumer.

Prepared arithmetic markers now derive their initial domains through SQL
Semantics' `ParameterInference`, shared by native-protocol preparation and the
direct-construction comparison fixture. Integer division retains versioned widths,
including a divisor's decimal scale and legacy zero-width NULL results. NULL
markers keep their coercibility separately from NULL literals. Native comparisons
exercise 136 structures on five releases through both direct execution and the
MySQL protocol (680 reference observations per path). The previously failing
whole-model arithmetic input at index 77 now matches without an exception.

LOAD annotations have a versioned interpretation: MySQL 8.0 and 8.1 follow
ordinary file loading, while 8.2 onward validate the bulk algorithm. MySQL 8.2
and 8.3 also append target and file conditions after the unsupported-algorithm
error. Native histories reuse nine original structures before table creation,
after creation, after primary-key addition and after removal on six releases
(216 observations). These checks explain the observed version difference; they
do not certify every LOAD option combination as intrinsically valid.

The completed whole-model audit at `dc14db871fb` retained all 245 public roots:
93 matching successes, 136 matching SQL errors and 16 differences, with no
exhaustion, generation, profile or infrastructure failures. The matching errors
still need the explanations and witnesses required by the acceptance contract.
After the NULL and LOAD repairs at `2e58a4016d5`, replay of the original 16 failing
MySQL 8.0 supplied seeds gives 11 matches and five remaining differences. These
focused replays do not replace the final whole-model and differential campaigns.


Histogram removal preserves its release-specific catalog behavior. MySQL 8.0.44
reports absent statistics for missing tables and views; 8.1 and later require a
base table. The consumer uses the typed histogram request and dictionary state.
A direct native comparison reuses two constructed requests across eight states:
missing table, present table, populated histograms, removed histograms, dropped
table, view, dropped view and temporary table. All 96 observations matched on
8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7 and 9.1.0. The package checks passed, including
14,989 unit/property/doctests and 48,470 assertions. This establishes those
histogram histories, not the outstanding whole-model construction property.


Immediate `DO` statements now expose a nonempty list of `SelectExpression`.
A wildcard cannot inhabit that operand domain. SQL lowering reports a typed
`EvaluationWildcard` refusal and retains only the qualified name and the
requirement for a default database. Input-boundary tests preserve competing
stored-function lookup conditions and subsequent deprecation notices.

Stored programs have a distinct `DeferredEvaluation` member because native
MySQL accepts `CREATE PROCEDURE p() DO *` and raises the condition only when
that instruction is reached. This member implements `ProgramStatement`, never
the top-level `Statement` interface. Its retained schema contains only its typed
items. Execution routes the deferred condition through the program's handlers.
A constructor-driven property samples forty such members in each of four
releases and checks successful definition followed by the declared invocation
condition with unrelated session text: 160 trials and 800 assertions passed.
The input differential history passed 43 observations on each of MySQL 5.7.44,
8.0.44, 8.4.7 and 9.1.0, including CONTINUE HANDLER processing. The previously
failing whole-model DO root at index 46 now constructs an ordinary expression
list and completes identically on both engines. No root was removed from the
245-type inventory. This repair does not resolve the other intrinsic-expression
defects or the remaining whole-model differences.

A zoned timestamp cast now enforces its zero-to-six fractional precision in the
public `AtTimeZone` constructor, before derivation or `Operation` construction.
SQL lowering reports an excessive precision as a typed `RejectedInput` with the
operand's end position; the consumer no longer scans accepted zoned casts to
rediscover this invariant. MySQL 8.0 and 8.1 continue collecting later conditions,
while 8.2 onward stop at the precision refusal. Input tests compare 44 observations
on each of six releases, including stored definitions, leading and trailing
warnings, further cast errors and unprepared markers. All 264 observations match.
This closes the precision domain; it does not certify every zoned operand or the
remaining whole-model construction property.

Ordinary `Cast` construction also enforces the temporal and floating-point
precision limits. A caller cannot construct TIME/DATETIME precision above six
or FLOAT precision above 53 and defer that intrinsic defect to execution.
Input lowering returns a typed precision refusal with its completion boundary;
Memory no longer walks accepted cast structures to rediscover these errors.
MySQL 5.7, 8.0 and 8.1 keep subsequent grammar conditions after the refusal,
whereas 5.6 and 8.2 onward stop. The input matrix compares CAST and CONVERT,
large overflowing precision spellings, stored definitions, unprepared markers
and competing SELECT modifiers across eight releases (2,140 observations).
In 5.7, a later query-cache conflict stops reading but does not replace an
already recorded precision error; deferred ALL/DISTINCT checks do not displace
a prior syntax refusal. These checks close this precision domain only; array
cast placement and other expression-role constraints remain separate obligations.

Current-database selection is an execution-state input, not a constructor
invariant. Unqualified targets of a multiple-table DELETE, CREATE EVENT, and
SHOW FUNCTION/PROCEDURE CODE require a selected database before later body or
query diagnostics. Qualified targets keep their own database. A direct native
property preserves five original structures across an unselected session,
`USE`, and deletion of the selected database. It compares all outcomes and
conditions on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1 (75 observations). A separate
SQL-input matrix checks warning and error precedence in 165 observations.
These witnesses establish the tested database-state transitions; they do not
classify unrelated matching errors in the whole-model campaign.

Derived relations now exclude HIGH_PRIORITY, SQL_BUFFER_RESULT,
SQL_CALC_FOUND_ROWS and SQL_CACHE from the query blocks of their query-expression
spine. The constraint includes later set operands and applies inside stored
program definitions; nested expression queries retain their own boundaries.
SQL input lowering reports typed refusals without publishing invalid relations.
Legacy modifier ordering and the independent DELETE target's database selection
remain input-boundary concerns, with no source tree retained in semantic values.
The input matrix matches 930 native observations across five releases.

The same six directly constructed derived query roles are also compared before
table creation, after insertion of duplicate rows and after a data update:
90 complete native/memory observations across five releases. Those histories
exposed lost source metadata on materialized DISTINCT columns. Materialized
base fields now preserve their source table, their schema on modern releases,
and their no-default flag. Another 55 native observations cover LIMIT, DISTINCT,
GROUP BY, set operations, aliases and computed columns through the SQL input
boundary. The direct histories exercise accepted structures without submitting
their SQL to Memory. These checks do not close the remaining whole-model
construction and version-domain obligations.

Array CAST construction now rejects element types that no multi-valued index
accepts. Input lowering emits a typed refusal before publishing such a scalar;
completed-prefix diagnostics use the same target rule. An unsupported array
element kind takes precedence over its excessive precision, and MySQL 8.0 and
8.1 preserve later completed warnings while 8.2 onward stop. The input matrix
checks every cast kind, explicit lengths, character sets and competing failures:
1,044 native observations across six modern releases. This establishes the target
domain only. Separating index-only array conversions from ordinary scalars,
stored-routine restrictions and functional-index execution remain unresolved;
these rejected-input checks do not establish the whole-model construction gate.

The complete public-root audit at `5fd0b90f519`, using seed
`contract-20261015`, retained all 245 roots and recorded 94 matching successes,
143 matching SQL errors and eight differences, with no generation, profile or
infrastructure failures. The remaining differences concern random passwords,
loadable-library diagnostics, ordered LOCK TABLES warnings, open-table physical
cache contents, engine reports, installed plugins, process lists and status
counters. Matching SQL errors still require the classification and transition
witnesses in the acceptance contract; this audit does not close that gate.
LOCK TABLES resolves completed lock specifications in order. A lock mode's
semantic notice precedes that table's identifier, default database and duplicate
alias checks; a failure preserves only notices reached through that lock. Input
lowering also publishes completed `TableLock` values before a malformed suffix,
so the same consumer checks that prefix without inspecting a parser tree.
Native histories reuse six original structures before database selection, after
selection and after table creation on MySQL 5.6 and 5.7 (36 observations). A
separate five-release matrix checks 220 input observations, including long
identifiers, duplicate aliases, deprecated modes and later syntax errors.
These histories establish the database/table state transitions for those
structures, not the remaining whole-model construction gate.

SHOW PLUGINS now projects the same release-specific catalog as
INFORMATION_SCHEMA.PLUGINS. Installation and removal resolve built-in identities
against that catalog. Native checks compare both views and quoted removal
requests on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1. MySQL 5.6 reports warning 1619
before error 1305 for a built-in removal; later releases report error 1619.
The original generated SHOW PLUGINS structure now agrees, including metadata,
without an exception contract. This repairs a consumer catalog mismatch rather
than establishing the remaining whole-model construction invariants.
The direct comparison fixture closes administrative setup handles before the
first generated request, on both servers. It does not remove SHOW OPEN TABLES
from comparison or exempt its rows. Native histories reuse one constructed
listing before table creation, after a populated write and read, and after
FLUSH TABLES on five releases (35 observations including the initial complete
listing). The original generated open-table request agrees from that shared
baseline. Cache contents after user requests remain part of the comparison.

Direct execution starts a client request's activity and request counters before
semantic derivation. The comparison fixture executes its encoding and variable
setup as two constructed SET statements, matching the native setup history.
After saving the target response, the direct observer also executes a constructed
SHOW WARNINGS, mirroring the native diagnostic read. No source SQL is submitted
to memory for these operations. Native histories compare the same status objects
before and after successful scalar requests and intentional SIGNAL errors on
five releases (110 observations), retaining exact Questions and command counts.
This closes those request-history discrepancies; it does not exempt physical
status values or establish all remaining status-listing contracts.

Complete direct SHOW STATUS listings use independent GLOBAL/SESSION baselines
and the same value policy as SQL differential fuzzing. Session command counts
remain exact; global command counts retain their observed history deltas. Native
wire bytes are checked against mysqlnd measurements and the actual request size.
Direct calls have no transport and must retain zero byte counts. Physical
quantities must be zero or empty in memory. All metadata, variable identities,
diagnostics, live connection counts and unclassified values remain exact.
The fixture mirrors its two overlapping setup connections instead of exempting
the maximum connection count. Thirty comparisons reuse the same status objects
against independent native instances across five releases and three scopes;
negative controls reject changed counters, nonempty physical values, extra names,
metadata, diagnostics and termination. Raw observations remain in each record.

Direct random `SET PASSWORD` requests for the current account use the same
relationship contract as SQL differential fuzzing. Each instance validates its
own generated password, account identity and configured length. Subsequent
constructed requests require an incorrect replacement password to fail and the
returned password to succeed. With RETAIN CURRENT PASSWORD, the previous primary
hash must become the secondary; DISCARD OLD PASSWORD must remove that secondary
without changing the replacement primary. Memory never receives a native password
or hash. Only the validated returned password field is substituted in comparison
copies; protocol metadata, warnings and original observations are preserved.
The comparison fixture uses the default generation length on both sessions.
Twelve native cases execute the same original structures against two independent
instances on MySQL 8.0, 8.4 and 9.1 (24 comparisons), covering implicit and explicit
current users and both retention choices. Four properties exercise missing
primaries, forged passwords, broken retention and incomplete validation responses.

Direct process-list comparisons bracket each instance with independent identity,
clock and process-table samples. Allocated identities, stable TCP peer ports and
bounded elapsed times use the shared SQL fuzzing contract. A direct memory call
has no TCP peer port and must report that empty physical quantity. Every row and
all other fields remain exact, including `Info`: the fixture supplies the same
request label out of band. This label belongs to the running connection, not the
semantic structure. A property reuses one SHOW PROCESSLIST object with unrelated,
invalid and multibyte labels, proving that execution does not parse the label.
Ten native cases compare the same FULL or ordinary listing against two independent
instances on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1 (20 comparisons). Negative controls
retain differences in identities, ports, elapsed times, other values, metadata,
warnings, duplicate rows and termination. The original public-root counterexample
now passes while both original observations remain available.

At checkpoint `c8dd3a0dfe3`, the complete 245-root comparison with seed
`contract-20261015` reports 99 matching successes, 146 matching SQL errors and
zero differences, with no generation, profile or infrastructure failures. All
roots are retained. This passes the sampled SQL-outcome comparison only. The
146 rejections still require intentional-condition explanations, same-object
state witnesses or repairs to intrinsic structural contradictions; they do not
certify the constructible-structure gate. In particular, the privilege-level,
row/VALUES and functional-index domains remain under investigation.


Nested row constructors remain valid comparison operands. The consumer checks
the arity of every nested pair before comparing corresponding leaves in their
original order. This preserves lexicographic ordering, null-safe equality and
unknown values in ordinary equality and IN lists. Direct tests compare 58
constructed expressions on each of five releases (290 native observations),
including nested grouping and NULL in different positions, without submitting
SQL to memory. This consumer repair does not close the separate public-type
defect that still permits a Row at ordinary scalar positions.


Written row comparisons also carry their diagnostic phase. Different outer
widths are checked during contextualization of a completed statement in
MySQL 5.7 and modern releases; MySQL 5.6 defers the check. Scalar/row pairs, nested shape
conflicts and membership/subquery widths remain preparation conditions. The
semantic input refusal retains this distinction, including in stored-program bodies;
execution does not infer it from SQL text. LOAD checks its input restrictions
before preparing assignments, so disabled LOCAL input precedes a subquery width
error. A direct history reuses one LOAD structure while its wildcard subquery's
catalog changes from absent to one column, then two columns, then absent. Native
comparison includes the resulting table, file-policy and column-count errors.
These phase and state witnesses do not repair the remaining public row-domain
contradictions.


Standalone privilege grants admit AS only at the global privilege level. The
constructor rejects a non-global AS clause and column privileges on routines.
Stored programs use `DeferredGrantAs` for the distinct instruction that native
MySQL accepts at definition time and rejects only when reached. The value retains
the typed grant and AS operands and implements `ProgramStatement`, not the
standalone statement interface. The retained privilege operands provide definition-time table facts where the
target admits binding. SQL lowering reports an immediate typed
refusal before publishing a contradictory standalone grant.

A type-driven property retains all twenty generated deferred grants on three
releases. The same CREATE and CALL objects are executed before definition, after
definition and after duplicate definition, without SQL input to memory: 240 native
observations match. Another 189 observations exercise immediate, invoked, skipped
and handled conditions, and 70 observations check routine column privileges and
competing input boundaries on five releases. These tests close these grant
constraints; other privilege-level restrictions remain separate obligations.


`ExplainConnection` retains only a connection identity, format and legacy
modifier. ANALYZE and INTO cannot inhabit that public statement type because no
connection state makes either option valid. Lowering reports their typed input
refusal before constructing a statement; a stored definition preserves its
non-standalone refusal and earlier format or numeric-token errors. MySQL 9.1
reports the ANALYZE refusal before INTO when both are written. Input comparisons
check 162 observations on six releases, including conflicting formats, malformed
identities and stored definitions. Another thirty direct observations on four
releases reuse each original request while its target connection is alive and
after disconnection. The first state completes, the second reports unknown thread
identity, and memory executes with unrelated input text throughout.


Stored invocations distinguish stale diagnostics from conditions raised by the
current caller. A new condition or a SQL diagnostics reset inside a routine
replaces stale history, even when a handler consumes the condition or a later
statement empties the area. Entering an isolated routine frame does not itself
replace that history. This preserves an empty nested call while avoiding
duplicate legacy errors and warnings. A five-release history compares 395 SQL
observations, including handled errors, SIGNAL, empty invocations, nested calls,
table reads and function warnings alongside the current caller's warning.
These histories test session diagnostics; the scalar-versus-row type boundary
remains a separate outstanding construction constraint.


Immediate GRANT constructors also reject routine kinds without an object,
column privileges outside an object level, static privileges outside their
object-kind domain, and dynamic privileges below the global level. Typed
`GrantRequest` operands are shared with the separate `DeferredGrantLevel` and
`DeferredGrantAs` procedure instructions. A refused input retains its typed
target and the first structural condition, preserving earlier account spelling
and default-database errors without consulting input text during execution.
Dynamic privilege-level refusals precede object existence and static
object-privilege errors. Legacy column-level refusals still occur during
procedure definition, including unreachable instructions.

The target-domain input matrix compares 889 SQL observations across five
releases. Twenty type-generated target instructions produce 80 direct
observations, reusing each CREATE and CALL object across absence, definition,
invocation and duplicate definition. None is discarded. The existing twenty
AS instructions also pass again on three modern releases. The full related
regression run passes 80 tests and 167 assertions. These procedure tests do not
close the later account-dependent priority of database-level static privilege
refusals.

Stored-program declaration constructors now check the body against its kind,
including instructions in unreachable branches and handlers. Functions and
triggers reject privilege changes that imply a commit, dynamic SQL, returned
result sets, transaction boundaries, RESET and FLUSH. Other program kinds reject
RETURN, and functions require a RETURN instruction somewhere in their body.
Restrictions shared by every program kind are checked before these body-wide
conditions. This describes a language structure; it does not ask Semantics to
certify execution eligibility.

SQL lowering publishes a typed `RefusedProgramBody` diagnostic instead of an
invalid declaration. Its retained header, field types and numeric completion
positions preserve earlier definer, database and type conditions, including
ENUM/SET notes interleaved with later grammar warnings. It contains neither
source SQL nor a parser tree. A five-release matrix compares 747 SQL observations
covering body kinds, condition priority, existing definitions, both strictness
settings and headers without a selected database. A separate constructor-only
sample retains 100 generated declarations across all five body-bearing roots,
without generation or profile failures. These checks do not close the remaining
lexical-scope constraints, other implicit-commit statements, or the whole-model
construction gate.

Program expressions preserve MySQL 5.6 diagnostic history until they raise a
new condition, including control predicates, defaults and table-reading
subqueries. Retained warnings do not invoke their handler again. A five-release
history adds 440 observations for these expressions, alongside the prior
395-observation stored-condition history. Later releases retain their distinct
reset behavior.


A stored function retains either external code or a `FunctionBody`: RETURN or a
compound control structure capable of containing RETURN. Cursor instructions and
ordinary SQL statements cannot be its entire body. The declaration constructor
still checks that an actual RETURN exists, including unreachable instructions
and handlers; it does not require every execution path to reach one.

Routine constructors also relate the effective language to the body format.
The last LANGUAGE characteristic wins; omitted LANGUAGE and the SQL identifier
in any letter case mean SQL instructions. Other languages require an external
body. A refused SQL input keeps its typed header and a numeric diagnostic
location, without publishing an invalid declaration or retaining SQL or ASTs.
Without a language component, MySQL 8.1 refuses external routine creation;
8.2 and later retain the definition with a warning and refuse its invocation.
The emulator preserves this distinction and the release-specific message text.

Stored routine and trigger bodies cannot retain client `Parameter` occurrences:
their invocation variables do not provide a client binding scope. The check also
visits scalar subqueries, local defaults and unreachable branches. Literal text
and opaque external code remain uninterpreted. SQL input is represented by a
typed `ParameterScopeMismatch` refusal and its completed header, without
publishing the contradictory declaration. Events have a separate binding domain
and are not included in this routine/trigger constraint.

Preparing an otherwise valid routine or trigger definition follows its own
protocol restriction. Memory checks the typed header and reading diagnostics
before error 1295, independently of target-object existence. Text execution
retains its distinct marker error and all preceding declaration notes. An
already recorded condition sequence is preserved when the refusal propagates.

The direct comparison also retains an unknown-database error for a qualified
request, creates and selects that schema on both engines, and retries the same
object. Schema names are typed identifiers, including quotes and Unicode; the
native diagnostic supplies fixture state only. Every setup observation remains
in the report. A setup difference or unrecognized database diagnostic fails the
comparison instead of dropping the history. Five-release tests exercise a
qualified function that becomes creatable after the missing schema is created.

LOAD constructors intersect their input features with their option constraints.
MySQL 8.0 and 8.1 ignore bulk annotations, so an otherwise valid legacy load can
retain them. Combining incompatible annotations with S3, PARALLEL, MEMORY or
compression has no supported interpretation and is rejected during construction.
Compression also cannot accompany a zero file count. Source and bulk features
participate in the enclosing generation checks, preventing legacy dotted column
references from being combined with newer LOAD forms.

SQL lowering reports incompatible inputs as `RefusedLoadOptions`, retaining only
the target name, format, character set, assignments and typed conflict. The input
boundary preserves earlier database, charset and assignment conditions, together
with charset warnings and stored-program restrictions. Original invalid SQL stays
in the native comparison matrix. A constructor sample of 100 LOAD values and the
245-root sample remain constructible without exclusions or increased budgets;
these samples do not establish every possible cross-release combination.

Identifier character sets are interpreted at the input boundary. In particular,
Latin-1 input bytes become decoded system-character-set names before the model
is published. Planning uses those names directly; changing `character_set_client`
does not reinterpret an existing name. Rendering reverses that conversion for
the selected client context. Observable expression layouts retain name spellings
in the same representation as their typed identifiers, while string literal
slots retain their original value bytes. Tests reuse the identical statement
object under Latin-1 and UTF-8 contexts and compare native legacy filename
errors, including Unicode, quoted delimiters and reserved filesystem names.
Conversions for additional client character sets remain separate work; this
change does not establish equivalence for arbitrary connection encodings.

Structural publication checks names in the model's canonical representation. It
must not reject a valid decoded name merely because the current client character
set cannot spell it. `Operation::toString()` reports `UnrepresentableName` if
rendering would replace identifier characters. This does not prevent direct
structure consumers from resolving or executing the same object. Result labels
and origin metadata follow the session's result character set independently.

Prepared statements retain the semantic object, its lexical profile, its default
database and the settings that interpret literals. Ordinary SQL EXECUTE and
binary-protocol execution resolve fresh table and parameter facts from this
object. Their request text is an observable label outside the semantic graph;
the direct execution path accepts an unrelated invalid label. Result encoding
and exposed session variables use current values.

Protocol preparations also track the identities of their referenced relation
declarations. A changed declaration invalidates the preparation and returns to
the SQL input boundary. Observations on MySQL 5.6, 5.7 and 8.4 show that this can
reinterpret double quotes, double pipes and identifier bytes under current
settings while retaining the original default database. A wire-level observation
on 8.4 confirms this happens without a second client COM_STMT_PREPARE. This
protocol behavior is separate from rebinding a semantic value: explicit direct
execution always retains that value. Planning receives the newly published
structure after protocol repreparation, without access to either request SQL or
parser nodes.

Stored program constructors also exclude multi-valued index casts throughout
their bodies, including local defaults, cursor queries, unreachable branches
and nested index definitions. This is an intrinsic declaration restriction;
no database state or invocation can make that arrangement valid. Input lowering
preserves the corresponding error at cast completion, earlier declared types
and instruction errors, and the different continuation behavior of early MySQL
8 releases. Event schedules remain outside the stored body and retain their
separate expression restrictions. Array index declarations now use the separate key role described below. General
scalar ARRAY casts and other intrinsic operand domains still require further
work; these checks do not establish the whole-model construction property.

Ordinary functional indexes now retain their typed expression parts in the
dictionary. Their computed values participate in inserts, updates, uniqueness
checks and table rebuilding without becoming SQL columns or name-resolution
declarations. ALTER and CREATE TABLE LIKE reuse these expressions without
parsing the display text. Added index declarations are reconstructed through
their semantic constructors before combining them with retained declarations,
so repeated execution of one statement does not create repeated occurrences
inside the new structure.

The direct functional-index history reuses the same CreateIndex object before
its table exists, before its column exists, with duplicate fixture values,
after removing a duplicate, and after creating the index. Each stage compares
results, errors and warnings with MySQL. The memory request label is unrelated
invalid input throughout direct execution. Separate SQL histories compare
dependency errors, inferred result-type restrictions, populated table changes
and dictionary metadata on MySQL 8.0, 8.4 and 9.1. These witnesses close the
tested ordinary-index execution gaps; they do not establish the intrinsic
construction domains of all key parts.

Multi-valued indexes use `ArrayPart`, which is a key part and an `Operand`, but
not a `Scalar`. It retains the document expression, element target and written
ordering. Semantics resolves the document against the indexed table and gives
the entry its own type fact. Planning consumes that fact without reconstructing
SQL. The dictionary retains the typed part through ALTER and LIKE. Storage
converts, sorts and deduplicates its entries and compares overlapping entries
across rows, including composite unique keys and indexes added to existing data.

The array-index state witness reuses one `CreateIndex` object across a missing
table, missing document column, overlapping stored arrays, deletion of the
conflicting row, and an already existing index. Conversion histories also check
SQL NULL, JSON null, empty arrays, nested documents, scalar documents, binary
and character entries, and numeric and temporal targets. Current-row conversion
notes and their row ordinals remain exact.

There is a narrowly observed diagnostic exception for reusing a deleted primary
key after a decimal array conversion. Fresh native instances executing the same
history have reported either zero or four old rounding notes on a later integral
array insertion. The live logical table is empty at that point. The retained
`ArrayIndexRetiredRowsTest` runs two fresh native instances and memory. Only its
final integral insertion permits zero or four copies of the exact Note 3751
message for the previously deleted index entries; results, errors and every
other statement remain exact. Memory reports zero such historical notes. Normal
conversion cases use fresh primary keys to avoid mixing this history into their
oracle. No general warning suppression is applied to SQL Faker or direct
structure comparisons. This observed variation is consistent with retired
physical index entries; it does not establish the server's internal mechanism.

VALUES rows are checked against both the first row's width and the insertion
target. Matching the target's column count does not excuse a difference from
the first row. Empty rows and DEFAULT have different meanings in read queries
and INSERT sources. Stored programs defer these checks until an instruction is
reached; an unreachable VALUES instruction does not prevent creation or a call.
The retained SQL histories test each position, including the table contents
after a refused insertion. Direct histories reuse one InsertQuery object while
the table, its column count and its defaults change, and compare stored rows
after every submission. These witnesses distinguish actual state dependencies;
they do not close the remaining public query-role constructor domains.

CASE takes single values for its compared operand and every WHEN, THEN and ELSE
operand. Equal row widths do not make row-valued CASE operands valid. Semantics
resolves each WHEN/result pair before the compared operand and ELSE, retaining
the same first diagnostic as native MySQL when names and operand widths both
fail. Tests compare standalone expressions, reached stored-program instructions
and unreachable branches. General row and aggregate construction domains remain
unresolved; matching these input diagnostics does not establish that property.


Column index parts require a positive decimal prefix within the integer token
range. Zero is an intrinsic contradiction, including unreachable instructions
and an existing CREATE TABLE IF NOT EXISTS target. Lowering reports
`ZeroKeyPrefix` with the decoded column identity, without publishing an invalid
key part. The input boundary preserves the earlier legacy database requirement
and later grammar errors or warnings. These constraints apply to every role
that uses `ColumnPart`, including foreign-key column lists.

Positive lengths remain subject to the current column declaration. A direct
history reuses one CreateIndex object across a missing table, a missing column,
an integer column, a string column with duplicate prefixes, deletion of the
conflicting row, and an existing index. MySQL and memory agree on all six stages
on 5.6, 5.7, 8.0, 8.4 and 9.1. The same structure is never replaced and its memory
request label is unrelated invalid input. These observations distinguish the
constructor's intrinsic bound from column-type and row-state dependencies.

The native observer also runs `SHOW WARNINGS` after each target. A statement
that expires the current password can succeed while this follow-up query fails.
Such observations preserve the target response and record the separate
`warningsError` (code, SQLSTATE and message), with `warnings: null` and the
connection's actual state. The direct observer executes the same typed follow-up;
its SQL condition is compared exactly. Internal exceptions still fail the audit.
A same-session expiration/reset history verifies that warning access returns.

Prepared branch types are derived by SQL Semantics from the other CASE, IF,
IFNULL and COALESCE results. Legacy NULL markers have distinct binary-string
aggregation behavior; literal NULL remains ignorable. The direct comparison
supplies the inferred domains before execution and compares protocol metadata
as well as values, including nonempty multibyte results.

Spatial-reference identifiers in CREATE, DROP and ORGANIZATION attributes accept
integer tokens only. Decimal fractions, exponent notation and decimal tokens
above the unsigned 64-bit token range are rejected before a semantic value is
published. The input adapter preserves the existing fractional-number refusal,
including its boundary inside a stored program. This narrows the lexical operand
domain; it does not establish the validity of every spatial definition or make
existing identifiers independent of database state.

`AlterEvent` requires at least one change after its event name. A definer alone
does not satisfy this invariant. An empty SQL alteration produces a typed
`EmptyEventAlteration` instead of an invalid statement. Completed program headers
supply the enclosing database and definer even when later input does not parse;
only those typed operands and numeric diagnostic positions leave input lowering.
Native histories exercise absent and existing events, valid comment changes,
functions, procedures, triggers, event bodies and unreachable instructions. The
integer-token and event-change checks are constructor guarantees, not an execution
eligibility decision supplied by SQL Semantics.

DEFAULT character conversion uses the session's database encoding, which may
differ from the target table's schema and may be changed by SET. Resolution
settings retain that collation explicitly. A same-object conversion history
compares missing tables, table creation, USE and encoding assignments against
MySQL 5.6, 5.7, 8.0, 8.4 and 9.1. Routine invocation temporarily supplies its
database encoding and restores the caller's values afterwards.

Semantic facts associate notices with their owning stored instruction. This
association survives direct construction without source locations. A syntax
failure in a later instruction does not suppress an earlier instruction's
contextualization; a failure within the same instruction does. Legacy expression
collation names are resolved when the stored instruction is invoked. Native
histories compare creation and invocation, including instructions in an
unreachable branch. These checks do not replace the whole-model audit or establish
that every remaining expression role is coherent.

MySQL 9.0 and 9.1 enforce column-level REFERENCES clauses, including their
implicit child indexes. An omitted parent column list resolves to the parent's
primary key while foreign_key_checks is enabled. It remains unresolved when the
parent or primary key is absent, or when checks are disabled. Direct histories
reuse one column-level or table-level reference through those state transitions;
the semantic request is unchanged and retains no input SQL. Earlier supported
releases ignore column-level references. The versioned wire histories also check
populated INSERT and ALTER TABLE behavior and result metadata.

Release-specific catalog fixtures cover every supported MySQL release, including
8.1, 8.2 and 8.3. Replication vocabulary notices retain the release in which their
legacy spelling is deprecated, including when a later operand is rejected.
Native catalog tests establish the same account password and proxy-grant fixture
on both servers before comparing SHOW GRANTS; container installation differences
are not treated as SQL-result exceptions.

Account lists preserve occurrence order during execution. An ALTER USER batch
checks each recipient against changes made by earlier occurrences and retains
all recipient conditions. MySQL 5.7 keeps successful changes on failure; modern
releases restore account data but require a password reset when the failed batch
successfully addressed the acting session's account. The warning observer keeps
that subsequent access failure separate from the original ALTER USER error.
CREATE USER checks additional authentication methods only for new recipients,
while retaining the authentication-policy notice for existing recipients. Native
histories and direct structure replays compare these distinctions without
replacing failed targets or suppressing their conditions.

VALUES width conditions retain their owning semantic row, and AT TIME ZONE
conditions retain their owning conversion. Consumers place a row condition
before its elements and a conversion condition after its operand, without
recovering input text. Native comparisons include competing window names and
missing columns. A direct conversion history preserves one query object across
an absent table, an integer column, an empty timestamp column, populated timestamp
rows and a datetime column. These checks establish diagnostic ownership and the
observed state transitions; they do not close the outstanding general row and
VALUES constructor domains.

A multiple-table DELETE cannot retain two exactly identical qualified target
names. This constructor invariant applies before `Operation` construction;
case comparison and resolving omitted database names remain contextual. SQL
input that violates the invariant produces `RepeatedDeleteTarget`, holding only
the typed prefix through the first repetition and the enclosing declaration
identity. This preserves default-database precedence without retaining source
text or an AST. Native checks on 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 include
populated tables, unknown aliases, qualified program headers and unreachable
stored instructions. Only 5.6 reports the completed repeated target before a
later syntax error; later releases finish parsing first.

Text operands whose grammar excludes line feeds enforce that domain at
construction, independently of ordinary string literals. The check stops at the
first NUL, matching the affected server settings. SQL input that violates the
domain retains a typed text diagnostic, including when a later parse-tree
inspection command would otherwise fail. Memory applies the session character
set and the diagnostic byte limit to that value. Differential histories cover
Latin-1 and UTF-8 connections, NUL ordering, multibyte truncation and the retained
unseeded fuzz input on the applicable releases. Spatial-reference attributes also
exclude repeated roles; lowering preserves the first completed attribute error.

`ReplicaUntil` requires a complete source or relay coordinate, or a GTID or
gap condition. It excludes combinations of those alternative condition kinds
before an operation can be constructed. Source configuration and running applier
state remain execution concerns. Native histories preserve the UNTIL request
through absent configuration, source configuration, repeated starts, stopping
and reset. Memory records the configured source host and local replication
state; it does not open a network replication connection. These bounded checks
do not replace the outstanding whole-model and SQL Faker campaigns.

Expression-subquery constructors exclude HIGH_PRIORITY, SQL_BUFFER_RESULT and
SQL_CALC_FOUND_ROWS throughout their query-expression spine. Lowering reports
their typed placement diagnostic before publishing a scalar or row subquery.
Top-level parenthesized queries keep these modifiers. Unused CTE definitions
retain their deferred preparation behavior; statement-level modifier checks
apply when the query body is used. Native comparisons include populated tables,
unreachable stored instructions and the 5.6 precedence of misplaced modifiers
over conflicting quantifiers.

Multiple-table DELETE targets bind to input occurrence identities in Semantics.
An unqualified target uses the current database unless it names an explicit
alias. A qualified target selects its database even when another input has the
same table name. Execution, row locking, LOCK TABLES checks and read-only
transaction checks consume these bindings. Direct construction tests preserve
one DELETE object while switching the current database; native histories also
compare populated tables and asymmetric READ/WRITE locks across databases.

Character-set introducers retain their notices even when the following literal
is unfinished or the introducer is a refused lookahead. Text, hexadecimal and
bit literals with wide character units receive leading zero padding. UTF-32
radix values carry a typed invalid-character diagnostic for out-of-range code
points and, on modern releases, surrogate code points. MySQL 5.6 reports a
completed invalid radix value before later syntax errors. Unused CTE definitions
keep their delayed preparation. Native observations compare values, metadata,
ordered conditions and subsequent statements across five releases. These checks
do not establish the outstanding whole-model construction property.

`CreateFunction` takes a `FunctionImplementation` combining the body and its
interpreting characteristics. Its constructor checks the effective LANGUAGE,
RETURN presence and function-body restrictions independently of the declaration's
name, parameters and return type. It is a declaration operand, not a program
instruction. The completed function retains the same body and ordered
characteristics for resolution and execution. This makes the coupled invariant
available when constructing that operand, instead of repeatedly rebuilding
unrelated declaration fields after an inconsistent language/body choice.

Process-list comparison admits one additional observed clock bracket: a sleeping
repair connection can report TIME = -1 between two process-table samples whose
TIME values are both zero. Two independent MySQL 8.4.7 instances received the
same 1,000 probe sequences; one reported only zero brackets, while the other
reported six `[0, -1, 0]` brackets. The exception is limited to that identified
repair connection, its unchanged sleeping state and exactly those sampled
bounds. Other negative times, wider bounds and all non-clock fields retain their
previous checks. Memory continues to report its own elapsed time.

The function-implementation revision passed all 120 `body-domain` seeds at the
unchanged 2,000-attempt cap, 8,068 SQL Semantics tests, 15,574 Memory unit,
property and documentation tests, and 57 native routine/process comparisons.
Static checks include dependency boundaries and the 890 retained-value schemas.
These results do not replace the pending whole-model native campaign.

The preceding `1e26e29cc78` unseeded campaign failed after 464.3 seconds and
675 inputs: 673 matched, one process-clock observation lacked a comparison
contract, and one event schedule reported a scalar-width condition before its
native dependency restriction. The clock bracket above addresses the observed
comparison case. Event schedules still need a model that preserves dependency
priority, unused CTE preparation and invocation-time checks in stored programs.
This campaign does not meet the 900-second gate; all nine supplied seed corpora
and an unseeded campaign must pass together on the final revision.

Event schedule input refusals retain the event name and definer separately from
an operand's clause and row role. This lets Memory apply default-database
precedence and legacy conversion diagnostics without reparsing input text or
publishing a row as a scalar. A normal schedule's dependency traversal skips CTE
definitions but keeps their references: an unused definition supplies no active
table dependency, while reading even a constant CTE does. Stored-function calls
remain dependencies whether or not the routine exists.

Administrative deprecations belong to semantic operands. Each legacy QUERY
CACHE flush item retains its notice, including repeated items, later input
failures and a stored routine's first load. These input and history properties
are separate from the outstanding whole-model constructor invariant gate.

AT TIME ZONE distinguishes the input role of a TIMESTAMP literal from its
DATETIME output field. Ordinary `AtTimeZone` constructors reject incompatible
literal operands and the fixed result families of casts, arithmetic, interval
arithmetic and clocks, including grouped operands. Stored definitions instead retain
`DeferredTimeZone`, which is not a `Scalar`; its typed condition is raised only
when the instruction is reached. Deferred arithmetic, calls and predicates carry
that condition without converting it into a row-width error. Names and other
compound expression types still depend on resolution. This operand-domain constraint does
not close the outstanding whole-model invariant gate.

Named time-zone availability is initial instance state, independent of the SQL
structure. An empty named catalog retains numeric offsets and SYSTEM, while
named conversions and mysql.time_zone queries observe the empty state. Native
comparison fixtures initialize the same empty or populated catalog before
executing constructed structures; they do not replace or exempt query results.

Legacy column-privilege refusals retain only the completed privilege, target and
optional stored-declaration scope in `RefusedColumnLevel`. They do not fabricate
recipients to complete a GRANT request. Target completion can precede an invalid
or unfinished recipient list, so input lowering preserves that boundary for both
GRANT and REVOKE. The consumer resolves the current database from these typed
operands, independently of the request label. Native tests cover complete and
incomplete input, reached and skipped definitions, and database selection on all
five representative releases.

Table maintenance returns input conditions as rows associated with the first
target and clears the ordinary warning list. This applies to histogram requests
and partition maintenance as well as CHECK, ANALYZE, REPAIR, OPTIMIZE and key-cache
commands. The warning descriptors already supplied by Semantics are consumed
without consulting source SQL. Tests retain missing, repeated and populated
targets and compare row ordering, metadata and subsequent warning state.

Stored negation, prefix operators and NULL tests preserve an enclosed deferred
condition through `DeferredNot`, `DeferredUnary` and `DeferredNullTest`. They do
not implement `Scalar`. Their grouping and lexical-mode invariants use the same
rules as ordinary operators. Direct construction histories reuse one invocation
with the branch disabled, enabled and disabled again; native histories compare
the same transitions. `DeferredCast` retains an enclosing scalar cast's target
and dependency without admitting the expression into the ordinary scalar domain.
An ARRAY cast has no stored deferred variant: native stored definitions reject
it even inside an unreachable instruction. Tests compare both definition-time
rejection and invocation-time conversion conditions. Other compositions, including
CASE, character-set conversions
and additional SELECT clauses, still require equivalent treatment. These tests
do not establish the whole-model construction gate.

Window count and offset literals retain their unsigned 64-bit integer-token
role. Fractional, exponent and out-of-range decimal literals are rejected by
`WindowFunction` construction. A `FrameBound` cannot carry an INTERVAL unit
without an offset. Variable operands remain state-dependent: the same directly
constructed NTILE query is compared over populated tables while its referenced
variable changes between zero, positive counts and NULL. Ranking functions
resolve to unsigned BIGINT; stored window outputs and their parents retain the
result column's name and storage flags separately from direct expression
metadata. Native tests include literal boundaries inside unreached instructions,
all window function families, aliases, arithmetic parents, empty input and
ordinary populated input on MySQL 8.0.44, 8.4.7 and 9.1.0. These checks do not
close the remaining whole-model constructor invariant gate.

Empty table definitions retain their semantic condition while Memory applies
release-specific preparation order. MySQL 5.6 and 5.7 reject missing columns
before looking up the database or validating storage options. MySQL 8.0 and
8.4 can complete an empty `CREATE TABLE IF NOT EXISTS` when the target already
exists; the same directly constructed object fails when that table is absent
and succeeds against a populated target. MySQL 9.0 and 9.1 reject the empty
definition even when that target exists. These latter observations are not
counted as successful state-transition witnesses for constructor validity.
Constraint diagnostics and tablespace lookup retain their precedence. The
ordinary empty-definition and deferred definition domains still need review;
matching their SQL errors alone does not establish the construction property.

Legacy EXPLAIN modifiers carry their deprecation notices as semantic values.
Completed modifiers retain those notices when later input is refused; a bare
`EXPLAIN EXTENDED` remains a table-description request. Native tests compare
96 modifier observations on MySQL 5.6 and 5.7. This does not address the separate
canonical-query note of successful extended EXPLAIN output.

An explicit database character-set or collation DEFAULT resolves the server's
current setting. A missing legacy database reports its encoded options-file
condition, and repeated CREATE DATABASE IF NOT EXISTS retains its native
completion count. Native histories compare 171 statements across three releases
and 60 observations of unchanged AlterDatabase objects across absent, populated
and dropped schemas. Unrelated request text remains outside those objects.
The repair snapshot passed 8,329 Semantics tests and 17,934 Memory unit,
property and documentation tests, static checks for both packages, and all seven
native modifier/default tests. The outstanding construction and full-fuzz gates
remain required by the acceptance contract.

Legacy UNION enclosure requirements are derived from typed query operands. In
MySQL 5.6, an enclosed first operand requires the following operands to be
enclosed too. Directly constructed structures retain the versioned diagnostic
without requiring source SQL; input lowering separately retains the completed
operand position for native syntax-error excerpts. The publication replay can
therefore inspect the structure without throwing an input-lowering exception.
Native tests compare absent, populated and dropped table histories using an
unchanged constructed query and unrelated request text. MySQL 5.7 admits the
mixed enclosure form and responds to the table state; 5.6 keeps its structural
rejection in all three states. These matching intrinsic errors are not counted
as evidence of constructor validity. Legacy materialized UNION fields retain
the first result name and integer storage widths. Focused enclosure and integer
metadata tests pass, while a broader mixed-domain probe still has 155 metadata
differences among 584 observations; that surface remains unresolved.

Stored language members and ordinary SQL instructions now share the derivation's
program scope and explicit notice ownership. Nested derivations restore their
outer instruction even on failure. Legacy expression COLLATE binds operand
dependencies before looking up the collation name, and defers that lookup inside
stored definitions. Modern nonempty names retain their parsing phase. A row or
deferred operand under COLLATE is represented by `DeferredCollated`, never by
`Scalar`. Stored DO items similarly retain `DeferredProjection` without losing
ordinary items or wildcard roles. Memory prepares those dependencies when the
instruction is reached. Native comparisons across 5.6, 5.7, 8.0, 8.4 and 9.1
match 975 SQL observations and 240 creation/invocation observations for 60
directly constructed routines. The same CALL structure is skipped, reached and
skipped again with unrelated request text. This establishes the repaired
composition and phase behavior, not the remaining whole-model invariant gate.

Function return declarations now exclude a non-national character type with an
unresolved BINARY-only collation at construction. The input adapter reports its
native condition without constructing that invalid value and preserves notices
from the completed declaration. Version-dependent national and named character
sets remain represented; their conditions derive from the release. ENUM and SET
return declarations keep their distinct binary-attribute rules, including the
legacy handling of named character sets.

Character-set resolution belongs to SQL Semantics: ASCII selects latin1,
UNICODE selects ucs2, and a BINARY mark applies after selecting the character
set. Memory delegates this rule, retains the release of a stored definition,
and uses the same return collation for evaluation and SHOW CREATE. Loading an
implicitly materialized deprecated return collation preserves its notice.
Native integration across 5.6, 5.7, 8.0, 8.4 and 9.1 passes 680 SQL observations
and 240 direct-construction observations. The latter reuse 60 function objects
through absent-database, existing-database, duplicate-function and dropped-function
states with unrelated request text. These are focused return-domain witnesses;
they do not close the outstanding whole-model construction and fuzzing gates.


Stored window invocations now preserve non-scalar argument roles in
`DeferredWindowFunction`, separately from ordinary `WindowFunction` scalars.
The shared argument-position rules retain stable counts, value arguments and
primary-expression row numbers; the deferred form also retains the OVER clause,
NULL treatment and counting edge. Reaching the instruction prepares its
arguments before the retained width condition. A skipped instruction does not
prepare the call. Function-option conditions precede frame limitations after
name resolution. Native integration on 8.0, 8.4 and 9.1 compares 597 SQL
observations and 180 creation/invocation observations for 45 directly constructed
routines, covering FIRST_VALUE, LAST_VALUE, LEAD, LAG and NTH_VALUE. Their same
CALL objects are skipped, reached and skipped again over populated tables with
unrelated request text.

Definer preparation now preserves the order of table/database resolution,
account notices, query errors and view-target checks. Event definer notices
precede schedule evaluation, while earlier schedule restrictions retain their
priority. Creating or dropping an account reports references from existing
events, views, stored routines and triggers in native family order. View
materialization rules distinguish views from derived tables on MySQL 5.6;
result origins retain both the public view identity and the underlying column
identity. Direct references, aliases, nested views and derived-table references
retain their different metadata without recovering SQL source. Native
integration across 5.6, 5.7, 8.0, 8.4 and 9.1 compares 350 SQL observations and
360 definition/read observations for 60 directly constructed views. The same
objects run before account creation, after creation and after removal, reading
three fixture rows at every stage.

These focused comparisons preserve all result columns, metadata, errors and
warnings. They do not close the outstanding whole-model construction gate or
replace the required supplied-seed and unseeded differential campaigns.
