# Faker interface

SQL Faker exposes SQL generation as [FakerPHP](https://fakerphp.org/) provider methods. Use this interface to generate statements, fragments, and lexical values alongside other Faker data. For custom constraints, pass a [generation plan](plan.md) to the provider’s `generate()` method or use the [SqlGenerator interface](generator.md).

## Common

### Registration and seeding

```php
use Faker\Factory;
use SqlFaker\MySqlProvider;
use SqlFaker\MySql\StatementType;

$faker = Factory::create();
$provider = new MySqlProvider($faker, 'mysql-8.4.7');
$faker->addProvider($provider);
$faker->seed(12345);

$sql = $faker->sql(StatementType::Select, maxDepth: 6);
$expression = $faker->expr(maxDepth: 3);
$identifier = $provider->quotedIdentifier(1, 12);
```

Every provider takes `(Faker\Generator $generator, ?string $version = null, ?SqlFaker\Coverage\GrammarCoverage $coverage = null)`. The optional coverage collector records exercised grammar and lexical choices. The provider constructor also registers the provider with the supplied Faker instance; the explicit `addProvider()` call follows the usual Faker registration style but is optional here. Provider methods can be called directly, as shown above, for an explicitly typed PHP interface.

Use a separate Faker instance for each dialect: providers share method names, and Faker resolves overlapping methods to a provider according to registration order. Select a version tag from the [support table](../README.md#support-syntax). An unsupported tag raises `RuntimeException`.

Construct the provider before calling Faker's [`seed()`](https://fakerphp.org/#seeding-the-generator), which controls the random sequence. Reproduction also requires the same dependencies, grammar, arguments, and order of random calls. Generated strings may include comments or varying whitespace; an example's exact output is not a stable contract across upgrades.

### Generation plans

Every provider also exposes `generate(GenerationPlan $plan): string` and `planner(): SqlFaker\Generation\Choice\PlanBuilder`. Use `generate()` to apply custom constraints and `planner()` to compile choices for replay with the same dialect settings. See [generation plans](plan.md). Call these methods directly on the provider to keep the SQL interface explicit.

### Statements and fragments

All methods that generate SQL return `string`. Unless a different default is stated, statement and fragment methods take `int $maxDepth = PHP_INT_MAX`. This is an expansion-count threshold that favors shorter derivations once reached, not a strict nesting or length limit. See [complexity and termination](algorithm.md#complexity-and-termination).

`sql()` takes an optional dialect-specific `StatementType` enum as its first argument and `int $maxDepth = PHP_INT_MAX` as its second. The common enum cases are `Select`, `Insert`, `Update`, `Delete`, `CreateTable`, `AlterTable`, `DropTable`, and `SimpleStatement`. `StatementRule` is the canonical enum name and `StatementType` is its alias. The enum classes are separate for each dialect. Use a positional first argument when writing similar calls for multiple dialects: MySQL names it `$startRule`, while PostgreSQL and SQLite name it `$type`.

| Method | Requested syntax |
|--------|------------------|
| `selectStatement()` | SELECT family; SQLite can also select VALUES |
| `insertStatement()` | INSERT family; SQLite can also select REPLACE |
| `updateStatement()` | UPDATE |
| `deleteStatement()` | DELETE |
| `createTableStatement()` | CREATE TABLE rule; SQLite returns only the opening fragment |
| `alterTableStatement()` | ALTER TABLE rule |
| `dropTableStatement()` | DROP rule; PostgreSQL can select other object types |
| `simpleStatement()` | General statement entry point |
| `expr()` | Expression |
| `whereClause()` | WHERE clause; PostgreSQL and SQLite can return an empty string |
| `withClause()` | WITH clause; SQLite can return an empty string |
| `identifier()` | Identifier grammar rule, which can also select a permitted keyword or quoted form |
| `foreignKeyConstraint()` | Named FOREIGN KEY constraint fragment |

The following methods exist in all three providers and take `int $maxDepth = 40`. They select a particular syntax shape, without preparing the schema or checking its semantics.

| Method | Requested syntax |
|--------|------------------|
| `insertFunctionUpsertStatement()` | Upsert with a function expression in its update portion |
| `fullTextSearchStatement()` | SELECT with the dialect's full-text matching syntax |
| `temporaryTableStatement()` | CREATE TEMPORARY/TEMP TABLE |
| `viewStatement()` | CREATE VIEW |
| `generatedColumnStatement()` | CREATE TABLE with a generated column |
| `foreignKeyCascadeStatement()` | CREATE TABLE with a foreign key and ON UPDATE/DELETE CASCADE |

### Lexical values

These methods construct individual SQL values. All parameters below are integers. Length parameters refer to the generated content, excluding surrounding quotes or prefixes.

| Method | MySQL defaults | PostgreSQL defaults | SQLite defaults |
|--------|----------------|---------------------|-----------------|
| `quotedIdentifier($minLength, $maxLength)` | `1, 64` | `1, 63` | `1, 128` |
| `stringLiteral($minLength, $maxLength)` | `1, 255` | `1, 255` | `1, 255` |
| `integerLiteral($min, $max)` | `1, 2147483647` | `1, 2147483647` | `1, PHP_INT_MAX` |
| `decimalLiteral($precision, $scale)` | `10, 2` | `10, 2` | `15, 2` |

`integerLiteral()` returns a numeric string. `decimalLiteral()` uses at least one integer digit and at least two fractional digits, even when a smaller scale is supplied; its parameters are not a database `DECIMAL(p, s)` validator. Lexical helpers generate values directly and do not run the statement derivation, structural rewriting, or lexical boundary-selection pipeline. See [limitations](algorithm.md#limitations).

### Generation errors

The same exceptions apply to all three providers. `SqlFaker\Generation\Exception\GenerationException` reports an unsatisfied grammar plan or expansion budget. `SqlFaker\Generation\Exception\LexicalException` reports missing or incompatible lexical choices. Both extend `RuntimeException`. Unsupported version tags also raise `RuntimeException`, while invalid bounds or plan arguments can raise `InvalidArgumentException`. See the [generator error reference](generator.md#errors).

## MySQL

Use `SqlFaker\MySqlProvider` with `SqlFaker\MySql\StatementType`. The default version is `mysql-8.4.7`.

Without a statement type, `sql()` uses the selected grammar’s own entry point and requires non-empty output. Common statement rules also have older-version fallbacks. Some fallbacks are broader than the method name: for example, a CREATE TABLE request can use the general `create` rule. Helpers referencing syntax absent from the chosen version can fail. CTE and row-alias helpers are not available for every supported MySQL version.

### Additional statements and fragments

| Method | Default `maxDepth` | Requested syntax |
|--------|--------------------|------------------|
| `sqlWithoutEmptyRows(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX)` | `PHP_INT_MAX` | SQL with every visited `opt_values` production required to be non-empty |
| `replaceStatement()` | `PHP_INT_MAX` | REPLACE |
| `truncateStatement()` | `PHP_INT_MAX` | TRUNCATE |
| `createIndexStatement()`, `dropIndexStatement()` | `PHP_INT_MAX` | CREATE INDEX / DROP INDEX |
| `beginStatement()`, `commitStatement()`, `rollbackStatement()` | `PHP_INT_MAX` | Transaction statements |
| `loadDataStatement()` | `PHP_INT_MAX` | LOAD statement from `load_stmt`, including DATA or XML alternatives |
| `multiTableUpdateStatement()`, `multiTableDeleteStatement()` | `PHP_INT_MAX` | UPDATE / DELETE with two target table references |
| `updateJoinDerivedStatement()` | `40` | UPDATE joining a derived table |
| `insertSelectCompoundStatement()` | `40` | INSERT from a compound query using UNION ALL |
| `insertRowAliasUpsertStatement()` | `40` | INSERT with a row alias and ON DUPLICATE KEY UPDATE |
| `partitionSelectStatement()` | `40` | SELECT with a PARTITION clause |
| `simpleExpr()`, `literal()`, `predicate()` | `PHP_INT_MAX` | Simple expression, literal, or predicate |
| `orderClause()`, `limitClause()` | `PHP_INT_MAX` | ORDER BY / LIMIT fragment |
| `tableReference()`, `joinedTable()`, `tableIdent()` | `PHP_INT_MAX` | Table reference, join, or table identifier |
| `subquery()` | `PHP_INT_MAX` | Subquery |

`sqlWithoutEmptyRows()` constrains grammar productions; it does not check insert-column counts or prevent a query from returning zero rows. The common upsert helper uses ON DUPLICATE KEY UPDATE with an IF expression, and full-text generation uses MATCH ... AGAINST. Generated-column plans select STORED syntax.

### Additional lexical values

MySQL's `quotedIdentifier()` uses backticks.

| Method with default arguments | Output form |
|-------------------------------|-------------|
| `nationalStringLiteral($minLength = 1, $maxLength = 255)` | `N'...'` |
| `dollarQuotedString($minLength = 1, $maxLength = 255)` | `$$...$$` |
| `longIntegerLiteral($min = 0, $max = 2147483647)` | Integer string within the supplied range |
| `unsignedBigIntLiteral($minLength = 1, $maxLength = 20)` | Digit string with leading zeros removed; the result can be shorter than `minLength`, and length does not validate the unsigned BIGINT range |
| `floatLiteral($precision = 10, $scale = 2, $minExponent = -38, $maxExponent = 38)` | Decimal with exponent |
| `hexLiteral($minLength = 1, $maxLength = 16)` | `0x...` |
| `quotedHexLiteral($minBytes = 1, $maxBytes = 8)` | `X'...'`, two hex digits per byte |
| `binaryLiteral($minLength = 1, $maxLength = 64)` | `0b...` |
| `hostname($minParts = 1, $maxParts = 4, $maxPartLength = 63)` | Dot-separated hostname |

These helpers construct their output even if the selected grammar version does not accept that form. In particular, `dollarQuotedString()` is not a version-compatibility check.

## PostgreSQL

Use `SqlFaker\PostgreSqlProvider` with `SqlFaker\PostgreSql\StatementType`. The default version is `pg-17.2`.

Without a type, `sql()` first chooses a random enum case. The enum additionally includes `CreateTableAs` and `CreateDomain`. `simpleStatement()` uses the broad `stmt` rule. `dropTableStatement()` and `StatementType::DropTable` use `DropStmt`, which can generate DROP for objects other than tables.

### Additional statements and fragments

| Method | Default `maxDepth` | Requested syntax |
|--------|--------------------|------------------|
| `createTableAsStatement()` | `PHP_INT_MAX` | CREATE TABLE AS |
| `createDomainStatement()` | `PHP_INT_MAX` | CREATE DOMAIN |
| `truncateStatement()` | `PHP_INT_MAX` | TRUNCATE |
| `copyStatement()` | `PHP_INT_MAX` | COPY |
| `createIndexStatement()` | `PHP_INT_MAX` | CREATE INDEX |
| `transactionStatement()` | `PHP_INT_MAX` | Transaction statement from `TransactionStmt` |
| `simpleExpr()`, `literal()` | `PHP_INT_MAX` | Simple expression or constant |
| `sortClause()`, `selectLimit()` | `PHP_INT_MAX` | ORDER BY or select-limit syntax |
| `tableRef()`, `joinedTable()`, `qualifiedName()` | `PHP_INT_MAX` | Table reference, join, or qualified name |
| `subquery()` | `PHP_INT_MAX` | Parenthesized SELECT |
| `partialIndexUpsertStatement()` | `40` | ON CONFLICT DO UPDATE with an index-inference predicate |
| `domainDmlStatement()` | `40` | Random INSERT, UPDATE, or DELETE without a top-level WITH clause |
| `partitionOfStatement()` | `40` | CREATE TABLE ... PARTITION OF with range bounds |
| `tableSampleStatement()` | `40` | SELECT with TABLESAMPLE |
| `doStatement()` | `40` | DO with a string body |
| `mergeStatement()` | `40` | MERGE with DELETE, DO NOTHING, UPDATE, and INSERT branches |

`whereClause()` permits empty output. Other general statement and fragment methods request non-empty output. Full-text generation selects the `@@` operator. The common upsert helper uses ON CONFLICT DO UPDATE, and generated-column plans select STORED syntax. `domainDmlStatement()` does not create a domain or bind columns to one. `doStatement()` does not validate the procedural language inside its generated string.

### Additional lexical values

PostgreSQL's `quotedIdentifier()` uses double quotes.

| Method with default arguments | Output form |
|-------------------------------|-------------|
| `floatLiteral($precision = 10, $scale = 2, $minExponent = -307, $maxExponent = 308)` | Decimal with exponent |
| `hexLiteral($minLength = 1, $maxLength = 16)` | `X'...'` |
| `binaryLiteral($minLength = 1, $maxLength = 64)` | `B'...'` |
| `dollarQuotedString($minLength = 1, $maxLength = 255)` | `$$...$$` |
| `parameterMarker($min = 1, $max = 99)` | Positional parameter such as `$1`; no bound value is generated |

## SQLite

Use `SqlFaker\SqliteProvider` with `SqlFaker\Sqlite\StatementType`. The default version is `sqlite-3.47.2`.

Without a type, `sql()` first chooses a random enum case. `simpleStatement()` selects the general `cmd` rule. `createTableStatement()` and `StatementType::CreateTable` select `create_table`, which generates the opening CREATE TABLE and table name, without the column definition or AS SELECT portion. Use `temporaryTableStatement()` for a complete temporary-table statement, or a [constrained `cmd` plan](plan.md#sqlite) for complete general CREATE TABLE syntax.

### Additional statements and fragments

| Method | Default `maxDepth` | Requested syntax |
|--------|--------------------|------------------|
| `term()` | `PHP_INT_MAX` | Literal term |
| `orderByClause()` | `PHP_INT_MAX` | Optional ORDER BY clause |
| `limitClause()` | `PHP_INT_MAX` | Optional LIMIT clause |
| `groupByClause()` | `PHP_INT_MAX` | Optional GROUP BY clause |
| `havingClause()` | `PHP_INT_MAX` | Optional HAVING clause |
| `fullname()` | `PHP_INT_MAX` | Table name with optional database qualifier |
| `multiDmlStatement()` | `40` | Two semicolon-terminated statements, each randomly chosen from INSERT, UPDATE, and DELETE |

The optional clause methods above, `whereClause()`, and `withClause()` can return an empty string. Full-text generation uses MATCH, and the common upsert helper uses ON CONFLICT DO UPDATE. These methods do not create full-text tables, indexes, or functions. `quotedIdentifier()` uses double quotes. SQLite has no additional lexical-value methods beyond those listed under Common.
