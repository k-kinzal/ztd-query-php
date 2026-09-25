# SQL Formatter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

Format MySQL, PostgreSQL, and SQLite strings with **Compact**, **Expanded**,
**Tabular**, or **River** layouts. SQL Formatter uses the concrete syntax tree from
[sql-parser](../sql-parser/) to identify clauses, lists, expressions, and nested
queries. It needs no schema or database connection.

## Installation

```bash
composer require k-kinzal/sql-formatter
```

Requires PHP 8.1+ and `k-kinzal/sql-parser` (which requires `ext-zlib`). In the
monorepo, run `composer install` in this package to install its development tools
and link the sibling parser.

## Usage

```php
use SqlFormatter\Formatter;
use SqlFormatter\FormatOptions;
use SqlFormatter\Style;
use SqlParser\MySql\MySqlParser;

$formatter = new Formatter(
    new MySqlParser('mysql-8.4.7'),
    new FormatOptions(style: Style::Expanded, indentWidth: 4),
);

echo $formatter->format(
    'SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name;',
);
```

Pass `PostgreSqlParser` or `SqliteParser` for those dialects. The supplied parser
selects the grammar release and MySQL `SqlMode`; reuse it and the formatter across
calls. `MySqlParser::versions()`, `PostgreSqlParser::versions()`, and
`SqliteParser::versions()` list the available releases. All 11 currently shipped
releases are exercised by the tests.

`format(string $sql): string` accepts the same input as the supplied parser:
MySQL parses one statement, including a compound statement; PostgreSQL and SQLite
also accept semicolon-separated statements. Client commands such as MySQL
`DELIMITER` are not server SQL. The parser's lexical and syntax exceptions are
propagated for invalid input.

## Layouts

The default is `Style::Expanded` with four spaces per nested block. `indentWidth`
accepts 1 through 16. Both options are immutable.

### Compact

```sql
SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name
```

Produces a compact canonical spelling for the selected dialect and release:

- Removes ordinary comments, optional whitespace, and optional final statement
  terminators. Separators inside statement lists and stored programs, and
  terminators anchoring retained directives, remain.
- Uppercases grammar keywords while preserving identifiers, including keywords
  used as names. Quoting, literal contents, and parameter markers remain intact.
- Uses `<>` for inequality and `=` for SQLite's `==`. MySQL also normalizes
  `DISTINCTROW` to `DISTINCT`, `REGEXP` to `RLIKE`, `INTEGER` to `INT`, and
  `DECIMAL` to `DEC`.
- Omits redundant `SELECT ALL`, ascending sort directions, `INNER` and `OUTER`
  join modifiers, explicit set-operation `DISTINCT`, and optional alias `AS`.
  `UNION ALL`, `SELECT DISTINCT`, `DESC`, and SQLite's `CROSS JOIN` remain distinct.
- Removes expression parentheses only when reparsing confirms the same operand
  nesting. For example, `a+(b*c)` becomes `a+b*c`, while `a-(b-c)` retains its
  parentheses.

Optimizer hints (`/*+ ... */`), executable/version comments (`/*! ... */`), and
MariaDB-style directives (`/*M! ... */`) are retained. Executable bodies are kept
verbatim, including bodies inactive in the selected MySQL release. Newlines inside
literals, identifiers, and directives can still appear in compact output.

```php
$compact = new \SqlFormatter\Formatter(
    new \SqlParser\Sqlite\SqliteParser(),
    new \SqlFormatter\FormatOptions(\SqlFormatter\Style::Compact),
);
$compact->format('select all (a) as x from t where a != 1 order by a asc;');
// SELECT a x FROM t WHERE a<>1 ORDER BY a
```

This normal form is useful when comparing generated and serialized statements in
fuzz tests. Equality covers the documented syntax normalizations; it is not a
complete SQL equivalence decision procedure or a guarantee of the globally shortest
possible query. No schema, collation, function catalog, or data is consulted.
Identifier quoting, literal representations, join order, and algebraic expression
rewrites are deliberately outside this contract. As with other SQL reformatting,
database-generated labels for expressions without explicit aliases may change.

### Expanded

```sql
SELECT
    id,
    name
FROM
    users
WHERE
    active = 1
    AND age >= 18
ORDER BY
    name;
```

Places clause bodies below their headers and list items on separate lines.
Nested query and CASE blocks receive another indentation level.

### Tabular

```sql
SELECT   id,
         name
FROM     users
WHERE    active = 1
AND      age >= 18
ORDER BY name;
```

Left-aligns clause keywords and pads their right side to align their bodies.
Continuation items use the body column.

### River

```sql
  SELECT id,
         name
    FROM users
   WHERE active = 1
     AND age >= 18
ORDER BY name;
```

Right-aligns clause keywords, creating a vertical boundary before their bodies.
Alignment width is calculated from the headers in the enclosing block; nested
queries have independent widths. This is a layout preset, not enforcement of
all naming and query-design recommendations in a particular SQL style guide.

## Preservation

Expanded, Tabular, and River preserve source spelling:

- Keyword case, identifier spelling and quoting, literal contents, placeholders,
  parentheses, semicolons, and token order are preserved.
- Comment-bearing trivia is retained verbatim, including optimizer hints and both
  active and inactive MySQL version comments. Such trivia takes precedence over
  layout whitespace; comments are not reflowed or moved between tokens.
- Formatting does not resolve names, expand stars, add aliases, rewrite expressions,
  change dialect, or execute SQL.
- Every output is parsed again with the same parser. Its grammar nodes,
  alternatives, token kinds, and token texts must match the input, independently
  of positions and layout whitespace. A mismatch raises `FormattingException`
  instead of returning SQL whose syntax changed.
- Reformatting an already formatted string produces the same output.

Compact instead verifies a canonical syntax signature. It retains token kinds,
identifier and literal spellings, directives, and operand nesting after the
documented reductions. Every candidate parenthesis removal must parse with the
same signature; required parentheses remain. Whitespace decisions use the supplied
parser's lexer, including MySQL SQL modes and function-name adjacency rules.
Compact is also idempotent. Parseability and canonical structure are checked at
runtime; these checks do not prove arbitrary SQL transformations equivalent.

Layout rules cover SELECT clauses, CTEs, joins, set operations, CASE expressions,
window specifications, write statements, and table definitions. Other grammar constructs retain their
syntax and receive token spacing; accepting a grammar construct does not imply a
specialized multiline layout for every administrative or procedural statement.

See [the design](docs/design.md) for the dependency decision and layout pipeline,
and [verification](docs/verification.md) for the checks behind these guarantees.

## Development

```bash
composer install
composer lint
composer test:unit
composer test
composer bench:quick
```

The package uses the monorepo's PHP-AI-Toolkit reporter and executable PHPDoc
examples, PHPStan at maximum level, PHP-CS-Fixer, PHPCompatibility, LOC and tree
guards, Deptrac, ParaTest, and PHPBench. CI runs tests on PHP 8.1 through 8.5 and
mutation testing with Infection.

## License

MIT. See [LICENSE](LICENSE).
