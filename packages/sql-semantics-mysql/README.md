# SQL Semantics for MySQL

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics-mysql.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics-mysql)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--mysql-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-mysql/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for MySQL adds MySQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md): the statement structure classes of the official MySQL grammars, the MySQL rules for resolving names and deriving types, NULL facts and diagnostics, and the MySQL spelling of rendered SQL. Installing it also installs the shared runtime. `Dialect::MySql` selects MySQL in the runtime's `Semantics`, and `Mode` describes the `sql_mode` settings that change how SQL text is read. No database connection is needed.

## Requirements

- PHP 8.1+
- No PHP extension

## Support Syntax

The following grammar releases are supported. Pass the version tag, or the version number alone, as the second argument of `Semantics`; omitting it selects the default. A statement is read with the grammar of the selected release only.

| Version | Version tag | Default |
|---------|-------------|---------|
| 5.6.51 | `mysql-5.6.51` | |
| 5.7.44 | `mysql-5.7.44` | |
| 8.0.44 | `mysql-8.0.44` | |
| 8.1.0 | `mysql-8.1.0` | |
| 8.2.0 | `mysql-8.2.0` | |
| 8.3.0 | `mysql-8.3.0` | |
| 8.4.7 | `mysql-8.4.7` | Yes |
| 9.0.1 | `mysql-9.0.1` | |
| 9.1.0 | `mysql-9.1.0` | |

## Installation

```bash
composer require k-kinzal/sql-semantics-mysql
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::MySql);
$users = $semantics->analyze('CREATE TABLE users (id INT NOT NULL PRIMARY KEY, name VARCHAR(40))');
$query = $semantics->analyze('select id, upper(name) as label from users where id > 10', [$users]);

$query->toString(); // => 'SELECT id, upper(`name`) AS label FROM users WHERE id > 10'
$query->field('label')->type instanceof Known; // => true
$query->field('label')->type->descriptor->name(); // => 'VARCHAR'
$query->field('id')->nullability; // => Nullability::NotNull
$semantics->analyze('SELECT missing FROM users', [$users])->diagnostics()[0]->message(); // => 'Column missing does not exist.'
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md) for operations, facts, contexts, rendering and guarantees.

`analyze()` declares the shared `AnalyzedStatement` interface and returns a concrete MySQL `Statement`. Transaction control returns `Begin`, `StartTransaction`, `Commit`,
`Rollback`, the three savepoint commands, the six XA commands, or `SetTransaction`.
These implement `Statement\Server\Transaction\TransactionStatement`; they retain
their operands, selected language profile, optional client encoding and optional
source span. They have no `statement`, `context`, `facts` or `sources` aggregate.
`declarations()` returns an empty list. `XaRecover::$shape` describes its four
result columns; a missing savepoint or XA branch is an execution condition.

```php
$begin = $semantics->analyze('BEGIN WORK');
$begin instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin; // => true
$begin->work; // => true
$begin->toString(); // => 'BEGIN WORK'
```

Construct typed SQL operands and pass them to `Semantics::fromStatement($context, $statement)` to resolve a fresh concrete result. Constructors enforce structural alternatives and release-dependent XA identifier limits. Every MySQL statement family uses this model, including DDL, DML, account administration and stored programs. Expressions and table uses own their local type and name-resolution information.

These account statements retain their ordered operands even when the request has
an intrinsic contradiction, such as creating the same role twice or granting a
role to itself. Their nullable `problem` describes that condition; construction
does not require an account catalog. Catalog-dependent conditions, including
whether `IF NOT EXISTS` skips an existing account, belong to execution. The same
concrete account types appear in stored-procedure bodies. There are no separate
deferred role-creation, role-grant or account-rename types.

A SELECT, TABLE, VALUES or set-operation statement has a `Statement\QueryRoot`
root. Its `query` property holds the query body (`Statement\Query\Select`, for
example). Query bodies also appear directly in subqueries, INSERT sources and
view definitions. They implement the shared `Query` role, not `Statement`.
Construct a standalone query as `new QueryRoot($body)` and pass it to `Semantics::fromStatement()`. The root enforces constraints of the statement position; `inputRelation()`, result fields and rendered SQL remain available on that concrete result.

`Statement\Dml\Insert\InsertRows`, `InsertSet` and `InsertQuery` retain complete
write requests, including incompatible written widths, non-scalar values and
query-column conditions. `hasExecutionCondition()` identifies intrinsic
conditions that the consumer must prepare in dependency order. A missing target
can take precedence over a value condition; an omitted column list leaves its
width to catalog resolution. The published statement supplies the bindings for
that preparation, without a replacement statement or another SQL analysis.

Stored conditional writes use `DeferredInsertRows`, `DeferredInsertSet` and
`DeferredInsertQuery` to defer preparation until their instruction is reached.
They retain the same targets, values and duplicate-key assignments; VALUES and
VALUE also remain distinct when rendered. A row operand remains distinct from
an ordinary scalar assignment in both immediate and stored requests.

Written declaration types implement `Statement\Type\DeclaredName`; VARCHAR and
VARBINARY declarations require a length. Expression facts can instead hold
`Statement\Type\StringFamily` when the length is unresolved. This descriptor
retains the binary/national classification and known character-set attribute,
but cannot be used as a column, parameter, variable or return declaration.
A fully resolved expression carries `Statement\Type\Resolved\Domain`.

Declared types and CAST targets keep keyword alternatives separately from their
canonical type kind. For example, `INT8` retains `IntegralKind::BigInt` and
`IntegralAlias::Int8`; `name()` answers `BIGINT`, while rendering writes `INT8`.
The same distinction covers floating, fixed-point, character, Boolean and spatial
types, including national character forms and `DOUBLE PRECISION`. Constructors
require aliases to match the kind and any national or optional-word role. Consumers
can resolve and execute the canonical type without recovering its meaning from text.

Stored-routine `ParameterList` values require distinct case- and accent-insensitive
parameter names, and `Parameter` excludes NUL. Empty names, names longer than 64
characters and names ending in an ASCII space require MySQL 5.6 or 5.7. They
cannot be composed with modern-only syntax. Modern SQL lowering refuses those
names with a typed `Construction\RejectedInput` before publishing a list.

### Releases

Each release is read with its own grammar and keywords, and version comments are read as that release reads them:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

(new Semantics(Dialect::MySql, '8.0.44'))->profile()->grammar->value; // => 'mysql-8.0.44'
(new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT /*!80000 1 + */ 2 AS n')->toString(); // => 'SELECT 1 + 2 AS n'
(new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT /*!80000 1 + */ 2 AS n')->toString(); // => 'SELECT 2 AS n'
```

### Session modes

Five `sql_mode` settings change tokenization: `ANSI_QUOTES`, `PIPES_AS_CONCAT`, `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES` and `IGNORE_SPACE`. Pass a `Mode` as the third argument of `Semantics`. `Mode::fromString()` reads a value as `SELECT @@SESSION.sql_mode` reports it: a combination mode such as `ANSI` turns on the settings it includes, and every other mode name is accepted and not recorded, because it changes no token.

Pass `Statement\Type\Resolved\Settings` with `realAsFloat: true` when `REAL_AS_FLOAT` is enabled. It resolves REAL declarations and CAST targets as FLOAT; the default is DOUBLE. This setting changes types rather than tokenization, so passing `Mode::fromString()` alone does not select it.

Also pass `Statement\Type\Resolved\Settings` with `strict: false` when neither `STRICT_TRANS_TABLES` nor `STRICT_ALL_TABLES` is enabled. This changes the reported nullability of string calls such as `HEX`, `CONCAT` and `LOWER`, even for constant non-null arguments. `Settings::defaults()` follows the release's default strict mode; its MySQL 5.6 settings start with strict mode disabled.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

Mode::fromString('STRICT_TRANS_TABLES,ansi_quotes')->toString(); // => 'ANSI_QUOTES'
Mode::fromString('ANSI')->toString(); // => 'ANSI_QUOTES,PIPES_AS_CONCAT,IGNORE_SPACE'

$default = new Semantics(Dialect::MySql);
$ansi = new Semantics(Dialect::MySql, null, Mode::fromString('ANSI_QUOTES,PIPES_AS_CONCAT'));

$default->analyze('SELECT a FROM users WHERE "name" = a || b')->toString(); // => "SELECT a FROM users WHERE 'name' = a OR b"
$ansi->analyze('SELECT a FROM users WHERE "name" = a || b')->toString(); // => 'SELECT a FROM users WHERE `name` = a || b'
```

The mode is part of the language profile. A declaration analyzed under one mode cannot be used in a context of another mode. Rendered SQL is meant to be read under the mode it was rendered for.

Input tokenization also uses the client character set in
`Statement\Type\Resolved\Settings`. Pass these settings as the facade's
`session` argument to make them the default for `parser()`, `split()` and
analysis. An explicit analysis context overrides that default. When parsing
separately for such a context, use `parser($context->session)` so parsing and
semantic analysis read the same bytes. In particular, Latin-1 identifiers and
whitespace do not use UTF-8 byte classes.

### Current database

MySQL searches an unqualified table name in the current database only. Name it with a `SearchPath` of exactly one schema; unqualified declarations then belong to that database, and qualified names in it resolve to them. Without a search path, the current database is unnamed: a name qualified with a database does not match an unqualified declaration, and facts that would show the database name depend on it as session state.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

$semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('shop'));
$users = $semantics->analyze('CREATE TABLE users (id INT)');
$query = $semantics->analyze('SELECT id FROM shop.users', [$users]);

$query->inputRelation()->relationInformation()->table instanceof DeclaredTable; // => true
(new Semantics(Dialect::MySql))->analyze('SHOW TABLES')->shape()->missing[0]->describe(); // => 'the session state: the current database'
new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('shop', 'other')); // throws InvalidArgumentException
```

### Parameters

The native parameter style reads `?` markers. `ParameterStyle::Named` adds `:name` markers, as PDO accepts them; without it, `:name` is a syntax error. A parameter's type depends on the value bound to it.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$named = new Semantics(Dialect::MySql, null, null, ParameterStyle::Named);

$named->analyze('SELECT :id, ?')->field(0)->type->missing[0]->describe(); // => 'the value bound to parameter :id'
(new Semantics(Dialect::MySql))->analyze('SELECT :id'); // throws AnalysisException
```

### Names

- Table and database names are compared exactly. Column names and output field names are compared without regard to ASCII letter case.
- Names are rendered bare when they are no keyword, function name or introducer of the release, and in backticks otherwise. Backticks are identifiers under every mode.
- User variables (`@total`) and system variables are session state; their facts depend on it.

Explicit `LOCAL` scope is retained in system-variable reads, assignments,
`SET TRANSACTION`, `SHOW STATUS` and `SHOW VARIABLES`. It has the same session
meaning as `SESSION`; `VariableScope::session()` recognizes both spellings.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

$semantics = new Semantics(Dialect::MySql);
$users = $semantics->analyze('CREATE TABLE users (id INT)');

$semantics->analyze('SELECT ID FROM users', [$users])->field(0)->resolution instanceof ResolvedColumn; // => true
$upper = $semantics->analyze('SELECT id FROM USERS', [$users]);
$upper->inputRelation()->relationInformation()->table instanceof MissingTable; // => true
$semantics->analyze('SELECT @total')->field(0)->type->missing[0]->describe(); // => 'the session state: user variable @total'
```

### Result column names

Within a stored program, parameter and local-variable references resolve to the
nearest declaration using MySQL's case- and accent-insensitive name identity.
`INTO` and `LIMIT` operands retain the same declaration and type facts during
definition. An unqualified select item can name either a column or a program
variable, so its validated spelling remains available until resolution. A
program-variable result uses the expression spelling, including quotes and
parentheses; an ordinary column result uses its decoded name.

MySQL names a select list item without alias after the text of its expression as the statement writes it, so `SELECT 1+1` and `SELECT 1 + 1` return columns named `1+1` and `1 + 1`. An item that names itself keeps its own name: a column reference its column name, a string the value of its first quoted part, `NULL` the name `NULL`, a number its text, `?` the name `?`, and in MySQL 5.6 and 5.7 `TRUE` and `FALSE` those words; parentheses and a unary plus do not change it. The server removes leading spaces and control characters from the name, and keeps at most 255 bytes of it.

Such an item keeps the spelling of its expression as a layout (`SelectExpression::$layout`), and `toString()` writes the expression as it was written, comments included, so the rendered SQL returns the same column names. The optional words that change that text are part of the model: `AS` before an alias, `OUTER` and `INNER` of a join, the empty parentheses of `CURDATE()` and the other niladic functions, `ROW`, the `OF` of `MEMBER OF`, `INT` after `SIGNED`, `CHARACTER SET` for `CHARSET`, and the 5.6 and 5.7 leading dot of `.t`. Version comment markers are left out, as the server leaves them out of the name. An item built without a layout is named after, and written as, the canonical rendering of its expression. The name ends where the expression ends: whitespace and comments after it are not part of it, so `SELECT 1+1 /* c */` returns a column named `1+1`.

The server converts the name to its system character set. A string with an introducer is named after its converted value: `_utf8mb4` text keeps its characters, with `?` for each character outside the Basic Multilingual Plane, `_binary` and `_utf8mb3` text and national strings keep their bytes. When the name cannot be decided from the statement, the field has a null name and its slot lists what the name depends on (`OutputSlot::$unnamed`). For text read in the client character set that holds characters outside ASCII, or is longer than 255 bytes, that is the session state `character_set_client`. For other introduced character sets it is the server's conversion (`NameConversion`). A lookup by name (`fields()->lookup()`) then answers a `DependentField` instead of an `AbsentField`.

Derived tables, common tables, `CREATE TABLE ... SELECT` and views take these names. A view renames a generated name that is no valid column name to `Name_exp_N` and one that repeats an earlier name with the prefix `Name_exp_` (`My_exp_` in 5.6 and 5.7), as the server does; `CREATE TABLE ... SELECT` reports an invalid one (`IncorrectColumnName`). A name in `ORDER BY`, `GROUP BY` and `HAVING` finds an item by the name it was given after its text, as it finds an alias.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\DependentField;

$semantics = new Semantics(Dialect::MySql);

$query = $semantics->analyze('SELECT 1+1, CURRENT_DATE, a total, NOW( ) FROM users', []);
[$query->field(0)->name?->value, $query->field(3)->name?->value, $query->toString()]; // => ['1+1', 'NOW( )', 'SELECT 1+1, CURRENT_DATE, a total, NOW( ) FROM users']
$semantics->analyze('SELECT `1+1` FROM (SELECT 1+1) AS d', [])->field(0)->resolution instanceof ResolvedColumn; // => true
$semantics->analyze("SELECT 'ü'", [])->fields()?->lookup('ü') instanceof DependentField; // => true
$semantics->analyze("SELECT 'ü'", [])->field(0)->slot->unnamed[0]->describe(); // => 'the session state: character_set_client'
```

A nested query in a select list can refer to an earlier result alias. For example,
`SELECT a+1 AS x, (SELECT x) FROM t` resolves the inner `x` to an `AliasTarget`.
Input columns take precedence over result aliases. Forward references, ambiguous
result names, and references to a result aggregate owned by the enclosing block
produce `InvalidProjectionAlias`, with an `AliasRule` identifying the reason.

When session settings specify user variables, a read publishes a
`UserVariableBinding` that records whether the entry exists at that occurrence.
An expression assignment introduces an entry for later occurrences without changing
the supplied session snapshot. This distinction preserves MySQL 5.6 and 5.7 reads
of absent variables. From MySQL 8.0, `CASE`, `COALESCE`, `IFNULL`, and `IF` infer
each absent operand from the first other resolved result branch, including an
explicit NULL, before aggregating all result types. An omitted ELSE adds no
inference operand.

Each `Settings::$userVariables` entry accepts a `Domain` or a
`Statement\Variable\UserVariableEntry`. Use `UserVariableEntry` to provide both
the stored value's domain and the collation retained at assignment. MySQL 5.6
and 5.7 use the assignment character set for integer result metadata, even after
the connection changes character set. A direct arithmetic operand uses the logical
integer width, while an assignment retains the variable's reported width. Legacy
`ABS` promotes that reported width into its result's numeric bound, so subsequent
arithmetic can have a wider result. A plain `Domain` assumes the current connection collation for
this metadata. An assignment to an absent variable introduces a NULL string entry
using the operand's collation, or the connection collation for numeric and temporal
operands. Later SET items and expression occurrences see this preparation state,
which differs from the type of a subsequently assigned value.
When the operand reads an existing user variable, the new entry instead takes
that variable's assignment collation. This applies even if no row executes the
assignment; numeric values still report their logical binary collation when read.
When modeling an executed assignment, an untyped NULL retains the preceding type
and collation. A typed NULL changes the value type; MySQL 5.6 also replaces its
assignment collation, while later releases retain it. Legacy decimal and double
variable metadata also expands by the retained assignment character width.

`Settings::$handlers` maps each open HANDLER alias to its qualified table name.
Supply the corresponding table declaration in the analysis context. The same
`HandlerScan`, `HandlerIndexRead` or `HandlerIndexSeek` then resolves its predicate
columns and output fields against that declaration, including invisible columns
used explicitly in a predicate. Without a supplied handler, its row shape remains
dependent on session state. Analysis does not open, advance or close a handler;
the consumer supplies a new snapshot after those operations. Key values, predicate
parameters and LIMIT parameters retain their positions in the original statement.

Rows have an explicit `Statement\Expression\RowOperand` role. `Row`,
`GroupedRow` and a fixed multi-column `RowSubquery` do not implement `Scalar`.
Comparisons and membership predicates explicitly accept row operands and check
fixed widths and nested shapes at construction. `ScalarSubquery` accepts a
known one-column query or a query whose width depends on its catalog; changes
to a wildcard's table declaration are resolved against each supplied context.

Stored-program conditions, variable defaults, CASE operands and RETURN values
also accept `RowOperand`. This models a stored instruction, whose scalar use is
checked when control reaches it. For example, a procedure can contain a row-valued
IF condition behind another IF that is false. The definition remains valid; the
consumer must bind the row's names and report its scalar-use error if that
instruction is reached. It must not replace the row with an ordinary scalar or
require the original SQL to prepare it.

UPDATE retains bare integer ORDER BY operands, including integers under
parentheses or unary plus, as execution conditions: a write has no select list
in which an output position could resolve. Row-valued assignments and predicates
also remain on the complete UPDATE request. Top-level and stored UPDATE use the
same statement type. Complete `Delete` and `MultipleDelete` requests retain
read-only, absent and repeated targets, sampling conditions and invalid ordering
operands. Their `hasExecutionCondition()` methods identify preparation work;
execution consumes the published bindings without rebuilding or reparsing the
request. Multiple-table targets also retain their optional `.*` suffixes.
Stored deletions use `DeferredDelete` and `DeferredMultipleDelete` so an
unreached branch remains dormant. A non-updatable-target diagnostic refers to
the input relation itself rather than back to its containing statement.
`OrderingPosition` retains the offending literal, including unsigned values
beyond PHP's signed integer range and written leading zeros. The MySQL 5.6 and
5.7 profiles also diagnose boolean ordering positions; modern profiles treat
those booleans as constant ordering expressions. Dependency resolution and the
consumer's preparation order determine which condition is reported first.

CREATE TABLE, ALTER TABLE and CREATE INDEX values retain fixed invalid index names and
non-scalar functional key operands as complete requests. The same types represent
top-level and stored statements; `hasExecutionCondition()` identifies intrinsic
conditions whose outcome depends on preparation order or catalog state.
Construction validates the request's structure without installing a declaration
or executing a conditional skip. The target,
ordered key parts, index kind and options remain available without SQL text or
a parser AST. `InvalidIndexName` identifies an explicit secondary-index name
that is empty, ends in an ASCII space, or equals PRIMARY ignoring case. Primary
key labels are ignored. This describes the instruction and its condition;
Semantics does not decide whether execution will reach or report that condition.

Routine wildcard REVOKE and explicitly mismatched trigger schemas also remain
complete requests. `RevokePrivileges::hasExecutionCondition($release)` accounts
for the release-specific meaning of global ALL; modern account flags still
constrain the request's language domain. `CreateTrigger` retains its body when
the explicit trigger and table databases differ. Catalog lookup and protocol
restrictions belong to the consumer. These concrete statement types own their conditions and resolved operands.
Definition options also remain on complete statements when their values are invalid.
`ALGORITHM` and `LOCK` keep unknown names and publish `UnknownAlterChoice`.
Column, index, table and tablespace engine attributes retain their quoted `Text`
and publish `RefusedJsonAttribute` for invalid JSON. Repeated storage settings
remain ordered options with a `StorageProblem`. Their aborting `ParseFailure`
notices preserve definition-time checks, including inside an unreachable routine
branch. The same conditions are derived for directly constructed statements;
consumers do not need the input SQL to discover them.

`Settings::$fullTextParsers` supplies the available full-text parser plugins as
a list of `Name` values. An empty list means no parser plugins are available;
`null` leaves availability unspecified. `WITH PARSER` resolves names without
regard to case. A name absent from a supplied catalog produces an
`UnknownIndexParser` diagnostic and an ordered, aborting `ParseFailure` notice,
before conditions in later table options or partition expressions. The same
lookup applies to directly constructed CREATE TABLE, CREATE INDEX and ALTER
TABLE definitions. Analysis does not load or install plugins.

`Statement\Table\Catalog\SystemColumn::$dimensions` contains the type name,
character and byte lengths, numeric precision and scale, and fractional seconds
precision reported by `INFORMATION_SCHEMA.COLUMNS`. These values describe the
declared column; `SystemColumn::$domain` describes a result that reads it, which
can differ for system views. Use `dimensions` for column dimensions and
`columnType` only for the server's display text. The release catalogs retain
these dimensions from the server rather than inferring them from that text.

Statement values also retain `TRUNCATE`'s optional TABLE keyword, CALL's
parentheses, the DEALLOCATE/DROP verb of a prepared-statement release, and
RENAME's TO/AS/equals/omitted connector. CALL requires parentheses for nonempty
arguments. These choices survive publication inside stored programs as well as
immediate statements; execution consumes the same target and operands.

Maintenance and partition operations expose `binlog` as a nullable
`Statement\Server\BinlogOption`: `Local` and `NoWriteToBinlog` both suppress
binary logging, and null means the modifier was omitted. Legacy OPTIMIZE
PARTITION also retains its ignored trailing modifier in `ignoredBinlog`.
Statements that accept both TABLE and TABLES retain the selected
`Statement\Table\TableWord` in `tableWord`. Rendering uses these values, so
publication and direct construction preserve the caller's keyword choices.

Keyword calls keep their canonical `KeywordFunction` identity for typing and
execution, and an optional `KeywordAlias` for synonyms such as SCHEMA,
SYSTEM_USER, SUBSTR and GEOMCOLLECTION. `ArgumentSyntax` retains comma-separated
or FROM/FOR operands of SUBSTRING. Deferred routine calls carry the same choices;
account-position `SessionUser` accepts the USER synonyms. `ShowKeys::$listing`
and `ShowDatabases::$listing` retain INDEX/INDEXES/KEYS and DATABASES/SCHEMAS.
These choices survive publication and rendering without keeping SQL fragments.
`Statement\Server\Database\DatabaseWord` similarly retains DATABASE or SCHEMA
on CREATE, ALTER, DROP, legacy directory-name upgrades and SHOW CREATE requests.
Clock calls and column timestamp clauses retain `ClockAlias` alternatives such
as CURRENT_TIMESTAMP and LOCALTIME. `AggregateAlias` retains population-statistic
names such as STDDEV_POP and VAR_POP. Their operation identities still determine
typing and execution; ordinary calls, deferred calls and window aggregates retain
the same keyword choices during publication. Interval arithmetic retains ADDDATE
and SUBDATE in `DateArithmeticAlias`, constrained to the matching subtraction
choice. `Quantifier` keeps SOME separately from ANY; `comparesRows()` exposes
the shared row-operand constraint for analysis and execution.

`Statement\Dml\Evaluation` retains every grammatical DO item, including a
wildcard or a non-scalar projection. `hasExecutionCondition()` identifies a
fixed preparation condition, and `problem()` describes it. These requests still
render as DO statements. A consumer must resolve their operand dependencies
before reporting the fixed condition. Stored `DeferredEvaluation` instructions
retain the same operands, but prepare them only when the program reaches them.

`Statement\Type\Resolved\Settings::$explicitDefaultsForTimestamp` controls
the nullability of TIMESTAMP columns without an explicit NULL attribute. With
the setting disabled they are NOT NULL; with it enabled they are nullable.
An unknown setting (`null`) leaves the fact dependent. Default release settings
disable it in MySQL 5.6 and 5.7 and enable it from MySQL 8.0.

Resolved domains distinguish string expression bounds from reported byte lengths.
`CASE`, control functions, string extrema, variable assignments and concatenations
retain both bounds so nested
expressions do not repeatedly multiply a blob's width by its character set size.
`HEX` results also retain their character bound separately from the reported byte
width, including through nested calls. Modern releases retain the low 32 bits when
the encoded maximum wraps; legacy releases wrap the character count before scaling.
The returned field type of `NULLIF` and computed `MIN`/`MAX` strings follows
the emulated release, including MySQL 5.6 and 5.7 string sizing.
When a derived table materializes a direct `GROUP_CONCAT` result, its column type
uses `groupConcatMaxLen` and the argument character set, which can differ from
the function's directly reported type. Set operations retain their separate
release-specific string aggregation rules before materializing the result.

Window expressions retain their logical domains while parent expressions resolve.
`Materialization::windowed()` supplies the separate type needed when an execution
path stores the result. Computed temporal windows retain a textual collation;
reading a stored temporal column uses binary collation. Direct temporal control
expressions use the connection collation in MySQL 8.0 and latin1 from MySQL 8.4.
An executor applies `character_set_results` separately, so the result's byte
length does not by itself identify the expression's logical character set.

A domain also separates an expression's evaluation kind from its reported field.
For example, `NULLIF` of a DATE reports a string but still converts the date to
its numeric date value in arithmetic. `Domain::displayDecimals` overrides the
result column's decimals without changing the fractional precision used during
evaluation. Materializing that expression stores a string, so later arithmetic
uses string conversion. Fixed-scale FLOAT and DOUBLE arithmetic retains the
resolved scale and the release-specific display width. MySQL 8.0 YEAR results
can report string fields and fractional metadata while retaining integer
semantics; materialization stores them as integers. Decimal division preserves
precision growth from `divPrecisionIncrement` even after its scale reaches 30.

An unreduced scalar subquery reads a textually reported temporal result as a
string while retaining its reported fractional metadata. Set columns also
resolve from the returned field types: temporal `NULLIF` results become strings
in the release's set collation, and matching BIT fields retain BIT storage.
MySQL 5.7's streamed `UNION ALL` output retains the fractional precision of
temporal values reported as strings; its stored query fields clear that precision.
`Domain::storageField` records a separate field for storing an expression. For
example, `NULLIF` of BIT can return a BIT field while a materialized derived
table or `CREATE TABLE … SELECT` stores its result as an integer. The original
evaluation and returned field remain available to direct expression consumers.
MySQL 5.6 and 5.7 BIT extrema similarly evaluate as unsigned integers while reporting
BIT fields. Their materialized result columns retain BIT storage and bounds.
Expression resolution precedes execution paths: `Materialization::ordered()`
provides the integer domain of a BIT sort key when an ordered DISTINCT path
stores it. A path that reads a covering index can retain the original BIT domain.

For a materialized TEXT/BLOB column, `Domain::capacity` records the byte capacity
of its storage type separately from the reported bound in `length`. The `value()`
view used when resolving expressions exposes that capacity. This lets a scalar
subquery or string function read the same column type while direct projection
retains the expression's original result width.
For TEXT/BLOB set results, MySQL 5.7 and later expose the temporary column's
capacity to scalar subqueries. MySQL 5.6 keeps the expression bound until a
derived table reads the result. Nested set operations combine the original
bounds rather than the allocated capacities.
MySQL 5.7 can return an unordered `UNION ALL` directly. Its statement output
reports the combined string expression types; the query facts retain the
temporary column types needed by scalar and derived-table consumers.

### Aggregate ownership

`DISTINCT` ordering is checked against the selected expressions and the aggregates
owned by the query block. An unselected aggregate call produces
`AggregateInOrdering` with `distinct` set to `true`; an unselected input column
produces `NonGroupedColumn`. Selecting every grouping expression makes `DISTINCT`
redundant unless the block uses `ROLLUP`. A sorting subquery cannot read an outer
aggregate through its result alias.

Aggregate ownership is available through `$query->queryInformation()->aggregates`.
For `SELECT (SELECT SUM(t.a)) FROM t`, the outer block owns `SUM(t.a)` because its
argument reads only the outer table. `COUNT(*)` and constant arguments stay in the
inner block. Clause visibility also matters: an enclosing WHERE cannot own an
aggregate. Consumers should use the resolved occurrences when planning groups,
since their written nesting alone does not identify the rows to aggregate.

Inside HAVING, aggregate arguments can read the enclosing block's input columns,
including through nested queries. A selected alias without a matching input column
remains a reference to the result row. For example, `(SELECT SUM(a))` and
`(SELECT SUM(x))` differ when the outer query selects `a AS x`.
A reference to a computed result alias retains its enclosing query depth in
`AliasTarget::depth`. Consumers must retain that dependency when deciding whether
a scalar subquery can be evaluated once or must be evaluated for each outer row.
MySQL 9.1 also lets ordinary nested expressions read unselected outer input columns;
those references are checked against GROUP BY and functional dependencies.
Earlier supported releases reject those names in grouped blocks, except for
eligible derived or common tables merged into the block from MySQL 5.7 onward.
The `derivedMerge` session setting controls that exception.

### Constant JSON paths

`Statement\Call\Json\JsonValueCall::pathValue` exposes the interpreted constant
path before the statement is resolved. A `Json\Path` holds typed member, wildcard,
array-cell, array-range and recursive-descent steps. Its constructors exclude
missing or contradictory step operands. `Json\Path\Invalid` instead holds the
byte position of an invalid decoded path operand; it is an SQL condition, not
retained SQL source. The written string literal remains available for rendering.

Derivation reports `InvalidJsonDocument` or `InvalidJsonPath`, tied to the owning
call. Document types depend on the analysis context: the same column expression
can be unresolved, numeric or textual as its table declaration changes. Consumers
apply valid path steps to document values and retain their own execution state.
Semantics does not execute JSON selection or certify execution eligibility.

### Tables and views

A `CREATE VIEW` declares a view (`RelationKind::View`); every other declaration is a base table. The declared kind decides what statements on the name do, as the server decides it. `SHOW CREATE TABLE` returns `Table` and `Create Table` for a base table and the four columns of `SHOW CREATE VIEW` for a view. A statement that needs a base table (`ALTER TABLE`, `CREATE INDEX`, `CREATE TRIGGER`, `CREATE TABLE ... LIKE`, `HANDLER ... OPEN`) refuses a view, and one that needs a view (`ALTER VIEW`, `CREATE OR REPLACE VIEW`, `SHOW CREATE VIEW`, `DROP VIEW`) refuses a base table (`WrongRelationKind`). `DROP TABLE` and `TRUNCATE TABLE` do not find a view. `DROP VIEW IF EXISTS` of a base table is refused in 8.1, 8.2, 8.3, 9.0 and 9.1 only; the other releases add only a note.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql);
$table = $semantics->analyze('CREATE TABLE t (a INT)');
$view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

$semantics->analyze('SHOW CREATE TABLE v', [$table, $view])->field(1)->name?->value; // => 'Create View'
$semantics->analyze('ALTER TABLE v ADD COLUMN b INT', [$table, $view])->diagnostics()[0]->message(); // => 'v is not BASE TABLE.'
```

### Process expressions

`Kill` retains complete process expressions, including row-valued computations
that execution must refuse. Such requests expose `RefusedKillOperand` alongside
their dependency conditions; the scope and expression remain available for SQL
reconstruction. Preparation checks syntax-time conditions such as unknown system
variables; process dependencies and row-width failures are checked at execution.
Stored `DeferredKill` instructions apply these conditions when control reaches
the instruction.

### Explanation options

`ExplainVerb` retains the `EXPLAIN`, `DESCRIBE` or `DESC` command on statement,
connection and table explanations, including deferred stored instructions.
The renderer reproduces that choice without consulting SQL text.

`Explain` and `ExplainConnection` retain explicit format names and incompatible
`INTO` options. `ExplainConnection` also retains `ANALYZE`, its output variable,
and the ignored empty destination spelling of MySQL 8.2–8.3. Execution checks
these options before looking up the connection or preparing dependencies; an
option refusal does not remove the connection identifier or explained statement.
Unknown formats and incompatible options appear as typed diagnostics and ordered
`ParseFailure` notices. MySQL 8.1's `INTO` refusal follows notices from its explained
operand. These values can be constructed and executed without recovering options
from SQL. Deferred explained operands and invalid ordinary output targets retain
their existing refusal paths.

### Partition expressions

HASH, RANGE and LIST methods retain their complete expressions even when those
expressions read session state or call stored functions. The same applies to
HASH subpartitioning and retained row-valued methods. Derivation reports
`InvalidPartitionExpression` with its expression occurrence and ordered notice;
consumers can reject the request before table lookup without losing its operands.
Native-function availability remains a versioned execution check. Subquery methods
retain their existing generation restrictions.

### File input destinations

`LoadTable` retains scalar/default assignments, non-scalar assignments and
fixed-width input variables as complete requests. `hasExecutionCondition()`
identifies the operand conditions that require ordered preparation. Option
conflicts remain semantic diagnostics of the full statement. Consumers use its
published bindings to check database, file-entry and assignment dependencies;
analysis does not replace these requests with partial refusal records.

`LoadTable::columns` distinguishes an omitted
input destination list (`null`) from an explicit empty list (`[]`, rendered as
`()`); both forms load every visible column in table order. A nonempty list
retains its ordered column and user-variable destinations.

### Generated columns

A column defined with `AS (expr)`, `VIRTUAL` or `STORED`, is declared generated (`Column::$generated`), also in a copy made with `CREATE TABLE ... LIKE`; a column that `CREATE TABLE ... SELECT` takes from a query, or a view column, is not. The server computes such a column and accepts only a default value for it: a value other than `DEFAULT` or `DEFAULT(col)` written into it by `INSERT` or `REPLACE` (a row of `VALUES`, with or without a column list, `SET`, a query source), by `ON DUPLICATE KEY UPDATE` or by `UPDATE` is reported (`GeneratedColumnWrite`), whatever `IGNORE` and the SQL mode say. A query source passes the default only through a select item `DEFAULT(col)` of a single query block, so the columns of a union, of `TABLE` or of an expanded `*` are reported. `CREATE TABLE ... SELECT` reports a generated column of its column list that the query fills. `LOAD DATA` computes generated columns and ignores what it reads for them. From MySQL 8.0 on, the parser refuses `DEFAULT`, `ON UPDATE`, `AUTO_INCREMENT`, `SERIAL DEFAULT VALUE`, `COLUMN_FORMAT` and `STORAGE` on a generated column, and the type `SERIAL`, so analyzing such a definition throws `AnalysisException`.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql);
$table = $semantics->analyze('CREATE TABLE t (a INT, total INT AS (a * 2) STORED)');

$table->declarations()[0]->columns[1]->generated; // => true
$semantics->analyze('INSERT INTO t (a, total) VALUES (1, DEFAULT)', [$table])->diagnostics(); // => []
$semantics->analyze('UPDATE t SET total = 3', [$table])->diagnostics()[0]->message(); // => "The value specified for generated column 'total' in table 't' is not allowed."
```

## Declared type checks

`CREATE TABLE` columns, routine parameters and function return types share checks for numeric widths, precision and scale, and temporal fractional precision. Invalid sizes appear as `InvalidTypeSize` diagnostics; an accompanying `ParseFailure` records where the server stops reading, so an executor can preserve warning order. Parameter and return-type errors use an empty column name, as MySQL reports them. MySQL 5.6 accepts `BIT(0)`; later supported releases reject it.

Column requests retain incompatible attributes, such as `BIT SRID 1` or a clock
default whose precision differs from its column's precision. Analysis returns the
complete `CREATE TABLE` or `ALTER TABLE`, with an `InvalidColumnAttribute`
diagnostic and an ordered `ParseFailure`. Rendering preserves the type and
attributes, and direct construction derives the same condition. Legacy releases
check each completed column; modern releases check after reading the statement.

The original spelling of an unquoted `FULL` identifier produces warning 4119 in the supported MySQL 8.x and 9.x releases. Quoted names, string aliases and names lexed as parts of a qualified identifier do not produce that warning. Savepoint commands own this condition as `nameWarning`, computed from the name's explicit delimiter and selected release, including for directly constructed values. Other statement families expose ordered notices through `notices()` and their conditions through `warnings()`. Each notice identifies its owning occurrence.

Deprecated type attributes produce warnings in written order, including integer display widths, `ASCII`, `UNICODE`, `BINARY` and national character types. These checks do not establish complete validation of every type or table option.

## Stored program body structure

Stored-program names exclude empty names, trailing spaces, NUL bytes and names
longer than 64 characters. These constructor constraints apply to declarations,
calls, alterations and inspection requests. Input lowering reports the name's
typed condition before later database or body conditions. MySQL 5.6 permits a
wider operand domain for `DROP FUNCTION`; `DropProgram::legacyName()` identifies
that form, and composition checks exclude incompatible newer language features.

Complete routine, trigger and event bodies remain part of their concrete
statements when a definition-time condition prevents the server from accepting
them. This includes unbound cursors, variables and conditions, declaration-order
conflicts, missing or misplaced `RETURN`, incompatible languages, prohibited
statements and unscoped client parameters. Unreachable branches and handlers are
checked too. For example, analyzing `ALTER EVENT e DO FETCH c INTO x` returns an
`AlterEvent` containing that `FetchCursor`, with an undefined-cursor diagnostic;
rendering the statement still writes the complete request.

The same rules apply when `Construction\Publication` binds directly constructed
values. Constructors enforce operand shapes and occurrence ownership. Definition
conditions are derived after the name, parameters and body are combined, so a
function implementation can retain an SQL instruction without `RETURN` and
report why its enclosing function cannot be defined.

`CHAR` and `VARCHAR` return types with an unsupported `BINARY` attribute remain in the
complete function definition. Their condition belongs to the return type, so
execution preserves earlier parameter/type notices and stops before body notices
or body errors. Rendering still includes the return declaration and the full body.
The `TEXT` family permits `BINARY` alone and uses the database's default collation
for that return declaration, as `ENUM` and `SET` do.

`QueryRoot` retains complete reads with fixed output or input conditions, such as
tableless stars, incompatible row widths, non-scalar operands, invalid output
ordinals and repeated input names. `hasExecutionCondition()` identifies these
requests; diagnostics describe their conditions, and preparation checks earlier
dependencies before reporting the applicable error. Reconstruction retains every
clause, including output destinations and `QUALIFY`.

Scalar subqueries still exclude exact repeated input names within one fixed
database namespace. A stored query retains those names and its declaration
reports the first reached input condition. A query body can also
retain such a template inside an unused CTE; only references reached from its
owner activate that definition. Names whose equality depends on the default
database or case-comparison settings remain resolution dependencies.

A `ParseFailure` notice carries `Statement\Routine\Problem\RefusedProgramBody`
with the typed name, definer, parameter and return declarations, and the first
body condition. It preserves error precedence over later instructions without
removing their semantic values. Input occurrence ranges order parsed notices;
direct construction uses semantic dependency order. Optional
`Statement\Routine\Definition\DefinitionSource` boundaries format input
diagnostics and never supply the body's meaning. No SQL text or parser node is
retained. Database selection, SQL mode and other state remain the consumer's
responsibility.

This does not make every grammar input representable. Input operands without a
semantic value, including the existing early nested-program declaration
refusals, still raise `Construction\RejectedInput`. The retained-body checks do
not certify that a routine can execute or establish every implicit-commit DDL
and lexical-scope constraint.

## Limitations

`CAST(... AT LOCAL AS ...)` has no public expression type: every supported
MySQL release rejects this form. Lowering reports `Construction\RejectedInput`
with the typed `NotSupportedYet` problem before publishing a structure. The
failure's `aborts` field distinguishes an immediate input stop from a refusal
that still permits later grammar conditions. It is an input diagnostic, not an
execution approval flag. SQL input remains accepted by SQL Parser wherever the
grammar permits it; `Lowering\SyntaxWarnings` preserves the ordered observations
of the rejected input, including a stored-program statement's stopping boundary.

- Optimizer hints (`/*+ ... */` after `SELECT`, `INSERT`, `REPLACE`, `UPDATE` or `DELETE`) are not analyzed. The server reads them with a grammar of their own, and the parser this package uses delivers them as a comment, so from 5.7 on a statement with a hint is refused with `ImplementationGap` instead of being read without it. In MySQL 5.6 such a comment is an ordinary comment.
- Version comments (`/*!80000 ... */`) are read as the selected release reads them: the body is part of the statement when the release is at least the written version, and a comment otherwise. Rendered SQL writes that reading without the comment markers, so it is SQL for the selected release.
- Table and database names are compared exactly, as a server with `lower_case_table_names=0` (the default on Unix) compares them. A server running with 1 or 2 compares them without regard to letter case; that setting is not part of the analysis.
- Column names are compared without regard to ASCII letter case. The server also folds letters outside ASCII; two column names that differ only in the case of such a letter are one name to the server and two names here.
- The name of an unaliased select item whose text is read in `character_set_client` and holds characters outside ASCII, or is longer than 255 bytes, depends on that session state. The same applies to a string introduced with a character set other than `binary`, `utf8mb3` and `utf8mb4` that holds bytes outside ASCII, and to any string in `ucs2`, `utf16`, `utf16le` or `utf32`, whose name depends on the server's conversion table (`NameConversion`). Such a field has a null name and lists the input in `OutputSlot::$unnamed`, and a column lookup in a derived table that could only match it depends on that input, as does a name in `GROUP BY`, `HAVING` or an `ORDER BY` expression that could name the item. A bare name in `ORDER BY` that a column of the `FROM` clause has still resolves to that column, although the server would take the item first if the item had that name. A name of ASCII characters is known for every client character set that encodes ASCII as ASCII, which all of them but `swe7` do.
- The name `NAME_CONST` gives a column is derived for a string, decimal or integer number, hexadecimal or bit value and boolean name argument; other arguments are refused with `ImplementationGap`.
- For a name the context does not declare, `SHOW CREATE TABLE` has a row shape that depends on whether the name is a table or a view (`TableOrView`).
- A foreign key that references a view is not reported. The server refuses it only for a storage engine that keeps foreign keys, and the storage engine can come from the session. Writes into a view that cannot be updated are not reported either.
- A write through a view into a column that reads a generated column is not reported; the server refuses it and names the column and table of the base table. The checks of a generated column definition that need its expression or keys are not reported: a reference to a later generated column or to an `AUTO_INCREMENT` column, `ON UPDATE`/`ON DELETE` actions of a foreign key over it, and keys the server refuses on a virtual column.
- `INSERT ... VALUES ROW(...)`, which the server inserts as the rows of `INSERT ... VALUES`, is analyzed as a `VALUES` statement source: `DEFAULT` in it is reported and its values do not see the columns of the table.
- When no current database is given, results that would show its name, such as the column name of `SHOW TABLES`, depend on it.

See [Guarantees](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/docs/guarantees.md) for the limits that apply to every database.

Ordinary statement constructors reject declared sizes that exceed the fixed
bounds of every supported release, such as `DATETIME(7)`, `BIT(65)` and
`DECIMAL(66,31)`. SQL input for those ordinary statements raises `RejectedInput`
with an `InvalidTypeSize` diagnostic. Stored-program definitions instead retain
their parameter, return and local declarations, including unused branches, and
publish the same size condition with its reading boundary. Release-specific cases
such as `BIT(0)`, accepted by MySQL 5.6, remain representable and are resolved
against the selected release. These checks do not inspect a catalog or certify
whether a statement will execute successfully.

`Statement\Routine\Condition\SqlState` accepts quoted values containing five uppercase letters or digits, excluding success class `00`. Invalid PHP construction raises `InvalidConstruction`. An invalid SQL operand such as `SIGNAL SQLSTATE '00000'` instead raises `SqlSemantics\Construction\RejectedInput`; its `problem` is a typed `ProgramProblem`, and `end` is the exclusive byte offset of the operand. No operation containing that invalid value is returned.

Additional authentication factors use `Statement\Account\User\Factor`, whose cases are `Second` and `Third`. Registration and factor-change constructors accept this enum; factor changes also reject duplicates and adding the third factor before the second. Invalid SQL operands produce a typed input refusal during lowering instead of a statement that is intrinsically invalid. Account existence and installed authentication plugins remain execution-state concerns.

`Statement\Name\AccountName` excludes names outside every supported release's domain: more than 32 trimmed user characters, more than 255 trimmed host bytes, or an at sign in the host. It preserves decoded operands, including embedded NUL bytes; `username()` and `hostname()` provide their whitespace-normalized interpretation. MySQL 5.6 has limits of 16 user characters and 60 host bytes; 5.7 has limits of 32 and 60. Input lowering applies the selected release's limits before publishing accounts and definers, and reports `AccountNameFailure` through `RejectedInput`. MySQL 5.6 checks the original operands before trimming when the host is explicit. Structure composition rejects a longer name combined with features that require an incompatible older release. Account existence remains an execution-state concern.

A standalone `PARTITION` entry in the MySQL 5.6 and 5.7 grammars is rejected during input lowering. It is not a public `Statement` type. Partition clauses remain available inside table definitions and alterations; removing the client entry does not remove table partitioning. The input refusal carries a typed reason and keyword offset, without retaining SQL or parser nodes.

Table, column, index, partition, database and tablespace options retain their optional
`=` signs. Character set and collation options retain `DEFAULT` and the selected
`CHARSET`, `CHAR SET` or `CHARACTER SET` keyword. The owning CREATE TABLE,
tablespace and resource-group statements retain the comma choices between adjacent
operands; supplied comma lists must contain exactly one boolean per boundary.
Resource-group CPU ranges hold `Numeral` operands, including leading zeros.
Consumers obtain numeric indices from those operands without parsing rendered SQL.

Database encryption uses `Statement\Server\Database\Encryption::Enabled` or `Disabled`. `DatabaseEncryption` accepts this setting and an optional decoded `Text` spelling that must denote the same value. SQL lowering resolves case-insensitive Y/N operands before their first NUL. An unrecognized operand remains a `Text` in `DatabaseEncryption::$encryption`; the complete CREATE or ALTER DATABASE publishes `InvalidEncryption` and an aborting `ParseFailure`. An entire `N` operand disables encryption; a NUL suffix enables it, including after `N`, matching the server's separate validation and setting conversion. Hexadecimal and bit spellings are excluded. Database existence and storage-engine capabilities remain execution-state concerns.

Legacy INSERT statements retain their optimizer hints in the semantic head. Qualified wildcards in an INSERT column list require a legacy grammar and cannot compose with modern-only hints.

`SetItem` contains only accepted assignment roles. `SET NAMES = expression` has no public expression-assignment type: input lowering raises `RefusedNamesAssignment`, carrying the assignment and operand offsets and whether a local `names` variable is visible. The refused expression and its parser nodes are discarded. `SetNames` still represents character-set changes, and `NameAssignment` can assign a declared local variable named `names`.

## License

MIT License. See [LICENSE](LICENSE) for details.

`Statement\Account\Privilege\GrantRoles` and `RevokeRoles` accept nonempty lists of `GrantedRole` only. A static privilege such as `SELECT`, or a name with a column list, is not a role operand. SQL containing those operands raises `Construction\RejectedInput` during lowering instead of publishing an invalid role statement. Ordered input notices retain recipient-name precedence, each role-list problem, and whether parsing continues into later program statements. Diagnostic positions are integer offsets; no original SQL or parser node is retained.

When a context supplies `Settings::client`, Latin-1 identifier input is decoded
before statements are published. A `Name` constructed directly already holds
the decoded name; changing the session character set does not change that name.
SQL rendering encodes identifiers for the context's client character set. String
literal bytes remain separate from identifier encoding. UTF-8 names therefore
need no conversion; conversions for other client character sets are not covered
by the Latin-1 rule.

Structural publication checks names in the model's canonical representation. It
must not reject a valid decoded name merely because the current client character
set cannot spell it. `toString()` reports `UnrepresentableName` if
rendering would replace identifier characters. This does not prevent direct
structure consumers from resolving or executing the same object. Result labels
and origin metadata follow the session's result character set independently.

Spatial index declarations have exactly one key part in ordinary CREATE INDEX,
ALTER TABLE and CREATE TABLE values. Complete stored instructions can retain a
multi-part declaration with a `SpatialKeyArity` diagnostic, so a skipped branch
or an existing-table shortcut keeps its meaning. Binding preserves every key
operand and dependency. Repeated key columns use `DuplicateKeyColumn`, separate
from duplicate table-column declarations; consumers can preserve their different
validation phases without recovering source SQL.

Ordinary primary, full-text and spatial keys also exclude functional operands.
A complete stored instruction retains this combination with `FunctionalIndexKind`,
which owns the exact functional operand and the incompatible index kind. Index
names, other key parts and their dependencies remain available to consumers;
no SQL string or parser node is retained for later preparation.

An ordinary functional key expression also excludes a bare column reference,
including one wrapped in parentheses. Use a column key for that role. The
deferred key-part type can retain the rejected functional spelling inside a
stored or conditional definition: `CREATE TABLE IF NOT EXISTS` can leave that
definition unused. Its operand remains available for dependency resolution
when the definition is reached.
