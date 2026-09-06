# API reference

[Documentation](README.md) · [User guide](usage.md) · [Supported versions](versions.md)

## Providers

Use one of these classes for the SQL dialect you need:

- `SqlFaker\MySqlProvider`
- `SqlFaker\PostgreSqlProvider`
- `SqlFaker\SqliteProvider`

All three constructors accept `Faker\Generator $generator` and
`?string $version = null`. They register their formatters with the generator and
also support direct calls on the provider. [The user guide](usage.md) shows both
styles. SQL-generation methods return strings.

## Statement selection

MySQL exposes `sql(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX)`.
PostgreSQL and SQLite expose
`sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX)`. When using named
arguments, use `startRule` for MySQL and `type` for the other two dialects.

Each dialect has its own `StatementType` enum under `SqlFaker\MySql`,
`SqlFaker\PostgreSql`, or `SqlFaker\Sqlite`. `StatementRule` is another name for
the same enum. The following choices and convenience methods are available on
all three providers:

| Enum case | Convenience method | Output |
| --- | --- | --- |
| `Select` | `selectStatement()` | SELECT-family statement; SQLite can also produce VALUES. |
| `Insert` | `insertStatement()` | INSERT-family statement; SQLite can also produce REPLACE. |
| `Update` | `updateStatement()` | UPDATE statement. |
| `Delete` | `deleteStatement()` | DELETE statement. |
| `CreateTable` | `createTableStatement()` | CREATE TABLE statement. |
| `AlterTable` | `alterTableStatement()` | ALTER TABLE statement. |
| `DropTable` | `dropTableStatement()` | DROP TABLE statement. |
| `SimpleStatement` | `simpleStatement()` | A statement chosen from the dialect's general statement set. |

PostgreSQL also offers `CreateTableAs` / `createTableAsStatement()` and
`CreateDomain` / `createDomainStatement()`.

These methods take `int $maxDepth = PHP_INT_MAX`. Additional statement methods
with the same depth argument are:

| Provider | Methods |
| --- | --- |
| MySQL | `replaceStatement()`, `truncateStatement()`, `createIndexStatement()`, `dropIndexStatement()`, `beginStatement()`, `commitStatement()`, `rollbackStatement()`, `loadDataStatement()`, `multiTableUpdateStatement()`, `multiTableDeleteStatement()` |
| PostgreSQL | `truncateStatement()`, `copyStatement()`, `createIndexStatement()`, `transactionStatement()` |

`transactionStatement()` chooses among PostgreSQL transaction commands, including
BEGIN, COMMIT, ROLLBACK, and other commands such as SAVEPOINT.
MySQL's multi-table UPDATE and DELETE methods require two targets.

MySQL also provides
`sqlWithoutEmptyRows(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX)`
when generated row-value lists must be non-empty.

## Fragments

Each method below accepts `int $maxDepth = PHP_INT_MAX` and returns a SQL string.
A dash means there is no dedicated method for that fragment on the provider.

| Fragment | MySQL | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| Expression | `expr()` | `expr()` | `expr()` |
| Simple expression | `simpleExpr()` | `simpleExpr()` | `term()` |
| Literal | `literal()` | `literal()` | `term()` |
| Predicate | `predicate()` | — | — |
| WHERE | `whereClause()` | `whereClause()` | `whereClause()` |
| ORDER BY | `orderClause()` | `sortClause()` | `orderByClause()` |
| LIMIT | `limitClause()` | `selectLimit()` | `limitClause()` |
| GROUP BY | — | — | `groupByClause()` |
| HAVING | — | — | `havingClause()` |
| Table reference | `tableReference()` | `tableRef()` | — |
| Joined table | `joinedTable()` | `joinedTable()` | — |
| Table name | `tableIdent()` | `qualifiedName()` | `fullname()` |
| Subquery | `subquery()` | `subquery()` | — |
| WITH | `withClause()` | `withClause()` | `withClause()` |
| Named foreign-key constraint | `foreignKeyConstraint()` | `foreignKeyConstraint()` | `foreignKeyConstraint()` |
| Identifier | `identifier()` | `identifier()` | `identifier()` |

PostgreSQL's `selectLimit()` can include LIMIT, OFFSET, or FETCH syntax. Optional
clauses can return empty strings; see [output behavior](usage.md#understand-the-output).
A generated identifier may be quoted or accompanied by whitespace and comments.

## Lexical tokens

Token methods accept length or numeric bounds rather than `maxDepth`. All return
strings, including numeric literals. Quoted-token lengths refer to the generated
contents, excluding quote characters and prefixes.

| Method | MySQL defaults | PostgreSQL defaults | SQLite defaults |
| --- | --- | --- | --- |
| `quotedIdentifier(minLength, maxLength)` | `1, 64` | `1, 63` | `1, 128` |
| `stringLiteral(minLength, maxLength)` | `1, 255` | `1, 255` | `1, 255` |
| `integerLiteral(min, max)` | `1, 2147483647` | `1, 2147483647` | `1, PHP_INT_MAX` |
| `decimalLiteral(precision, scale)` | `10, 2` | `10, 2` | `15, 2` |
| `floatLiteral(precision, scale, minExponent, maxExponent)` | `10, 2, -38, 38` | `10, 2, -307, 308` | — |
| `hexLiteral(minLength, maxLength)` | `1, 16` | `1, 16` | — |
| `binaryLiteral(minLength, maxLength)` | `1, 64` | `1, 64` | — |
| `dollarQuotedString(minLength, maxLength)` | `1, 255` | `1, 255` | — |

All of these arguments are integers. Use positive, ordered length bounds and
ordered numeric bounds. `precision` controls the total digit budget and `scale`
the fractional digits; use a precision greater than the scale. Leading zeros can
be removed from numeric output, so a digit budget is not a fixed output length.

MySQL quoted identifiers use backticks; PostgreSQL and SQLite use double quotes.
`stringLiteral()` includes single quotes. MySQL's `hexLiteral()` and
`binaryLiteral()` use `0x` and `0b`; PostgreSQL's equivalents use `X'...'` and
`B'...'`. Dollar-quoted strings use `$$...$$` and require a database version
supporting that syntax.

Additional token methods are:

| Provider | Method and defaults | Output |
| --- | --- | --- |
| MySQL | `nationalStringLiteral(minLength: 1, maxLength: 255)` | `N'...'` |
| MySQL | `longIntegerLiteral(min: 0, max: 2147483647)` | Decimal integer text. |
| MySQL | `unsignedBigIntLiteral(minLength: 1, maxLength: 20)` | Unsigned integer text; leading zeros are removed, possibly shortening the result. |
| MySQL | `quotedHexLiteral(minBytes: 1, maxBytes: 8)` | `X'...'`, with two hex digits per byte. |
| MySQL | `hostname(minParts: 1, maxParts: 4, maxPartLength: 63)` | Dot-separated hostname parts. |
| PostgreSQL | `parameterMarker(min: 1, max: 99)` | A positional parameter such as `$1`. |

For an exact numeric value, set both bounds to the same number:

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\MySqlProvider($faker);

$value = $provider->integerLiteral(min: 42, max: 42);
if ($value !== '42') {
    throw new \RuntimeException('Expected SQL integer text.');
}
```

## Scenario methods

These methods generate SQL with a particular feature present. They all take
`int $maxDepth = 40`. They generate SQL text; they do not create the corresponding
tables, indexes, or other database objects.

| Method | Available providers | Feature |
| --- | --- | --- |
| `insertFunctionUpsertStatement()` | All three | An upsert whose update expression calls a function. |
| `fullTextSearchStatement()` | All three | The dialect's full-text search syntax. |
| `temporaryTableStatement()` | All three | A temporary table declaration. |
| `viewStatement()` | All three | A view declaration. |
| `generatedColumnStatement()` | All three | A table with a generated column. |
| `foreignKeyCascadeStatement()` | All three | A foreign key with cascading update and delete actions. |
| `updateJoinDerivedStatement()` | MySQL | An UPDATE joined to a derived table. |
| `insertSelectCompoundStatement()` | MySQL | INSERT from a UNION ALL query. |
| `insertRowAliasUpsertStatement()` | MySQL | An upsert using an inserted-row alias. |
| `partitionSelectStatement()` | MySQL | SELECT from a named partition. |
| `partialIndexUpsertStatement()` | PostgreSQL | An upsert with a partial-index predicate. |
| `domainDmlStatement()` | PostgreSQL | An INSERT, UPDATE, or DELETE for testing domain-related behavior. |
| `partitionOfStatement()` | PostgreSQL | A table partition declaration. |
| `tableSampleStatement()` | PostgreSQL | A TABLESAMPLE query. |
| `doStatement()` | PostgreSQL | A DO statement. |
| `mergeStatement()` | PostgreSQL | A MERGE statement. |
| `multiDmlStatement()` | SQLite | Two semicolon-terminated DML statements. |

## Errors

All three exception classes below extend `RuntimeException`:

| Exception | Meaning |
| --- | --- |
| `SqlFaker\Grammar\GenerationException` | The requested SQL could not be generated, for example because its grammar constraints could not be satisfied. |
| `SqlFaker\Grammar\LexicalException` | Tokens could not be represented consistently as SQL text. |
| `SqlFaker\Grammar\LexicalCatalogException` | The selected version's lexical data is malformed or inconsistent. |

A provider can also throw `RuntimeException` when a version is unsupported or its
data cannot be loaded. Capture the dialect, version, seed, method, and arguments
when reporting a generation failure. Preserve the exception message for diagnosis.

```php
require 'vendor/autoload.php';

try {
    $provider = new \SqlFaker\MySqlProvider(\Faker\Factory::create(), 'missing-version');
} catch (\RuntimeException $error) {
    $message = $error->getMessage();
}
```

Depending on the token method and arguments, invalid bounds can also cause an
`InvalidArgumentException`. Use the parameter names and bounds documented above.
