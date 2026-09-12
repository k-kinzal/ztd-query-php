# SqlGenerator interface

`SqlFaker\Generation\SqlGenerator` is the common direct SQL-generation class for MySQL, PostgreSQL, and SQLite. Use it with a [generation plan](plan.md) when you need custom production constraints, an explicit expansion budget, or access to generation diagnostics. It uses Faker for randomness without requiring a SQL provider to be registered.

## Common

### Construction

Use `SqlFaker\Provider\SqlGeneratorFactory` to bind the selected grammar, version, lexical definitions, and structural rules. All three factory methods return the same `SqlFaker\Generation\SqlGenerator` class.

| Factory | Grammar loader | Default version |
|---------|----------------|-----------------|
| `forMySql()` | `SqlFaker\MySql\Grammar\MySqlGrammar::load()` | `mysql-8.4.7` |
| `forPostgreSql()` | `SqlFaker\PostgreSql\Grammar\PgGrammar::load()` | `pg-17.2` |
| `forSqlite()` | `SqlFaker\Sqlite\Grammar\SqliteGrammar::load()` | `sqlite-3.47.2` |

Each factory takes `(Faker\Generator $faker, SqlFaker\Grammar\Model\Grammar $grammar, string $version, ?SqlFaker\Coverage\GrammarCoverage $coverage = null)`. The loaders return the common grammar model. Their `resolveVersion(?string $version = null): string` method resolves a default or validates a supplied tag. Load the grammar and pass the same resolved version to the factory.

The optional coverage collector records which grammar and lexical choices generation exercises. Omit it when you only need SQL strings. Construct the generator before seeding Faker so setup does not affect a replayed sequence.

### Generating SQL

| Member | Purpose |
|--------|---------|
| `generate(GenerationPlan $plan): string` | Generate one result, reset the latest diagnostics, and enforce the plan's non-empty requirement |
| `planner(): SqlFaker\Generation\Choice\PlanBuilder` | Compile plans with the same grammar, aliases, structural rules, and lexical definitions |
| `lastSequence` | Nullable `SqlFaker\Generation\Token\TerminalSequence` with original production choices and transformed terminals |
| `lastOutput` | Nullable `SqlFaker\Generation\Lexeme\ResolvedOutput` with selected candidates and resolved boundaries |

`generate()` accepts `SqlFaker\Generation\Plan\GenerationPlan`, not SQL text or an enum. Use `GenerationPlan::fromRule(StatementType::Select->value)` to select an enum's grammar rule. Set complexity and expansion limits on the plan; `generate()` has no separate depth argument.

A successful call returns a string: a statement, several statements, a fragment, or an empty result according to the entry rule. `requiringNonEmpty()` requests a non-empty string or an exception. It does not turn a fragment into a complete statement. Reusing an ordinary plan generates a fresh result from the current Faker random state. A [compiled plan](plan.md#compiling-replayable-plans) can fix its production and lexical choices.

`lastSequence` and `lastOutput` describe the latest grammar-based generation. They remain `null` for direct lexical plans. After a failed call, they can be `null` or contain only the completed stages; they do not describe an earlier successful call.

For explicit composition, the constructor takes the common grammar, Faker, a `SqlFaker\Generation\Lexeme\LexicalGrammar`, and optional `TokenRewriter`, start-rule resolver closure, coverage collector, and original grammar. The public `realize(string $root, GenerationPlan $plan): string` method runs derivation, rewriting, and lexical realization from an explicit rule. It bypasses `generate()`'s start-rule resolution, diagnostic reset, coverage lifecycle, lexical-target dispatch, and final empty-string check. Use `generate()` for the complete plan contract.

### Errors

| Exception | Typical cause |
|-----------|---------------|
| `RuntimeException` | Unsupported version or unavailable/invalid grammar resources |
| `InvalidArgumentException` | Invalid plan parameters, budget, or lexical target |
| `SqlFaker\Generation\Exception\GenerationException` | Unknown rule, unsatisfied grammar constraints, insufficient expansion budget, or an empty result when non-empty output is required |
| `SqlFaker\Generation\Exception\LexicalException` | No lexical candidate satisfies the requested token, spelling, or boundary constraints |

Both generation-specific exception classes extend `RuntimeException`. Generation does not retry completed statements. Retain the version, seed or compiled plan, method arguments, and exception message when reproducing a failure. See [algorithm and limitations](algorithm.md) for the scope of successful output.

## MySQL

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

$faker = Factory::create();
$version = MySqlGrammar::resolveVersion('mysql-8.4.7');
$generator = SqlGeneratorFactory::forMySql($faker, MySqlGrammar::load($version), $version);
$faker->seed(12345);
$plan = GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(6);

$sql = $generator->generate($plan);
```

`GenerationPlan::all()` uses the grammar's own entry point. To select a general statement family explicitly, use `StatementType::SimpleStatement->value`. The generator maps common modern start-rule names to older names when needed, including `select_stmt` to `select` and `create_table_stmt` to `create`. Older fallback rules can cover broader statement families. Aliasing does not translate every nested rule or production pattern in a plan.

Use `SqlFaker\MySql\GenerationPlans` for [MySQL presets](plan.md#mysql), including non-empty row values and multi-table mutations. Each preset must be compatible with the chosen grammar version.

## PostgreSQL

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

$faker = Factory::create();
$version = PgGrammar::resolveVersion('pg-17.2');
$generator = SqlGeneratorFactory::forPostgreSql($faker, PgGrammar::load($version), $version);
$faker->seed(12345);
$plan = GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withMaxDepth(6);

$sql = $generator->generate($plan);
```

`GenerationPlan::all()` uses the grammar's entry point and can produce multiple statements or empty output. Use `GenerationPlan::fromRule('stmt')->requiringNonEmpty()` for a general non-empty statement or a specific enum value for a statement family. `StatementType::DropTable` maps to `DropStmt`, which also covers other object types.

This differs from the Faker provider's parameterless `sql()`, which first chooses a random enum case. Use `SqlFaker\PostgreSql\GenerationPlans` for [PostgreSQL presets](plan.md#postgresql), including MERGE, COPY, and TABLESAMPLE.

## SQLite

```php
use Faker\Factory;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

$faker = Factory::create();
$version = SqliteGrammar::resolveVersion('sqlite-3.47.2');
$generator = SqlGeneratorFactory::forSqlite($faker, SqliteGrammar::load($version), $version);
$faker->seed(12345);
$plan = GenerationPlan::fromRule(StatementType::Select->value)
    ->requiringNonEmpty()
    ->withStepBudget()
    ->withMaxDepth(6);

$sql = $generator->generate($plan);
```

`GenerationPlan::all()` uses the grammar's entry point. Use `fromRule('cmd')` for the general command rule. The generator also provides dedicated `insert`, `update`, `delete`, `alter_table`, and `drop_table` entry points from command alternatives. Optional rules such as `where_opt`, `orderby_opt`, `limit_opt`, and `with` can produce empty strings.

`StatementType::CreateTable` maps to the `create_table` opening fragment. To request a complete CREATE TABLE statement, constrain `cmd` to the alternative containing `create_table` and `create_table_args`; see the [SQLite plan example](plan.md#sqlite).

`SqlFaker\Sqlite\GenerationPlans` presets apply `withStepBudget()`, favoring fewer remaining expansions at the depth threshold. `multiDmlStatement()` lets you choose two semicolon-terminated DML statements explicitly. Other [SQLite presets](plan.md#sqlite) cover temporary tables, views, generated columns, cascading foreign keys, upserts, and full-text syntax.
