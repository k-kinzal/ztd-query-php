# Faker interface

SQL Faker adds SQL-generation methods to FakerPHP. Choose a provider for your database, then call its methods through Faker or directly on the provider.

## Common

### Register a provider

Create a Faker instance and register a provider. The following Common examples use this MySQL provider:

```php
use Faker\Factory;
use SqlFaker\MySqlProvider;
use SqlFaker\MySql\StatementType;

require 'vendor/autoload.php';

$faker = Factory::create();
$provider = new MySqlProvider($faker, 'mysql-8.4.7');
$faker->addProvider($provider);
$faker->seed(12345);
```

The provider constructor also registers itself, so the explicit `addProvider()` call is optional. The second constructor argument selects a version; omit it to use the default. Use separate Faker instances for different dialects because their method names overlap.

### Generate statements

Use a named method to select a statement family. Every call returns SQL text.

```php
$select = $faker->selectStatement(maxDepth: 6);
$insert = $faker->insertStatement(maxDepth: 6);
$update = $faker->updateStatement(maxDepth: 6);
$delete = $faker->deleteStatement(maxDepth: 6);
```

Pass the dialect's `StatementType` to `sql()` when the statement choice is stored in a variable. `StatementRule` is another name for the same enum. Calling the provider directly uses the same generation behavior.

```php
$type = StatementType::Select;
$sql = $faker->sql($type, maxDepth: 6);
$direct = $provider->sql($type, maxDepth: 6);
$any = $faker->sql(maxDepth: 6);
```

All three providers also offer `createTableStatement()`, `alterTableStatement()`, `dropTableStatement()`, and `simpleStatement()`. SQLite's CREATE TABLE helper selects an opening fragment; see the SQLite example below for a complete definition.

### Generate fragments

Generate an expression or clause when you need input for only part of a SQL statement:

```php
$expression = $provider->expr(maxDepth: 3);
$where = $provider->whereClause(maxDepth: 3);
$with = $provider->withClause(maxDepth: 3);
$name = $provider->identifier(maxDepth: 3);
```

These are SQL fragments, including any keywords or quoting selected by the grammar. Optional clause methods can return an empty string: PostgreSQL permits this for `whereClause()`, and SQLite also permits it for its optional ordering, limit, grouping, HAVING, and WITH clauses.

### Generate identifiers and values

Use lexical methods to specify lengths and numeric ranges. They return strings, including quotes or prefixes where appropriate.

```php
$identifier = $provider->quotedIdentifier(minLength: 4, maxLength: 12);
$string = $provider->stringLiteral(minLength: 1, maxLength: 20);
$integer = $provider->integerLiteral(min: 42, max: 42);
$decimal = $provider->decimalLiteral(precision: 6, scale: 2);
```

`$integer` is the SQL text `'42'`. Lengths exclude surrounding quotes and prefixes. `decimalLiteral()` emits at least one integer digit and two fractional digits; its parameters control generation rather than validating a database column definition.

Quoted identifiers use backticks for MySQL and double quotes for PostgreSQL and SQLite. These methods generate new values; they do not escape an existing application value.

### Control complexity

Pass `maxDepth` to statement and fragment methods to favor shorter SQL:

```php
$short = $provider->selectStatement(maxDepth: 1);
$varied = $provider->selectStatement(maxDepth: 6);
```

`maxDepth` counts grammar expansions before shorter completions are preferred. It does not set an exact nesting level or output length. Most methods default to `PHP_INT_MAX`; feature-specific methods such as `viewStatement()` and `insertFunctionUpsertStatement()` default to `40`.

### Repeat a result

Construct the provider before setting the seed. Reset the seed and repeat the same calls to reproduce their output with the same SQL Faker, FakerPHP, and database versions:

```php
$faker->seed(12345);
$first = $provider->selectStatement(maxDepth: 6);

$faker->seed(12345);
$second = $provider->selectStatement(maxDepth: 6);

$sameSql = $first === $second;
```

Other Faker calls consume random values too, so preserve their order when replaying a longer sequence.

### Use a generation plan

Call `generate()` directly on the provider to use a [generation plan](plan.md):

```php
$plan = \SqlFaker\Generation\Plan\GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(6)
    ->withExpansionBudget(100);

$sql = $provider->generate($plan);
```

For replayable production and lexical choices, use the provider's `planner()` as shown in [Compile a plan with callbacks](plan.md#compile-a-plan-with-callbacks).

## MySQL

### Select a version

Use an exact tag from the README's [version table](../README.md#mysql). The default is `mysql-8.4.7`.

```php
$mysqlFaker = \Faker\Factory::create();
$mysql = new \SqlFaker\MySqlProvider($mysqlFaker, 'mysql-8.4.7');
$mysqlFaker->seed(7);

$sql = $mysql->sql(\SqlFaker\MySql\StatementType::Insert, maxDepth: 6);
```

The first argument to MySQL's `sql()` is named `startRule`. With no type, it starts from the selected grammar's entry point. Explicit statement rules have aliases for older versions; some older aliases cover a broader statement family.

### Generate MySQL fragments and literals

```php
$order = $mysql->orderClause(maxDepth: 3);
$limit = $mysql->limitClause(maxDepth: 3);
$table = $mysql->tableIdent(maxDepth: 3);
$predicate = $mysql->predicate(maxDepth: 3);

$national = $mysql->nationalStringLiteral(minLength: 1, maxLength: 12);
$hex = $mysql->quotedHexLiteral(minBytes: 2, maxBytes: 4);
$bits = $mysql->binaryLiteral(minLength: 4, maxLength: 8);
```

The literals include their SQL notation: `N'...'`, `X'...'`, and `0b...`. Other MySQL lexical methods include `hexLiteral()`, `floatLiteral()`, `longIntegerLiteral()`, `unsignedBigIntLiteral()`, `dollarQuotedString()`, and `hostname()`.

### Require non-empty row values

Use `sqlWithoutEmptyRows()` when every generated row-value list must contain a value:

```php
$sql = $mysql->sqlWithoutEmptyRows(
    \SqlFaker\MySql\StatementType::Insert,
    maxDepth: 6,
);
```

This concerns SQL row-value syntax, not whether executing a query returns rows.

### Generate a particular MySQL feature

```php
$temporary = $mysql->temporaryTableStatement(maxDepth: 6);
$fullText = $mysql->fullTextSearchStatement(maxDepth: 6);
```

`temporaryTableStatement()` selects a temporary table declaration. `fullTextSearchStatement()` selects MATCH ... AGAINST syntax. Other feature methods include `multiTableUpdateStatement()`, `multiTableDeleteStatement()`, `insertRowAliasUpsertStatement()`, and `partitionSelectStatement()`.

## PostgreSQL

### Generate a PostgreSQL statement

The default version is `pg-17.2`. PostgreSQL names the first `sql()` argument `type`; omitting it randomly selects an enum case.

```php
$pgFaker = \Faker\Factory::create();
$postgres = new \SqlFaker\PostgreSqlProvider($pgFaker, 'pg-17.2');
$pgFaker->seed(7);

$sql = $postgres->sql(type: \SqlFaker\PostgreSql\StatementType::Select, maxDepth: 6);
$createAs = $postgres->createTableAsStatement(maxDepth: 6);
```

PostgreSQL additionally exposes `CreateTableAs` and `CreateDomain` enum cases. Its `DropTable` case uses the broader `DropStmt` grammar rule, which can select other object types.

### Generate PostgreSQL fragments and literals

```php
$order = $postgres->sortClause(maxDepth: 3);
$limit = $postgres->selectLimit(maxDepth: 3);
$table = $postgres->qualifiedName(maxDepth: 3);

$body = $postgres->dollarQuotedString(minLength: 4, maxLength: 12);
$parameter = $postgres->parameterMarker(min: 1, max: 1);
$bits = $postgres->binaryLiteral(minLength: 4, maxLength: 8);
```

`selectLimit()` can produce LIMIT, OFFSET, or FETCH syntax. `$body` includes dollar quotes, `$parameter` is the text `$1`, and `$bits` uses `B'...'` notation. Parameter generation does not bind a value.

### Generate a particular PostgreSQL feature

```php
$copy = $postgres->copyStatement(maxDepth: 6);
$sample = $postgres->tableSampleStatement(maxDepth: 6);
```

These select COPY and TABLESAMPLE syntax. Other PostgreSQL feature methods include `mergeStatement()`, `partialIndexUpsertStatement()`, `partitionOfStatement()`, and `doStatement()`.

## SQLite

### Generate an SQLite statement

The default version is `sqlite-3.47.2`. SQLite names the first `sql()` argument `type`; omitting it randomly selects an enum case.

```php
$sqliteFaker = \Faker\Factory::create();
$sqlite = new \SqlFaker\SqliteProvider($sqliteFaker, 'sqlite-3.47.2');
$sqliteFaker->seed(7);

$sql = $sqlite->sql(type: \SqlFaker\Sqlite\StatementType::Select, maxDepth: 6);
```

The SELECT family also includes VALUES, and the INSERT family can include REPLACE.

### Generate optional clauses

```php
$order = $sqlite->orderByClause(maxDepth: 3);
$group = $sqlite->groupByClause(maxDepth: 3);
$having = $sqlite->havingClause(maxDepth: 3);
$limit = $sqlite->limitClause(maxDepth: 3);
```

Each result can be an empty string because the corresponding clause is optional.

### Generate a complete table declaration

`createTableStatement()` selects the opening CREATE TABLE fragment. Use `temporaryTableStatement()` for a complete temporary-table declaration, or use the [complete CREATE TABLE plan](plan.md#sqlite) for a general table declaration.

```php
$temporary = $sqlite->temporaryTableStatement(maxDepth: 6);
```

### Generate two DML statements

```php
$statements = $sqlite->multiDmlStatement(maxDepth: 6);
```

This returns two semicolon-terminated statements, each selected from INSERT, UPDATE, and DELETE. To choose each statement type explicitly, use the [SQLite generation plan](plan.md#sqlite).
