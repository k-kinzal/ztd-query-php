# User guide

[Documentation](README.md) · [API reference](api.md) · [Supported versions](versions.md)

## Install

SQL Faker requires PHP 8.1 or later. Install it in the project that will consume
the generated SQL:

```sh
composer require --dev k-kinzal/sql-faker
```

Composer installs FakerPHP as a dependency. SQL generation does not require a
PDO connection, database server, or tables.

## Generate your first statement

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\MySqlProvider($faker);
$faker->seed(12345);

$sql = $provider->selectStatement(maxDepth: 3);
```

`$sql` is a string. Pass it to the parser or SQL-processing code you want to
exercise. Identifiers and values are generated independently of your database,
so a generated statement is not automatically executable against your schema.

## Choose a dialect and version

| Database | Provider | Default version |
| --- | --- | --- |
| MySQL | `SqlFaker\MySqlProvider` | `mysql-8.4.7` |
| PostgreSQL | `SqlFaker\PostgreSqlProvider` | `pg-17.2` |
| SQLite | `SqlFaker\SqliteProvider` | `sqlite-3.47.2` |

The second constructor argument selects an exact supported version:

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\MySqlProvider($faker, 'mysql-5.7.44');
$faker->seed(7);

$sql = $provider->insertStatement(maxDepth: 3);
```

Omit the argument or pass `null` to use the default. Unsupported version tags
throw `RuntimeException`. See [supported versions](versions.md) for the accepted
strings. A feature-specific method also requires a version supporting that syntax.

Each provider registers its formatters with the supplied Faker generator. Direct
calls such as `$provider->selectStatement()` and Faker calls such as
`$faker->selectStatement()` are both available. Use a separate Faker generator for
each dialect when calling through Faker: the providers share formatter names.

## Choose a statement

Use a named method, such as `selectStatement()` or `createTableStatement()`, or
pass a dialect-specific statement choice to `sql()`:

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\PostgreSqlProvider($faker);
$faker->seed(7);

$sql = $provider->sql(\SqlFaker\PostgreSql\StatementType::Insert, maxDepth: 3);
```

`StatementType` and `StatementRule` name the same enum in each dialect. Always use
the enum belonging to the provider's dialect. Passing `null` leaves the statement
choice to that provider. [The API reference](api.md#statement-selection) lists the
available choices and convenience methods.

## Generate fragments and tokens

Fragments let you exercise one part of a SQL parser without generating an entire
statement. Token methods let you constrain the length or numeric range of input:

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\MySqlProvider($faker);
$faker->seed(7);

$expression = $provider->expr(maxDepth: 3);
$where = $provider->whereClause(maxDepth: 3);
$identifier = $provider->quotedIdentifier(minLength: 4, maxLength: 8);
$integer = $provider->integerLiteral(min: 42, max: 42);
```

`$integer` is the string `'42'`, not a PHP integer. Other lexical methods likewise
return SQL text, including any quoting or prefixes appropriate to the dialect.
They generate new values; they do not quote an existing application value.

Fragment names differ by dialect. For example, the ORDER BY methods are
`orderClause()` for MySQL, `sortClause()` for PostgreSQL, and `orderByClause()` for
SQLite. See [fragments](api.md#fragments) and [lexical tokens](api.md#lexical-tokens).

## Control complexity

Statement and fragment methods accept a named `maxDepth` argument. Lower values
prefer simpler output; `maxDepth: 0` chooses the shortest available alternatives
immediately. Most methods default to `PHP_INT_MAX`; the scenario methods listed
in the API reference default to `40`.

Depth is not a byte limit or a promise of a particular number of nested queries.
Once it is reached, generation still has to finish the chosen statement. Begin
with a small value when generating input for a bounded test.

## Reproduce a result

Construct the provider before seeding Faker. To repeat an operation, reset the
seed and make the same call with the same arguments:

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\SqliteProvider($faker);

$faker->seed(12345);
$first = $provider->selectStatement(maxDepth: 3);
$faker->seed(12345);
$second = $provider->selectStatement(maxDepth: 3);

if ($first !== $second) {
    throw new \RuntimeException('The same seed and call should reproduce the SQL.');
}
```

Other calls using Faker consume random values too. Reproduce the same call
sequence when replaying a longer run. Keep the SQL Faker version, FakerPHP
version, and database version fixed; exact output is not promised across upgrades.

## Understand the output

Generated SQL can contain comments, whitespace, and quoted identifiers. Do not
assume a statement starts at byte zero with an uppercase keyword or ends with a
semicolon. If your test concerns syntax, inspect parsed syntax rather than a fixed
string layout.

Some fragments are optional in the database grammar. PostgreSQL's `whereClause()`
and SQLite's `whereClause()`, `orderByClause()`, `limitClause()`, `groupByClause()`,
`havingClause()`, and `withClause()` can return an empty string, including at the
shortest depth.

Generation follows a database grammar and checks lexical consistency. Whether a
statement can execute also depends on tables, columns, functions, privileges, and
server configuration. For example, generating an INSERT does not create its target
table. See [errors](api.md#errors) for failures that can occur during generation.
