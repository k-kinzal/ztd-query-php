# Rendering

`AnalyzedStatement::toString()` reconstructs SQL from the concrete statement and its operands. The result keeps no original parser tree or SQL-string cache. Analysis and direct semantic construction do not invoke the renderer or parse its output. Result-column spellings that affect column names are described under [spelled regions](#spelled-regions).

## How the text is produced

Each structure class writes itself to a typed, temporary output, clause by clause in grammar order. It can write only four kinds of pieces:

| Piece | Content |
|-------|---------|
| Keyword | One fixed upper-case keyword. |
| Symbol | Punctuation or an operator made of symbol characters. |
| Name | A decoded name, spelled by the name codec of the database for its position (column, relation, qualifier, alias, routine, label). |
| Literal | The spelling a literal or parameter class computes from its exact value. |

There is no piece for a free SQL fragment, so no expression assembled as a string can reach the output, and the source text reaches it only as the checked spelling of a spelled region. The pieces are joined with one space, except before `,` `)` `]` `;` `.` and after `(` `[` `.`, and where a class requests no space. The writer does not reorder, optimize, drop or add clauses, replace an alias by its expression, or re-associate operators; parentheses that group an expression are part of the structure and are written back.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze("select a,b from t where (a+1)*2 > b -- comment\n order by a")->toString(); // => 'SELECT a, b FROM t WHERE (a + 1) * 2 > b ORDER BY a'
$semantics->analyze('SELECT 1 + (2 * 3)')->toString(); // => 'SELECT 1 + (2 * 3)'
```

The result is deterministic: the same structure always gives the same text. It is not a formatter; use [SQL Formatter](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-formatter/README.md) for layout.

Standalone analyzed statement values implement `toString()`. A part of a structure, such as an expression or a correlated subquery, is not a statement root, and its rendering would not be a safe SQL fragment for every position, so it is not offered.

### Names

Names are written bare when the database reads the bare spelling as the same name at that position, and quoted otherwise. A name is never quoted blindly: the quoting rules differ per database.

| Database | Quoted with | Notes |
|----------|-------------|-------|
| MySQL | backticks | A name that is a keyword, function name or introducer of the release is quoted. Backticks are identifiers under every `sql_mode`, so a double-quoted identifier read under `ANSI_QUOTES` is rendered with backticks. |
| PostgreSQL | double quotes | A name with upper-case letters is quoted, because an unquoted name would be folded to lower case. |
| SQLite | backticks | Backticks, unlike double quotes, never fall back to a string literal. |

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(PostgreSql::PostgreSql))->analyze('SELECT "Name", "name", Name FROM "Users"')->toString(); // => 'SELECT "Name", name, name FROM "Users"'
(new Semantics(Sqlite::Sqlite))->analyze('SELECT a AS [weird name] FROM `select`')->toString(); // => 'SELECT a AS `weird name` FROM `select`'
```

### Literals

Literals hold their exact value: digits as strings, decoded text, hexadecimal digits. They are never converted through a PHP float. A literal is written in a spelling of that value that the database reads back as the same value. MySQL `RadixLiteral`, `Text` and `Numeral` also retain their `RadixNotation`: quoted or prefixed hexadecimal and bit forms, including the letter case of the quoted prefix. The digits and leading zeroes remain separate from this notation.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(Sqlite::Sqlite))->analyze("VALUES (1_000, 0X1f, 1.50, X'ab', 'it''s')")->toString(); // => "VALUES (1000, 0x1F, 1.50, x'AB', 'it''s')"
(new Semantics(PostgreSql::PostgreSql))->analyze("SELECT \$\$x\$\$, U&'d\\0061t'")->toString(); // => "SELECT 'x', 'dat'"
```

## Checks before publication

Construction validates the admitted value graph and resolves every expression, relation and query occurrence. Public operands are readonly; local binding slots are completed once. Constructor violations are reported as `InvalidConstruction`, and missing semantic implementations remain failures rather than SQL outcomes.

`analyze()` also checks that every input leaf retained by lowering is reachable in the semantic structure. This check runs against the temporary lowering result. Neither the parser tree nor the temporary source map becomes part of the published statement.

Reconstruction is checked independently: SQL Faker generates input, SQL Semantics analyzes and renders it, and PHPFuzzer compares both strings after SQL Formatter's Compact formatting. Generated semantic values are separately passed directly to MemoryDB through Eris properties. The real-MySQL differential target compares execution replies. These are searches for counterexamples, not runtime reparsing gates or proofs of agreement for every SQL input.

### Noise tables

Some tokens have no influence on meaning in the production they appear in: `WORK` or `TRANSACTION` after `BEGIN`, a statement terminator, in PostgreSQL an optional `AS` before an alias. Each database package lists such positions in noise tables (the classes of the `Rules\Noise` namespace in the MySQL and PostgreSQL packages, `Rules\Noise` and `Rules\DefinitionNoise` in the SQLite package), as pairs of a grammar production and a token position, each with a one-line reason and a citation of the database manual. The token-correspondence utility uses these entries when comparing grammar positions. The Compact reconstruction property does not discard differences through these tables. A token that changes meaning must not be listed; if the writer cannot reproduce a significant token, the model is missing a distinction, and the fix belongs in the model. The tables are kept short so that every entry can be reviewed against its citation, and unit tests pin their content.

Some keywords are declared synonyms: different spellings that the database reads as the same request, such as MySQL `&&` and `AND`, or SQLite `TEMPORARY` and `TEMP`. They are compared as one key, listed in the same tables with the same kind of justification.

MySQL index definitions, index hints, cache-index lists and index alterations retain `KEY` or `INDEX` as `IndexWord`. An optional omitted keyword remains absent. `IndexDefinition::$keyword` holds this enum or `null`, rather than a presence boolean. MySQL character type attributes and `SetCharacterSet` retain `CHARSET`, `CHARACTER SET` and `CHAR SET` through `CharsetForm`. The `BINARY` character-set name retains its bare form separately from a quoted `binary` identifier.

## What the rendered SQL does not preserve

The rendered text is SQL that requests the same thing as the input, in the spelling the writer chooses. Outside [spelled regions](#spelled-regions), the following are not preserved:

- whitespace and line breaks;
- comments; MySQL version comments are read as the selected release reads them, and the rendered text writes that reading without the comment markers;
- the letter case of keywords, which are written in upper case;
- optional noise words listed in the noise tables, and statement terminators;
- the spelling of declared synonyms, for example MySQL `VALUE` and `VALUES`, `CONVERT(a, CHAR)` and `CAST(a AS CHAR)`; SQLite `TEMPORARY` and `TEMP`;
- the spelling of a name or a literal where the database reads several spellings as the same value: identifier quotes, string escapes, dollar quoting and Unicode escapes, digit separators, the case of hexadecimal digits.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(MySql::MySql))->analyze('select a from t where a = 1 && b = 2 # comment')->toString(); // => 'SELECT a FROM t WHERE a = 1 AND b = 2'
(new Semantics(MySql::MySql))->analyze('INSERT INTO t VALUE (1)')->toString(); // => 'INSERT INTO t VALUES (1)'
(new Semantics(PostgreSql::PostgreSql))->analyze('BEGIN WORK')->toString(); // => 'BEGIN'
(new Semantics(Sqlite::Sqlite))->analyze('create temporary table x (y)')->toString(); // => 'CREATE TEMP TABLE x (y)'
```

Where a spelling carries meaning, the model keeps the distinction as a bounded value and renders it. In SQLite, for example, `=` and `==` are kept apart, `<>` and `!=` too, and a double-quoted word stays a double-quoted word, because SQLite reads it as a string when no column has that name; the bare words `TRUE` and `FALSE` stay boolean words for the same reason. In SQLite and MySQL, whether `AS` introduces an alias is kept too, because the text these databases name a result column after can include it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT a FROM t WHERE a == 1 AND a != 2')->toString(); // => 'SELECT a FROM t WHERE a == 1 AND a != 2'
$semantics->analyze('select "a" from t')->toString(); // => 'SELECT "a" FROM t'
```

The rendered text targets the selected release and profile. It is not translated to another release or database, and text rendered under one MySQL mode is meant to be read under the same mode.

## Spelled regions

SQLite and MySQL name a result column without an alias after the text of its expression as it was written: `SELECT 1+1` returns a column named `1+1`, and `SELECT 1 + 1` one named `1 + 1`. There the spelling is part of what the statement returns, and rendering the expression in the canonical spelling would rename the column. The model therefore keeps the written spelling of such an expression as a layout (rule `CORE-SPELLING-001`). PostgreSQL names these columns by rules that do not depend on the spelling, such as `?column?` or the name of a called function, so the PostgreSQL package keeps no layouts.

A `SqlSemantics\Statement\Spelling\Layout` holds one `Spelled` value per token the expression renders, in order: `gap`, the whitespace and comments written before the token, and `text`, its written spelling. `trail` holds what was written after the last token; only SQLite uses it, because the SQLite name extends to the start of the next token, so a comment after the expression is part of the name. `text()` answers the text from the first to the last token. A layout is kept only where it differs from the canonical spelling, and an aliased result column keeps none; the database packages document which result columns keep one (`ResultColumn::$layout` in SQLite, `SelectExpression::$layout` in MySQL).

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$query = $semantics->analyze('select 1+1 /* sum */, 2 as two from t where a+1 > 2');
$layout = $query->columns[0]->layout;

$layout->text(); // => '1+1'
$layout->trail; // => ' /* sum */'
$query->columns[1]->layout; // => null
$query->field(0)->name?->value; // => '1+1 /* sum */'
$query->toString(); // => 'SELECT 1+1 /* sum */, 2 AS two FROM t WHERE a + 1 > 2'
```

A layout never stands for structure. The expression is still the structure: facts are derived from it, and it is what the rendering writes. A layout only chooses, for each token the expression renders, an equivalent spelling and the trivia before it; it cannot add, drop or change a token, and nothing is rendered from a layout alone. Inside a spelled region a nested layout has no effect. Apart from the trivia and the token spellings within the region, the rendered text is canonical, as described above.

Result-column constructors check the layout restrictions of their dialect, including alias conflicts and token counts. Direct semantic construction does not parse a layout back into SQL or use it to supply an omitted operand. `toString()` checks that layout spellings still request the canonical tokens under the dialect rules, rejecting changed tokens or SQL inserted into a gap. This check only validates the output: no parsed value is used to complete or replace semantic operands. Reconstruction properties independently compare analyzed input and rendered SQL.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

$context = (new Semantics(Dialect::Sqlite))->context([]);
$statement = Semantics::fromStatement($context, new Select([new ResultColumn(new IntegerLiteral('12'))]));
$statement->toString(); // => 'SELECT 12'
$statement->field(0)->name?->value; // => '12'
```

A result column constructed without a layout uses the canonical expression spelling for its name. `Semantics::fromStatement($otherContext, $statement)` copies the operands, including any result-column layout, while deriving fresh local bindings.
