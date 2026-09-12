# Generation plans

A `SqlFaker\Generation\Plan\GenerationPlan` describes what SQL Faker should generate: the entry rule, allowed grammar productions, selected token spellings, output requirements, and complexity threshold. Pass a plan to a dialect's [SqlGenerator](generator.md). The [Faker interface](faker.md) offers convenient methods for many of the same plans.

## Common

### Creating and reusing a plan

Plans are immutable. Every `with...()` method and `requiringNonEmpty()` returns a new plan, so retain the return value or chain the calls. The base plan factories and immutable modifiers do not generate SQL or consume Faker randomness. Dialect factories that select a random statement type and compilation of replayable plans do make choices.

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\Provider\SqlGeneratorFactory;
use SqlFaker\MySql\StatementType;

$faker = Factory::create();
$version = MySqlGrammar::resolveVersion();
$generator = SqlGeneratorFactory::forMySql($faker, MySqlGrammar::load($version), $version);
$faker->seed(12345);

$base = GenerationPlan::fromRule(StatementType::Select->value)->requiringNonEmpty();
$short = $base->withMaxDepth(6);

$first = $generator->generate($short);
$second = $generator->generate($short);
```

The two calls reuse the constraints and consume successive random values. A plan does not store generated SQL or keep identifiers consistent across calls.

| Factory or modifier | Meaning |
|---------------------|---------|
| `GenerationPlan::all()` | Use the generator's dialect-specific default entry point |
| `GenerationPlan::statement(?string $startRule, int $maxDepth)` | Select a rule or the default entry point, set the depth threshold, and require non-empty output |
| `GenerationPlan::fromRule(string $startRule)` | Start from a named grammar rule |
| `GenerationPlan::constrained(string $startRule, array $patterns)` | Start from a rule and apply patterns by rule occurrence |
| `GenerationPlan::lexical(string $target, array $parameters)` | Construct one lexical value using integer parameters |
| `withMaxDepth(int $maxDepth)` | Set the expansion threshold; values below `1` become `1` |
| `withExpansionBudget(int $budget)` | Set a positive total expansion limit; omitted means 5,000 |
| `withStepBudget()` | At the depth threshold, prefer fewer remaining expansions before terminal length |
| `withCandidateKeys(array $keys)` | Fix complete lexical candidate identities by terminal occurrence; replaces the previous key map |
| `requiringNonEmpty()` | Require a non-empty result, or fail generation |
| `withPatternForEveryOccurrence(string $rule, ProductionPattern $pattern)` | Apply a fallback pattern to every visit to a rule |
| `withLexemes(array $lexemes)` | Select terminal spellings by occurrence; replaces the plan's previous lexeme map |

Grammar plans initially allow empty results and default to `PHP_INT_MAX` for `maxDepth`. Lexical plans always require non-empty output. `requiringNonEmpty()` applies to the final string; it does not require every optional clause or row to be non-empty. See [complexity and termination](algorithm.md#complexity-and-termination) for the actual meaning of `maxDepth`.

### Production patterns

`SqlFaker\Generation\Plan\ProductionPattern` matches the immediate symbols on the right-hand side of a grammar production. Symbol names are case-sensitive and depend on the dialect and grammar version. Patterns do not match rendered SQL text or search recursively through a production's descendants.

| Pattern | Match |
|---------|-------|
| `ProductionPattern::containing(string ...$symbols)` | The production contains every named symbol, in any order; other symbols are allowed |
| `ProductionPattern::exactly(string ...$symbols)` | The complete sequence of symbols matches in order |
| `ProductionPattern::exactly()` | The production has no symbols, selecting an empty alternative |
| `ProductionPattern::at(int $ordinal)` | Select the zero-based alternative index, distinguishing alternatives with identical symbols |
| `ProductionPattern::nonEmpty()` | The production has at least one symbol |

`nonEmpty()` concerns the immediate production. Its symbols can themselves derive empty strings, so it is different from the final-output requirement `requiringNonEmpty()`.

`$pattern->matches(array $symbols, ?int $ordinal = null): bool` checks a supplied list of symbol names against the pattern without generating SQL.

In `constrained()`, `$patterns` maps each rule name to a non-empty list of patterns. The first pattern applies to occurrence `0`, the next to occurrence `1`, and so on. Occurrences count rule visits during leftmost derivation, including recursive visits, and reset for each generation call. Once the list is exhausted, the rule becomes unconstrained unless it has an every-occurrence pattern. An occurrence-specific pattern takes precedence over that fallback; the two are not combined.

For example, this plan restricts an SQLite expression to an integer literal:

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Provider\SqlGeneratorFactory;

$faker = Factory::create();
$version = SqliteGrammar::resolveVersion();
$generator = SqlGeneratorFactory::forSqlite($faker, SqliteGrammar::load($version), $version);
$faker->seed(12345);
$plan = GenerationPlan::constrained('expr', [
    'expr' => [ProductionPattern::exactly('term')],
    'term' => [ProductionPattern::exactly('INTEGER')],
])->requiringNonEmpty()->withMaxDepth(6);

$literal = $generator->generate($plan);
```

A constraint has no effect if its rule is never reached. Generation checks descendant and repeated-occurrence constraints while selecting affordable completions. If no completion can satisfy the plan within its budget, it raises `SqlFaker\Generation\Exception\GenerationException`. The immutable plan factories do not prove satisfiability when a plan is created.

### Token spellings

`withLexemes()` maps terminal names to non-empty lists of requested strings. Occurrences are counted from zero in output order, not in the right-to-left order used for lexical selection. Later occurrences use normal generation, and counts restart when a plan is reused. A request for a terminal that is never reached has no effect.

A requested spelling must match an available lexical candidate or value domain and remain compatible with neighboring tokens. For example, PostgreSQL's full-text preset uses `['Op' => ['@@']]`. Unavailable spellings raise `SqlFaker\Generation\Exception\LexicalException`. This mechanism does not validate table names against a schema or bind SQL parameters. Empty strings can represent non-output marker candidates when supported.

`withLexemes()` replaces the whole spelling map, including choices from a preset. `withCandidateKeys()` additionally selects complete candidate identities, including boundary semantics that spelling alone cannot express. Compiled plans populate these keys automatically; retain them when replaying those choices. Structural rewrites can change terminal names, so handcrafted spelling maps require the selected dialect's token definitions.

### Lexical plans

Use the dialect's `GenerationPlans` factories for lexical values. They supply the correct lexical target and parameter names. All parameters in the following factories are required integers; unlike the Faker provider methods, these factories do not define default arguments.

| Factory available in every dialect | Raw lexical target | Parameter keys |
|-----------------------------------|--------------------|----------------|
| `quotedIdentifier($minLength, $maxLength)` | `quoted_identifier` | `minLength`, `maxLength` |
| `stringLiteral($minLength, $maxLength)` | `string_literal` | `minLength`, `maxLength` |
| `integerLiteral($min, $max)` | `integer_literal` | `min`, `max` |
| `decimalLiteral($precision, $scale)` | `decimal_literal` | `precision`, `scale` |

For example, `SqlFaker\MySql\GenerationPlans::stringLiteral(1, 20)` is equivalent to `GenerationPlan::lexical('string_literal', ['minLength' => 1, 'maxLength' => 20])` when used with a MySQL generator.

Lexical plans bypass statement derivation, structural rewriting, and lexical boundary selection. Grammar production patterns, lexeme overrides, candidate keys, and derivation limits do not affect that path. The generator checks the target when the plan is used; parameters control text construction and do not validate server limits. In particular, decimal construction emits at least two fractional digits even when a smaller scale is requested.

### Compiling replayable plans

`$generator->planner()` and `$provider->planner()` return `SqlFaker\Generation\Choice\PlanBuilder` configured for that generator. Its `root(GenerationPlan $plan): string` resolves the entry rule. `minimumExpansions(GenerationPlan $plan): int` computes the expansion requirement under the plan's production constraints and budget.

`build(GenerationPlan $constraints, int $budget, Closure $productionChoice, Closure $lexicalChoice): GenerationPlan` compiles selected productions and lexical candidates into an immutable plan. Each callback receives a choice count and returns a zero-based index or `null`. For productions, `null` chooses a shortest completion; a missing lexical choice uses the default choice. The plan records production ordinals, spellings, and candidate keys for replay through `generate()`. Use a grammar-derivation plan as its input.

`SqlFaker\Generation\Choice\BytePlanCompiler::compile(string $input, PlanBuilder $builder, ?GenerationPlan $constraints = null)` supplies those choices from bytes. The first four bytes choose a budget within the feasible range, and subsequent bytes provide production and lexical decisions. Missing bytes use default choices. Compilation requires a grammar plan with at least one required expansion and a maximum budget no greater than 1,000,000; lexical plans and infeasible budgets raise `InvalidArgumentException`.

```php
use Faker\Factory;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\SqliteProvider;

$provider = new SqliteProvider(Factory::create(), 'sqlite-3.47.2');
$constraints = GenerationPlan::constrained('expr', [
    'expr' => [ProductionPattern::exactly('term')],
    'term' => [ProductionPattern::exactly('INTEGER')],
])->requiringNonEmpty()->withExpansionBudget(20);
$plan = (new BytePlanCompiler())->compile('example', $provider->planner(), $constraints);

$first = $provider->generate($plan);
$second = $provider->generate($plan);
if ($first !== $second) {
    throw new RuntimeException('Expected the compiled plan to replay its choices.');
}
```

Compiled plans target the grammar and lexical definitions that produced them. They are not portable across dialects or guaranteed compatible with later SQL Faker releases. They fix the generated syntax and values, not the schema or server state needed to execute that SQL.

### Inspecting a plan

| Accessor | Result |
|----------|--------|
| `startRule(): ?string` | Requested start rule, or `null` |
| `patternAt(string $rule, int $occurrence): ?ProductionPattern` | Effective pattern for the rule visit |
| `lexemeAt(string $terminal, int $occurrence): ?string` | Requested token spelling |
| `lexicalTarget(): ?string` | Lexical target, or `null` for grammar derivation |
| `parameters(): array` | Lexical integer parameters |
| `requiresNonEmpty(): bool` | Whether the result must be non-empty |
| `maxDepth(): int` | Expansion threshold |
| `expansionBudget(): ?int` | Explicit total expansion budget, or `null` for the default |
| `usesStepBudget(): bool` | Whether shorter completion prefers expansion count first |
| `candidateKeyAt(string $terminal, int $occurrence): ?string` | Requested complete candidate identity |

### Presets shared across dialects

Each dialect has its own `GenerationPlans` class. The following no-argument factories exist in all three and return a plan requiring non-empty output. Their rule names and SQL forms differ by dialect.

| Factory | Requested syntax |
|---------|------------------|
| `insertFunctionUpsertStatement()` | Upsert with a function in the update portion |
| `temporaryTableStatement()` | CREATE TEMPORARY/TEMP TABLE |
| `viewStatement()` | CREATE VIEW |
| `generatedColumnStatement()` | CREATE TABLE with a generated column |
| `foreignKeyCascadeStatement()` | CREATE TABLE with ON UPDATE and ON DELETE CASCADE |
| `fullTextSearchStatement()` | SELECT with full-text matching syntax |

These plans default to `PHP_INT_MAX`; the corresponding Faker helpers normally apply `maxDepth: 40`. Add `withMaxDepth(40)` to use that same threshold with the direct generator. Plan names describe syntax selection, not database setup or semantic validity.

## MySQL

Use `SqlFaker\MySql\GenerationPlans` with a generator created by `SqlGeneratorFactory::forMySql()`.

| Factory | Parameters | Requested syntax |
|---------|------------|------------------|
| `withoutEmptyRows()` | `?string $startRule = null` | Require every visited `opt_values` production to be non-empty and require non-empty output |
| `foreignKeyConstraint()` | `SqlFaker\Grammar\Model\Grammar $grammar` | Named foreign key; selects compatible constraint rules from the supplied grammar |
| `multiTableUpdateStatement()` | None | Two-target UPDATE |
| `multiTableDeleteStatement()` | None | Two-target DELETE |
| `updateJoinDerivedStatement()` | None | UPDATE joining a derived table |
| `insertSelectCompoundStatement()` | None | INSERT from a UNION ALL query |
| `insertRowAliasUpsertStatement()` | None | Row alias and ON DUPLICATE KEY UPDATE |
| `partitionSelectStatement()` | None | SELECT with PARTITION |
| `loadDataStatement()` | None | The `load_stmt` rule, including DATA or XML alternatives |

`withoutEmptyRows()` accepts a grammar rule string, such as `StatementType::Insert->value`; the Faker `sqlWithoutEmptyRows()` method accepts the enum itself. It constrains every visit to `opt_values`, including nested occurrences. It does not check whether row lengths match a column list.

Use the same loaded grammar for `foreignKeyConstraint($grammar)` and the generator. Other presets use specific production shapes; availability in an older MySQL grammar must not be assumed. Start-rule fallbacks do not rewrite all nested constraints.

The shared upsert preset selects ON DUPLICATE KEY UPDATE with an IF expression, the full-text preset selects MATCH ... AGAINST, and generated-column plans select STORED syntax.

MySQL additionally provides these lexical factories. Every listed argument is required and has type `int`.

| Factory | Raw target |
|---------|------------|
| `nationalStringLiteral($minLength, $maxLength)` | `national_string_literal` |
| `dollarQuotedString($minLength, $maxLength)` | `dollar_quoted_string` |
| `longIntegerLiteral($min, $max)` | `long_integer_literal` |
| `unsignedBigIntLiteral($minLength, $maxLength)` | `unsigned_big_int_literal` |
| `floatLiteral($precision, $scale, $minExponent, $maxExponent)` | `float_literal` |
| `hexLiteral($minLength, $maxLength)` | `hex_literal` |
| `quotedHexLiteral($minBytes, $maxBytes)` | `quoted_hex_literal` |
| `binaryLiteral($minLength, $maxLength)` | `binary_literal` |
| `hostname($minParts, $maxParts, $maxPartLength)` | `hostname` |

For raw lexical plans, parameter keys are the argument names shown in the table. Output forms and construction limits are described in the [MySQL Faker reference](faker.md#mysql).

## PostgreSQL

Use `SqlFaker\PostgreSql\GenerationPlans` with a generator created by `SqlGeneratorFactory::forPostgreSql()`.

| Factory | Result and requested syntax |
|---------|-----------------------------|
| `foreignKeyConstraint()` | Named foreign key fragment |
| `partitionOfStatement()` | CREATE TABLE ... PARTITION OF with FROM/TO bounds |
| `tableSampleStatement()` | SELECT with TABLESAMPLE |
| `doStatement()` | DO with a string body |
| `mergeStatement()` | MERGE with DELETE, DO NOTHING, UPDATE, and INSERT branches |
| `copyStatement()` | COPY |
| `partialIndexUpsertStatement()` | ON CONFLICT DO UPDATE with an index-inference WHERE predicate |
| `domainDmlStatements()` | A list of three plans, for INSERT, UPDATE, and DELETE, each excluding top-level WITH |

The factories in the table take no arguments. Every factory returns a non-empty-output plan except `domainDmlStatements()`, which returns a list of such plans. Choose an entry from that list before calling `generate()`. The list does not create a domain or constrain its DML to domain-typed columns. `doStatement()` generates a string body without validating its procedural language.

`statementOfType(Faker\Generator $faker, ?StatementType $type, int $maxDepth)` is also available: it selects a random enum case when `$type` is `null` and returns a non-empty-output plan at the requested threshold. This factory consumes randomness when choosing a type.

The shared upsert preset selects ON CONFLICT DO UPDATE, the full-text preset fixes the operator to `@@`, and generated-column plans select STORED syntax. These plans do not create the functions, indexes, or types needed for execution.

PostgreSQL additionally provides the following lexical factories, with required integer arguments. Raw parameter keys match the argument names.

| Factory | Raw target |
|---------|------------|
| `floatLiteral($precision, $scale, $minExponent, $maxExponent)` | `float_literal` |
| `hexLiteral($minLength, $maxLength)` | `hex_literal` |
| `binaryLiteral($minLength, $maxLength)` | `binary_literal` |
| `dollarQuotedString($minLength, $maxLength)` | `dollar_quoted_string` |
| `parameterMarker($min, $max)` | `parameter_marker` |

Output forms are described in the [PostgreSQL Faker reference](faker.md#postgresql).

## SQLite

Use `SqlFaker\Sqlite\GenerationPlans` with a generator created by `SqlGeneratorFactory::forSqlite()`. SQLite presets apply `withStepBudget()` to prefer fewer remaining expansions at the depth threshold.

| Factory | Parameters and requested syntax |
|---------|---------------------------------|
| `foreignKeyConstraint()` | No arguments; named foreign key fragment |
| `multiDmlStatement(int $firstChoice, int $secondChoice)` | Two semicolon-terminated DML statements; `0` = INSERT, `1` = UPDATE, `2` = DELETE for each choice |

`statement(string $startRule, int $maxDepth)` also creates a plan with the SQLite completion preference, allowing empty output. `statementOfType(Faker\Generator $faker, ?StatementType $type, int $maxDepth)` uses that plan and randomly chooses an enum case when `$type` is `null`. These match the provider’s statement and optional-fragment behavior.

The two factories in the table return a plan requiring non-empty output. An invalid DML choice raises `InvalidArgumentException`. The shared upsert preset selects ON CONFLICT DO UPDATE, and the full-text preset selects MATCH. SQLite has no additional lexical factories beyond the four common ones.

To request a complete CREATE TABLE statement, constrain `cmd` instead of starting at the `create_table` opening fragment:

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Provider\SqlGeneratorFactory;

$faker = Factory::create();
$version = SqliteGrammar::resolveVersion();
$generator = SqlGeneratorFactory::forSqlite($faker, SqliteGrammar::load($version), $version);
$faker->seed(12345);
$plan = GenerationPlan::constrained('cmd', [
    'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
])->requiringNonEmpty()->withMaxDepth(6);

$sql = $generator->generate($plan);
```

This selects the complete statement shape, while column names, types, and other details remain generated. It does not guarantee that the resulting definition is semantically accepted by SQLite.
