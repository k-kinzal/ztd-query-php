# Generation plans

A `SqlFaker\Generation\Plan\GenerationPlan` selects what to generate and which choices to constrain. Pass it to a provider's `generate()` method or to [SqlGenerator](generator.md).

## Common

### Select a grammar rule

Choose a statement or fragment by its rule name. The following Common examples use SQLite expressions:

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\SqliteProvider;

require 'vendor/autoload.php';

$faker = Factory::create();
$provider = new SqliteProvider($faker, 'sqlite-3.47.2');
$faker->seed(12345);

$expressionPlan = GenerationPlan::fromRule('expr')->withMaxDepth(3);
$expression = $provider->generate($expressionPlan);
```

Use an enum value for a statement family, or `all()` for the grammar's entry point:

```php
$selectPlan = GenerationPlan::fromRule(\SqlFaker\Sqlite\StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(3);
$select = $provider->generate($selectPlan);

$anyPlan = GenerationPlan::all()->withMaxDepth(1);
$anySql = $provider->generate($anyPlan);
```

The start rule determines whether the result is a statement, several statements, or a fragment. An unconstrained optional rule can produce an empty string.

### Require output

Add `requiringNonEmpty()` when an optional rule must produce text:

```php
$wherePlan = GenerationPlan::fromRule('where_opt')
    ->requiringNonEmpty()
    ->withMaxDepth(3);

$where = $provider->generate($wherePlan);
```

This requires a non-empty final string. It does not require every optional part inside a larger statement to be present. `GenerationPlan::statement($rule, $maxDepth)` is a shortcut for selecting a rule, setting the depth threshold, and requiring non-empty output.

### Adjust complexity and expansion limits

Plan modifiers return new plans. Keep the returned value or chain the calls:

```php
$shortPlan = $expressionPlan
    ->withMaxDepth(1)
    ->withStepBudget()
    ->withExpansionBudget(50);

$shortExpression = $provider->generate($shortPlan);
$originalDepth = $expressionPlan->maxDepth();
$shortDepth = $shortPlan->maxDepth();
```

Here `$originalDepth` remains `3` and `$shortDepth` is `1`.

`withMaxDepth()` sets the expansion-count threshold at which shorter completions are preferred; values below `1` become `1`. `withStepBudget()` makes that preference minimize remaining expansions before terminal length. `withExpansionBudget()` sets a positive total expansion limit, replacing the default of 5,000. A limit too small to complete the requested structure raises `GenerationException`.

### Select production alternatives

Use `constrained()` to restrict how a grammar rule expands. This expression must expand to an integer term:

```php
$integerPlan = GenerationPlan::constrained('expr', [
    'expr' => [ProductionPattern::exactly('term')],
    'term' => [ProductionPattern::exactly('INTEGER')],
])->requiringNonEmpty()->withMaxDepth(3);

$integer = $provider->generate($integerPlan);
```

`exactly()` matches the complete sequence of immediate symbols. `containing()` accepts an alternative containing all the named symbols, while `nonEmpty()` excludes a production with no symbols. Rule and symbol names are case-sensitive and belong to the selected grammar.

### Constrain repeated occurrences

Each rule maps to a list of patterns, applied to successive visits during leftmost derivation. This example selects addition, then requires each operand to be a term:

```php
$sumPlan = GenerationPlan::constrained('expr', [
    'expr' => [
        ProductionPattern::exactly('expr', 'PLUS', 'expr'),
        ProductionPattern::exactly('term'),
        ProductionPattern::exactly('term'),
    ],
])->withPatternForEveryOccurrence('term', ProductionPattern::exactly('INTEGER'))
    ->requiringNonEmpty()
    ->withMaxDepth(6);

$sum = $provider->generate($sumPlan);
```

Occurrence counts start at zero and reset on each call. Once an occurrence-specific list is exhausted, subsequent visits use the every-occurrence pattern if one was supplied. An occurrence-specific pattern takes precedence over that fallback. A condition on a rule that is never visited has no effect.

### Select token spellings

Use `withLexemes()` to specify text for successive occurrences of a terminal. The addition above can use two fixed integer values:

```php
$fixedSumPlan = $sumPlan->withLexemes([
    'INTEGER' => ['1', '2'],
]);

$fixedSum = $provider->generate($fixedSumPlan);
```

A spelling must be accepted by that token's lexical candidates or value domain. This controls SQL text; it does not bind database parameters. Calling `withLexemes()` again replaces the previous spelling map.

### Generate a lexical value directly

Use a lexical plan when only one identifier or literal is needed:

```php
$literalPlan = GenerationPlan::lexical('integer_literal', [
    'min' => 42,
    'max' => 42,
]);
$literal = $provider->generate($literalPlan);

$quoted = $provider->generate(
    \SqlFaker\Sqlite\GenerationPlans::quotedIdentifier(minLength: 4, maxLength: 8),
);
```

`$literal` is the string `'42'`. The dialect factories also provide `stringLiteral()`, `integerLiteral()`, and `decimalLiteral()` with explicit bounds. Lexical plans construct a value directly, so grammar production patterns and expansion limits do not affect them.

### Compile a plan with callbacks

Use `planner()->build()` to retain selected productions and lexical candidates. Each callback receives the number of available choices and returns a zero-based index. Returning `null` for a production asks for a shortest completion:

```php
$builder = $provider->planner();
$budget = $builder->minimumExpansions($integerPlan);

$replayPlan = $builder->build(
    $integerPlan,
    $budget,
    static fn (int $count): ?int => null,
    static fn (int $count): int => 0,
);

$first = $provider->generate($replayPlan);
$second = $provider->generate($replayPlan);
$sameSql = $first === $second;
```

The compiled plan retains production ordinals, token spellings, and candidate keys, including boundary choices. Keep it with the same dialect, grammar version, and SQL Faker version used to compile it.

### Compile a plan from bytes

To derive those choices from a byte string, use `BytePlanCompiler` with a planner and a grammar-based constraint plan:

```php
$compiler = new \SqlFaker\Generation\Choice\BytePlanCompiler();
$bytePlan = $compiler->compile(
    'example',
    $provider->planner(),
    $integerPlan->withExpansionBudget(20),
);

$first = $provider->generate($bytePlan);
$second = $provider->generate($bytePlan);
$sameSql = $first === $second;
```

The first four bytes choose an expansion budget within the feasible range. Remaining bytes supply production and lexical choices; omitted bytes use defaults. The input must be a grammar plan with a feasible positive expansion count and a maximum budget no greater than 1,000,000. Use direct lexical generation for lexical-value plans.

## MySQL

### Require non-empty row values

Use `withoutEmptyRows()` to constrain every row-value list reached during generation:

```php
$mysqlFaker = \Faker\Factory::create();
$mysql = new \SqlFaker\MySqlProvider($mysqlFaker, 'mysql-8.4.7');
$mysqlFaker->seed(7);

$rowsPlan = \SqlFaker\MySql\GenerationPlans::withoutEmptyRows(
    \SqlFaker\MySql\StatementType::Insert->value,
)->withMaxDepth(6);

$sql = $mysql->generate($rowsPlan);
```

The plan takes a rule-name string; the corresponding Faker `sqlWithoutEmptyRows()` helper takes the enum itself. The condition concerns row-value syntax, not insert-column counts or query results.

### Use a MySQL feature preset

A preset supplies the production constraints for a particular SQL form:

```php
$fullTextPlan = \SqlFaker\MySql\GenerationPlans::fullTextSearchStatement()
    ->withMaxDepth(6);

$sql = $mysql->generate($fullTextPlan);
```

This selects MATCH ... AGAINST. Other MySQL presets include `insertRowAliasUpsertStatement()`, `multiTableUpdateStatement()`, and `partitionSelectStatement()`. Select a grammar version that supports the requested feature.

## PostgreSQL

### Generate full-text matching syntax

Use PostgreSQL's preset to select a SELECT expression with the `@@` operator:

```php
$pgFaker = \Faker\Factory::create();
$postgres = new \SqlFaker\PostgreSqlProvider($pgFaker, 'pg-17.2');
$pgFaker->seed(7);

$fullTextPlan = \SqlFaker\PostgreSql\GenerationPlans::fullTextSearchStatement()
    ->withMaxDepth(6);

$sql = $postgres->generate($fullTextPlan);
```

The preset includes the operator spelling. The names and values remain generated; the preset does not create a full-text index or database columns.

### Choose a DML plan

`domainDmlStatements()` returns three plans, in INSERT, UPDATE, DELETE order. Choose a plan before generating its SQL:

```php
$plans = \SqlFaker\PostgreSql\GenerationPlans::domainDmlStatements();
$insertPlan = $plans[0]->withMaxDepth(3);

$insert = $postgres->generate($insertPlan);
```

These plans omit a top-level WITH clause. They do not create a domain or associate columns with one. PostgreSQL also supplies presets such as `copyStatement()`, `tableSampleStatement()`, and `partialIndexUpsertStatement()`.

## SQLite

### Generate a complete CREATE TABLE statement

Select the `cmd` alternative containing both the opening table declaration and its definition:

```php
$createTablePlan = GenerationPlan::constrained('cmd', [
    'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
])->requiringNonEmpty()->withStepBudget()->withMaxDepth(6);

$createTable = $provider->generate($createTablePlan);
```

Starting at `create_table` alone selects only the opening fragment. The `cmd` alternative includes the column definition or AS SELECT portion as well.

### Choose two DML statements

Pass a choice for each statement: `0` selects INSERT, `1` UPDATE, and `2` DELETE.

```php
$twoStatements = \SqlFaker\Sqlite\GenerationPlans::multiDmlStatement(
    firstChoice: 0,
    secondChoice: 1,
)->withMaxDepth(6);

$sql = $provider->generate($twoStatements);
```

The result contains two semicolon-terminated statements, first INSERT and then UPDATE. SQLite's preset plans use `withStepBudget()` to prefer fewer remaining expansions at the depth threshold.
