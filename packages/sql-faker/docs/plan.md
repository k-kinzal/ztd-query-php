# Generation plans

A `SqlFaker\Generation\Plan\GenerationPlan` declares the allowed SQL. Pass it to a provider's `generate()` method or to [SqlGenerator](generator.md). A constraint plan leaves unspecified choices random; it is not itself a frozen SQL statement. Compile it with `planner()->build()` or `BytePlanCompiler` when every choice must be reproducible independently of Faker's state.

`all()` retains the whole grammar. `withRule()` adds conditions on selected grammar subtrees. The caller supplies table names, column relationships and value domains; SQL Faker neither reads CREATE TABLE statements nor manages database state. The same planning mechanism applies to statements and fragments in all three dialects.

## Common

### Select a grammar rule

Choose a statement or fragment by its rule name. The following Common examples use SQLite expressions:

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Sqlite\SqliteProvider;

require 'vendor/autoload.php';

$faker = Factory::create();
$provider = new SqliteProvider($faker, 'sqlite-3.47.2');
$faker->seed(12345);

$expressionPlan = GenerationPlan::fromRule('expr')->withMaxDepth(3);
$expression = $provider->generate($expressionPlan);
```

Use an enum value for a statement family, or `all()` for the grammar's entry point:

```php
$selectPlan = GenerationPlan::fromRule(\SqlFaker\Sqlite\Generation\StatementRule::Select->value)
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

### Plan related names, operands and values

Use `RulePlan` when a condition belongs to a SQL role rather than to the nth token in the entire statement. This SQLite expression allows addition or subtraction, requires the left operand to reference `score`, and generates an integer from 1 through 10 on the right:

```php
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\Generation\Plan\LexemeConstraint;

$column = RulePlan::any()
    ->allowing(ProductionPattern::exactly('idj'))
    ->withRule('idj', RulePlan::any()
        ->allowing(ProductionPattern::exactly('ID')))
    ->withLexeme('ID', LexemeConstraint::oneOf('score'));

$integer = RulePlan::any()
    ->allowing(ProductionPattern::exactly('term'))
    ->withRule('term', RulePlan::any()
        ->allowing(ProductionPattern::exactly('INTEGER'))
        ->withLexeme('INTEGER', LexemeConstraint::integers(1, 10)));

$calculation = RulePlan::any()
    ->allowing(ProductionPattern::anyOf(
        ProductionPattern::exactly('expr', 'PLUS', 'expr'),
        ProductionPattern::exactly('expr', 'MINUS', 'expr'),
    ))
    ->withChild('expr', 0, $column)
    ->withChild('expr', 1, $integer);

$calculationPlan = GenerationPlan::fromRule('expr')
    ->withRule('expr', $calculation)
    ->requiringNonEmpty();
$sql = $provider->generate($calculationPlan);
```

`withChild('expr', 1, ...)` selects the second immediate `expr` child of the production being planned. Earlier expressions elsewhere in the statement do not change that selection. A production lacking the required child is excluded. `withRule()` applies whenever the named rule is visited inside its scope. Descendants of a selected child also inherit that child's scope.

`anyOf()` keeps the union of matching alternatives, `allOf()` keeps their intersection, and `excluding()` negates a pattern. Repeated `allowing()`, `withRule()`, `withChild()` and `withLexeme()` calls on the same scope intersect their conditions. A nearer nested rule or lexical declaration overrides the corresponding inherited default. Conditions on unvisited rules are conditional; use a production pattern or a required child when a clause must occur.

Lexical constraints belong to terminal names. `oneOf()` takes complete SQL spellings, such as `users`, `'Alice'`, or a quoted identifier, not unquoted application string values. `integers()` restricts an unsigned integer token; unary signs belong to the grammar. Every selected spelling still goes through the dialect's lexical validation. Unspecified terminals keep their normal lexical choices.

### Plan ordered lists

`withItems()` describes the non-recursive part of each production in a directly recursive list. Items are in SQL output order, regardless of whether the grammar uses left or right recursion. For example, the values for an SQLite table whose declared column order is `(id, name, score)` can share reusable expression plans:

```php
$name = RulePlan::any()
    ->allowing(ProductionPattern::exactly('term'))
    ->withRule('term', RulePlan::any()
        ->allowing(ProductionPattern::exactly('STRING'))
        ->withLexeme('STRING', LexemeConstraint::oneOf("'Alice'", "'Bob'")));

$row = RulePlan::any()->withItems(
    RulePlan::any()->withRule('expr', $integer),
    RulePlan::any()->withRule('expr', $name),
    RulePlan::any()->withRule('expr', $integer),
);

$rowPlan = GenerationPlan::fromRule('nexprlist')
    ->withRule('nexprlist', $row)
    ->requiringNonEmpty();
$sql = $provider->generate($rowPlan);
```

List length, item types and item order are fixed here; values remain random within their domains. Every compatible list production remains available. This API accepts one or more items and supports direct recursion at either end. For indirect lists, compose nested `RulePlan` scopes for the intervening grammar rules; SQLite's `selcollist`/`sclp` projection list is one such case.

### Target a writable table

Attach the same conditions to a complete statement. This plan writes the row above to the caller-supplied `users` table:

```php
$table = RulePlan::any()
    ->allowing(ProductionPattern::exactly('nm'))
    ->withRule('nm', RulePlan::any()->allowing(ProductionPattern::exactly('idj')))
    ->withRule('idj', RulePlan::any()->allowing(ProductionPattern::exactly('ID')))
    ->withLexeme('ID', LexemeConstraint::oneOf('users'));

$insert = RulePlan::any()
    ->allowing(ProductionPattern::containing('insert_cmd', 'select', 'upsert'))
    ->withRule('xfullname', $table)
    ->withRule('insert_cmd', RulePlan::any()
        ->allowing(ProductionPattern::exactly('INSERT', 'orconf')))
    ->withRule('select', RulePlan::any()
        ->allowing(ProductionPattern::exactly('selectnowith')))
    ->withRule('selectnowith', RulePlan::any()
        ->allowing(ProductionPattern::exactly('oneselect')))
    ->withRule('oneselect', RulePlan::any()
        ->allowing(ProductionPattern::exactly('values')))
    ->withRule('values', RulePlan::any()
        ->allowing(ProductionPattern::exactly('VALUES', 'LP', 'nexprlist', 'RP')))
    ->withRule('nexprlist', $row);

$insertPlan = GenerationPlan::fromRule('cmd')->withRule('cmd', $insert)
    ->requiringNonEmpty()->withExpansionBudget(128);
foreach (['with', 'orconf', 'idlist_opt', 'upsert'] as $optional) {
    $insertPlan = $insertPlan->withRule($optional,
        RulePlan::any()->allowing(ProductionPattern::exactly()));
}
$sql = $provider->generate($insertPlan);
```

This example deliberately selects VALUES for brevity. To keep VALUES and SELECT available, allow both `oneselect` productions and attach the same expression plans to their respective row and projection lists. The SQLite [semantic fuzz plans](../../ztd-query-sqlite/fuzz/Semantics/StatementPlans.php) demonstrate that composition, plus UPDATE assignment/predicate scopes and DELETE predicates. MySQL's optional INTO and VALUE/VALUES choices likewise remain random unless constrained.

A schema-compatible plan must account for every reachable form it allows: target names, corresponding columns and values, expression types, and any optional clauses relevant to the caller. SQL Faker does not infer these relationships from identifier spellings, enforce database constraints such as uniqueness or foreign keys, or synthesize defaults from a live schema.

Unknown declared rule names are rejected before generation. Alternatives that cannot complete under their scoped conditions are excluded; if no finite derivation remains, generation fails. Conflicting conditions in a single declaration are rejected rather than silently overwritten. Rule names are dialect- and release-specific, just like the existing production patterns.

### Constrain repeated occurrences (low-level replay)

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
    \SqlFaker\Sqlite\Generation\GenerationPlans::quotedIdentifier(minLength: 4, maxLength: 8),
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

Both scoped `RulePlan` conditions and positional constraints use this compiler. The compiled plan retains source production ordinals, token spellings, and candidate keys, including boundary choices; it no longer needs the scoped declarations to replay. Keep it with the same dialect, grammar version, and SQL Faker version used to compile it.

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


### Encode choices as fuzzer input

`BytePlanEncoder` is the inverse of `BytePlanCompiler`: it runs `planner()->build()` once with callbacks that also receive the candidate productions, and returns the bytes the compiler decodes to that very plan. Lexical choices left to `null` are made the way the compiler will make them from the padding bytes:

```php
$builder = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();

$input = (new BytePlanEncoder())->encode(
    $builder,
    $constraints,
    $builder->minimumExpansions($constraints),
    static fn (int $count, array $candidates): int => $count - 1,
);

$plan = (new BytePlanCompiler())->compile($input, $builder, $constraints);
$sql = $provider->generate($plan);
```

The budget must lie between `minimumExpansions()` and the constraints' expansion budget, 5000 without one. `bin/seeds.php` uses the encoder to write one such input for every production a start rule can reach; the package's `seeds/` directory holds the results for the default grammar versions.
## MySQL

### Require non-empty row values

Use `withoutEmptyRows()` to constrain every row-value list reached during generation:

```php
$mysqlFaker = \Faker\Factory::create();
$mysql = new \SqlFaker\MySql\MySqlProvider($mysqlFaker, 'mysql-8.4.7');
$mysqlFaker->seed(7);

$rowsPlan = \SqlFaker\MySql\Generation\GenerationPlans::withoutEmptyRows(
    \SqlFaker\MySql\Generation\StatementRule::Insert->value,
)->withMaxDepth(6);

$sql = $mysql->generate($rowsPlan);
```

The plan takes a rule-name string; the corresponding Faker `sqlWithoutEmptyRows()` helper takes the enum itself. The condition concerns row-value syntax, not insert-column counts or query results.

### Use a MySQL feature preset

A preset supplies the production constraints for a particular SQL form:

```php
$fullTextPlan = \SqlFaker\MySql\Generation\GenerationPlans::fullTextSearchStatement()
    ->withMaxDepth(6);

$sql = $mysql->generate($fullTextPlan);
```

This selects MATCH ... AGAINST. Other MySQL presets include `insertRowAliasUpsertStatement()`, `multiTableUpdateStatement()`, and `partitionSelectStatement()`. Select a grammar version that supports the requested feature.

## PostgreSQL

### Generate full-text matching syntax

Use PostgreSQL's preset to select a SELECT expression with the `@@` operator:

```php
$pgFaker = \Faker\Factory::create();
$postgres = new \SqlFaker\PostgreSql\PostgreSqlProvider($pgFaker, 'pg-17.2');
$pgFaker->seed(7);

$fullTextPlan = \SqlFaker\PostgreSql\Generation\GenerationPlans::fullTextSearchStatement()
    ->withMaxDepth(6);

$sql = $postgres->generate($fullTextPlan);
```

The preset includes the operator spelling. The names and values remain generated; the preset does not create a full-text index or database columns.

### Choose a DML plan

`domainDmlStatements()` returns three plans, in INSERT, UPDATE, DELETE order. Choose a plan before generating its SQL:

```php
$plans = \SqlFaker\PostgreSql\Generation\GenerationPlans::domainDmlStatements();
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
$twoStatements = \SqlFaker\Sqlite\Generation\GenerationPlans::multiDmlStatement(
    firstChoice: 0,
    secondChoice: 1,
)->withMaxDepth(6);

$sql = $provider->generate($twoStatements);
```

The result contains two semicolon-terminated statements, first INSERT and then UPDATE. SQLite's preset plans use `withStepBudget()` to prefer fewer remaining expansions at the depth threshold.
