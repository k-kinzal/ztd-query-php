# Rendering

`Operation::toString()` answers SQL rendered from the statement structure. It never answers the analyzed text, and the model keeps no copy of it. The text is produced once, when the operation is constructed, and is checked before the operation is returned.

## How the text is produced

Each structure class writes itself to a typed, temporary output, clause by clause in grammar order. It can write only four kinds of pieces:

| Piece | Content |
|-------|---------|
| Keyword | One fixed upper-case keyword. |
| Symbol | Punctuation or an operator made of symbol characters. |
| Name | A decoded name, spelled by the name codec of the database for its position (column, relation, qualifier, alias, routine, label). |
| Literal | The spelling a literal or parameter class computes from its exact value. |

There is no piece for a free SQL fragment, so nothing of the source text, and no expression assembled as a string, can reach the output. The pieces are joined with one space, except before `,` `)` `]` `;` `.` and after `(` `[` `.`, and where a class requests no space. The writer does not reorder, optimize, drop or add clauses, replace an alias by its expression, or re-associate operators; parentheses that group an expression are part of the structure and are written back.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze("select a,b from t where (a+1)*2 > b -- comment\n order by a")->toString(); // => 'SELECT a, b FROM t WHERE (a + 1) * 2 > b ORDER BY a'
$semantics->analyze('SELECT 1 + (2 * 3)')->toString(); // => 'SELECT 1 + (2 * 3)'
```

The result is deterministic: the same structure always gives the same text. It is not a formatter; use [SQL Formatter](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-formatter/README.md) for layout.

Only an operation has `toString()`. A part of a structure, such as an expression or a correlated subquery, is not a statement root, and its rendering would not be a safe SQL fragment for every position, so it is not offered.

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
(new Semantics(Sqlite::Sqlite))->analyze('SELECT [weird name], `select` FROM t')->toString(); // => 'SELECT `weird name`, `select` FROM t'
```

### Literals

Literals hold their exact value: digits as strings, decoded text, hexadecimal digits. They are never converted through a PHP float. A literal is written in a spelling of that value that the database reads back as the same value.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(Sqlite::Sqlite))->analyze("SELECT 1_000, 0X1f, 1.50, X'ab', 'it''s'")->toString(); // => "SELECT 1000, 0x1F, 1.50, x'AB', 'it''s'"
(new Semantics(PostgreSql::PostgreSql))->analyze("SELECT \$\$x\$\$, U&'d\\0061t'")->toString(); // => "SELECT 'x', 'dat'"
```

## Checks before publication

An operation is returned only after the following checks pass. They run on every analysis and every construction, independently of PHP assertion settings. A failure throws `InvariantViolation` (or `InvalidConstruction` for an input outside the closed value domain), and the candidate is discarded; it is never returned in a degraded form.

Every construction, `new Operation(...)` and `analyze()` alike:

1. **Value audit.** Every value reachable from the structure is an enum case or an instance of a final class of the closed semantic namespaces, with initialised readonly properties only, holding no float, closure, resource or PHP reference. A node may occur at one position only.
2. **Fact completeness.** Every expression, relation and query of the structure received exactly one fact. A rule cannot silently skip a region.
3. **Structure equivalence.** The rendered text is parsed with the same grammar release and lowered by the same rules, and the result must equal the structure operand by operand: the same classes, the same scalar values and enum cases, and lists of the same length and order, recursively. This compares actual operands, not a fingerprint or text.

`analyze()` additionally checks the model against the input:

4. **Leaf embedding.** Every operand leaf lowered from the input (identifiers, literals, parameter markers, operators) must be reachable, by object identity, in the published structure. A rule cannot read an operand and then drop it.
5. **Token correspondence.** The significant tokens of the input and of the rendered text must be equal, in order. Tokens are compared by key: keywords and punctuation by their terminal, names by their decoded value, literals by their exact decoded value. Whitespace and comments are not tokens.

Checks 3 and 5 together tie the input, the structure and the rendered text to each other: the rendered text has the same structure as the model, and carries the same significant tokens as the input.

### Noise tables

Some tokens have no influence on meaning in the production they appear in: an optional `AS` before an alias, `WORK` or `TRANSACTION` after `BEGIN`, a statement terminator. Each database package lists such positions in noise tables (`Rules\Noise` in the MySQL and PostgreSQL packages, `Rules\Noise` and `Rules\DefinitionNoise` in the SQLite package), as pairs of a grammar production and a token position, each with a one-line reason and a citation of the database manual. Only these positions are skipped by the token correspondence check. A token that changes meaning must not be listed; if the writer cannot reproduce a significant token, the model is missing a distinction, and the fix belongs in the model. The tables are kept short so that every entry can be reviewed against its citation, and unit tests pin their content.

Some keywords are declared synonyms: different spellings that the database reads as the same request, such as MySQL `&&` and `AND`, or SQLite `TEMPORARY` and `TEMP`. They are compared as one key, listed in the same tables with the same kind of justification.

## What the rendered SQL does not preserve

The rendered text is SQL that requests the same thing as the input, in the spelling the writer chooses. The following are not preserved:

- whitespace and line breaks;
- comments; MySQL version comments are read as the selected release reads them, and the rendered text writes that reading without the comment markers;
- the letter case of keywords, which are written in upper case;
- optional noise words listed in the noise tables, and statement terminators;
- the spelling of declared synonyms, for example MySQL `VALUE` and `VALUES`, `CONVERT(a, CHAR)` and `CAST(a AS CHAR)`, `KEY` and `INDEX`; SQLite `TEMPORARY` and `TEMP`;
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

Where a spelling carries meaning, the model keeps the distinction as a bounded value and renders it. In SQLite, for example, `=` and `==` are kept apart, `<>` and `!=` too, and a double-quoted word stays a double-quoted word, because SQLite reads it as a string when no column has that name; the bare words `TRUE` and `FALSE` stay boolean words for the same reason.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT a FROM t WHERE a == 1 AND a != 2')->toString(); // => 'SELECT a FROM t WHERE a == 1 AND a != 2'
$semantics->analyze('select "a" from t')->toString(); // => 'SELECT "a" FROM t'
```

The rendered text targets the selected release and profile. It is not translated to another release or database, and text rendered under one MySQL mode is meant to be read under the same mode.
