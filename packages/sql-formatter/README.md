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
SELECT id, name FROM users WHERE active = 1 AND age >= 18 ORDER BY name;
```

Removes optional layout line breaks. Line comments and newlines inside literals
or quoted identifiers are preserved, so output is not necessarily one physical
line. Token boundaries retain any whitespace that affects lexical interpretation.

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
composer fuzz
```

The package uses the monorepo's PHP-AI-Toolkit reporter and executable PHPDoc
examples, PHPStan at maximum level, PHP-CS-Fixer, PHPCompatibility, LOC and tree
guards, Deptrac, ParaTest, and PHPBench. CI runs tests on PHP 8.1 through 8.5 and
mutation testing with Infection.

PHP-Fuzzer targets under [`fuzz/`](fuzz/) feed the formatter raw bytes and, per
database and layout preset, statements sql-faker generates whose original and
formatted texts must draw the same answer from MySQL, PostgreSQL, and SQLite.
`composer fuzz` runs a smoke set, the equivalence targets replay sql-faker's seed
corpora, and a nightly workflow runs every target. See [`fuzz/README.md`](fuzz/README.md).

## License

MIT. See [LICENSE](LICENSE).
