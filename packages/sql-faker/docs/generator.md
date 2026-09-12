# SqlGenerator interface

Use `SqlFaker\Generation\SqlGenerator` to generate SQL directly from a [generation plan](plan.md). The same class supports MySQL, PostgreSQL, and SQLite; `SqlGeneratorFactory` configures it for the selected database version.

## Common

### Create a generator

Load the grammar and pass it to the matching factory with the same version tag. The following Common examples use this MySQL generator:

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

require 'vendor/autoload.php';

$faker = Factory::create();
$version = MySqlGrammar::resolveVersion('mysql-8.4.7');
$grammar = MySqlGrammar::load($version);
$generator = SqlGeneratorFactory::forMySql($faker, $grammar, $version);
$faker->seed(12345);
```

`resolveVersion()` without an argument selects the database's default version. The generator uses Faker for random choices; registering a SQL provider is not required.

### Generate a statement

Pass the desired rule to `GenerationPlan::fromRule()`, then call `generate()`:

```php
$plan = GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(6);

$sql = $generator->generate($plan);
```

The return value is SQL text. `StatementType::Insert`, `Update`, and `Delete` select other statement families. `requiringNonEmpty()` requires a non-empty result; a rule for a fragment still produces a fragment.

### Generate SQL without selecting a statement family

Use `all()` to start from the grammar's entry point:

```php
$plan = GenerationPlan::all()
    ->requiringNonEmpty()
    ->withMaxDepth(3);

$sql = $generator->generate($plan);
```

The grammar's entry point determines whether the result can contain multiple statements. To generate from a particular statement family or fragment, select its rule explicitly.

### Generate fragments and lexical values

A rule such as MySQL's `expr` produces a fragment. A lexical plan constructs one value with the supplied bounds:

```php
$expression = $generator->generate(
    GenerationPlan::fromRule('expr')->withMaxDepth(3),
);

$integer = $generator->generate(
    \SqlFaker\MySql\GenerationPlans::integerLiteral(min: 42, max: 42),
);

$string = $generator->generate(
    \SqlFaker\MySql\GenerationPlans::stringLiteral(minLength: 1, maxLength: 20),
);
```

`$integer` is the string `'42'`, and `$string` includes SQL quotes. Lexical plan factories require their bounds explicitly.

### Reuse a plan

The same plan can be used for a series of generated inputs:

```php
$selectPlan = GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(3)
    ->withExpansionBudget(100);

$queries = [];
for ($i = 0; $i < 3; ++$i) {
    $queries[] = $generator->generate($selectPlan);
}
```

Each call uses the next random choices. `withExpansionBudget()` caps the total grammar expansions for a call; `withMaxDepth()` sets the threshold after which shorter completions are preferred.

### Reproduce the same sequence

Seed Faker after constructing the generator, and reset the seed before repeating a sequence:

```php
$faker->seed(7);
$first = $generator->generate($selectPlan);

$faker->seed(7);
$second = $generator->generate($selectPlan);

$sameSql = $first === $second;
```

Keep the SQL Faker, FakerPHP, database version, and call sequence fixed. To retain explicit production and lexical choices, use `planner()` as shown in [Compile a plan with callbacks](plan.md#compile-a-plan-with-callbacks).

### Handle a generation error

Catch `GenerationException` for a rule or generation condition that cannot be satisfied, and `LexicalException` for incompatible token realization:

```php
try {
    $sql = $generator->generate(
        $selectPlan->withExpansionBudget(1),
    );
} catch (\SqlFaker\Generation\Exception\GenerationException $error) {
    $generationError = $error->getMessage();
} catch (\SqlFaker\Generation\Exception\LexicalException $error) {
    $lexicalError = $error->getMessage();
}
```

A SELECT cannot complete in one grammar expansion. Choose an expansion budget large enough to finish the requested structure. Both exception types extend `RuntimeException`.

## MySQL

### Generate for an older MySQL version

Use the version's grammar and the same version tag when creating the generator:

```php
$mysqlFaker = \Faker\Factory::create();
$mysqlVersion = \SqlFaker\MySql\Grammar\MySqlGrammar::resolveVersion('mysql-5.7.44');
$mysqlGrammar = \SqlFaker\MySql\Grammar\MySqlGrammar::load($mysqlVersion);
$mysqlGenerator = SqlGeneratorFactory::forMySql($mysqlFaker, $mysqlGrammar, $mysqlVersion);
$mysqlFaker->seed(7);

$sql = $mysqlGenerator->generate(
    GenerationPlan::fromRule(\SqlFaker\MySql\StatementType::Select->value)
        ->requiringNonEmpty()
        ->withMaxDepth(3),
);
```

The MySQL generator resolves common statement-rule aliases across releases. For example, `select_stmt` maps to `select` when needed by the older grammar. Use syntax supported by the version selected in the [README table](../README.md#mysql).

## PostgreSQL

### Generate with the PostgreSQL grammar

Use `PgGrammar` and `forPostgreSql()` with PostgreSQL's rule names:

```php
$pgFaker = \Faker\Factory::create();
$pgVersion = \SqlFaker\PostgreSql\Grammar\PgGrammar::resolveVersion('pg-17.2');
$pgGrammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load($pgVersion);
$pgGenerator = SqlGeneratorFactory::forPostgreSql($pgFaker, $pgGrammar, $pgVersion);
$pgFaker->seed(7);

$sql = $pgGenerator->generate(
    GenerationPlan::fromRule(\SqlFaker\PostgreSql\StatementType::Select->value)
        ->requiringNonEmpty()
        ->withMaxDepth(3),
);

$expression = $pgGenerator->generate(
    GenerationPlan::fromRule('a_expr')->withMaxDepth(3),
);
```

Use `fromRule('stmt')` for a general PostgreSQL statement. `all()` starts at the complete grammar entry point, which can produce multiple statements or empty output.

## SQLite

### Generate with the SQLite grammar

Use `SqliteGrammar` and `forSqlite()`. SQLite's preset plans apply `withStepBudget()`, which prefers fewer remaining expansions when the depth threshold is reached:

```php
$sqliteFaker = \Faker\Factory::create();
$sqliteVersion = \SqlFaker\Sqlite\Grammar\SqliteGrammar::resolveVersion('sqlite-3.47.2');
$sqliteGrammar = \SqlFaker\Sqlite\Grammar\SqliteGrammar::load($sqliteVersion);
$sqliteGenerator = SqlGeneratorFactory::forSqlite($sqliteFaker, $sqliteGrammar, $sqliteVersion);
$sqliteFaker->seed(7);

$sql = $sqliteGenerator->generate(
    \SqlFaker\Sqlite\GenerationPlans::statement(
        \SqlFaker\Sqlite\StatementType::Select->value,
        maxDepth: 3,
    ),
);

$where = $sqliteGenerator->generate(
    GenerationPlan::fromRule('where_opt')->withMaxDepth(1),
);
```

`$where` can be empty because WHERE is optional. To require a clause, add `requiringNonEmpty()`. Use `fromRule('cmd')` for a general SQLite command, and the [SQLite plan example](plan.md#sqlite) for a complete CREATE TABLE statement.
