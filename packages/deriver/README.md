# Deriver

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/deriver.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/deriver)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-deriver-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Deriver derives PHP value candidates and their dependencies from source code without executing application files or their autoloaders. Queries select return values, expressions, or storage observations. Deriver follows their definitions, callers, and property writes backwards, retaining branch conditions and unresolved inputs in the resulting expressions. Missing inputs and analysis limits leave partial candidates with the remaining dependencies identified.

## Requirements

- PHP 8.1+ with the JSON and Tokenizer extensions
- A 64-bit PHP runtime
- Source targeting PHP 8.3 semantics; the host PHP version does not change the analysis target

## Installation

```bash
composer require k-kinzal/deriver
```

## Usage

```php
use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;

$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('app.php', <<<'PHP'
<?php
function userKey(int $id): string
{
    return 'user:' . $id;
}
PHP),
]));

$result = $session->derive(new ReturnQuery('userKey'));
$value = $result->normalOutcomes[0]->values['return'];

// $value retains concatenation with the symbolic parameter id.
echo $result->toJson();
```

Parameters take their candidates from callers in the supplied source. A parameter with no known caller stays symbolic, as in this example. Supply an entrypoint to bind a specific input:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Value\Term;

$query = new ReturnQuery('userKey', QueryScope::fromEntrypoints([
    new EntryPoint('userKey', [Term::constant(42)]),
]));
$result = $session->derive($query);

echo $result->normalOutcomes[0]->values['return']->native(); // user:42
```

Property candidates come from their declared initial values and corresponding assignments in the supplied source. Deriver collects these origins without requiring an execution history proving that each assignment ran:

```php
$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('app.php', <<<'PHP'
<?php
final class UserRepository
{
    private string $order = 'name';

    public function __construct(private PDO $pdo) {}

    public function orderByEmail(): void
    {
        $this->order = 'email';
    }

    public function sql(): string
    {
        return 'SELECT id FROM users ORDER BY ' . $this->order;
    }
}
PHP),
]));

$result = $session->derive(new ReturnQuery('UserRepository::sql'));

foreach ($result->normalOutcomes as $outcome) {
    echo $outcome->values['return']->native(), "\n"; // ... ORDER BY name, then ... ORDER BY email
}
```

An entry with `properties: ['order' => Term::constant('name')]` supplies that property value explicitly. The default candidate set covers origins in the supplied source and models; it does not add arbitrary external object states. An actual dependency on unavailable source or external input remains a reference in the result.

Use `symbolicArguments: true` on an `EntryPoint` to leave unspecified arguments open to source-origin lookup. Explicit positional or named arguments take precedence. A closure entry also accepts `captures: ['table' => Term::constant('users')]`; other captures are derived from the captured source where possible. Obtain its identity from `declarations()->symbols()` or a call observation's `callable`.

Inspect the result's assessment, unresolved dependencies, and exceptional outcomes before treating a normal value as exhaustive. A symbolic value can be complete even when its input is unknown.

Analysis is bounded by a `Budget`. `maxDepth` counts reference-to-origin steps independently along each dependency branch; zero retains the observed reference. `partitions` bounds eager candidate combinations, while `recursion` and `iterations` bound recursive and loop-carried dependencies. Reaching a limit retains known operands and the remaining references with their stopping reasons.

`Budget` bounds logical work, not elapsed time. Set `resources: new \Deriver\Query\ResourceLimits(seconds: 2.0)` on `Configuration` for a cooperative time limit, independently of its logical budget. Source capture precedes the query limits and is controlled by `Configuration::$sourceLimits`; a parser, custom model, or individual value operation cannot be preempted. Use a separate process if your application requires a hard deadline.

The result's `candidateGraph` retains the dependency expression, including choices beyond the enumeration limit. `normalOutcomes` contains the enumerated concrete or symbolic candidates, and `frontiers` identifies unresolved references and expansion boundaries. Candidates do not require a proof of reachability: results report `reachability = not-assessed` and coverage `source-candidates`.

Within a session, queries share dependency evaluations with matching source, input context, and expansion bounds. `Configuration::$candidateCacheEntries` limits retained evaluations to 2,048 by default. Interrupted dependencies are not reused as completed answers.

Use `deriveTogether()` to derive several observations with shared dependencies. It returns results in request order:

```php
use Deriver\Query\TupleQuery;

$input = new ProjectInput([new SourceFile('queries.php', '<?php
function report(PDO $pdo): void {
    $pdo->query("SELECT id FROM users");
    $pdo->query("SELECT id FROM orders");
}')]);
$session = (new Analyzer())->open($input);
$queries = [];
foreach ($session->callsTo('query') as $site) {
    if ($site->callable === 'report' && $site->receiver !== null) {
        $queries[] = new TupleQuery($site->beforeInvocation(), [
            'receiver' => $site->receiver,
            'sql' => $site->argument(0),
        ]);
    }
}
$results = $session->deriveTogether($queries)->results;
```

Each query has its own budget and statistics, as with `deriveMany()`. Results at different points are not mutually correlated; use a tuple for related values at one point.

A session holds at most 32 small recent results strongly by default, configurable through `Configuration::$retainedResults`. Results with more than 4,096 visited graph entries or one MiB of string payloads are not retained in that working set. Query lookup and explanations use weak references: retain the `DerivationResult` while using `explain($result->reference)`. Call `release()` to clear session-owned evaluations and recent results; caller-owned results remain valid. Source snapshots and compiled graphs have the session's lifetime.

A closed assessment can contain several candidates or symbolic inputs. Use `isConcrete()` before converting a value with `native()`. `definite()` returns the only normal outcome when every value is concrete and the result has no frontiers, exceptional outcomes or project diagnostics, and `null` otherwise. This describes the captured candidate set, not runtime reachability:

```php
$outcome = $result->definite();
if ($outcome !== null) {
    echo $outcome->values['return']->native(); // user:42
}
```

Converting a float to a string depends on the `precision` directive of the runtime, so `'v' . 0.25` stays unresolved with a `FLOAT_STRING_CONFIGURATION` frontier. Pass the directive your application runs with to resolve these conversions. The value is part of the snapshot identity, and a call to `ini_set()` in the analyzed code remains an unresolved dependency:

```php
use Deriver\Project\Configuration;
use Deriver\Project\TargetProfile;

$input = new ProjectInput([new SourceFile('app.php', '<?php function label(float $rate): string { return "rate:" . $rate; }')]);
$session = (new Analyzer())->open($input, new Configuration(new TargetProfile(floatPrecision: 14)));
```

Unresolved variable references retain their name, scope, source position, type, and reason. A global read through `global $name` uses an `EXTERNAL_INPUT` reference when no value is supplied. Provide externally initialized values through `Configuration::$environment` under `global:<name>`:

```php
$session = (new Analyzer())->open($input, new Configuration(environment: [
    'global:table_prefix' => Term::constant('wp_'),
]));
```

Static locals start from their declaration initializer for a fresh entry. They are not a union over every possible earlier invocation. To analyze a later invocation, provide an explicit `Configuration::$environment` value such as `'static:App\\counter:n' => Term::constant(4)`, using the callable's captured identity and variable name.

Request superglobals (`$_GET`, `$_POST`, `$_COOKIE`, `$_REQUEST`) remain external array references unless source assignments or supplied environment values resolve them.

`callsTo()` lists call sites without running the application. A function or method name selects calls of that name, `Class::__construct` selects `new Class(...)` sites, which are reported with the `new` operation and the created class as the target, and `*` also includes dynamic function and method calls such as `$f()` with an empty `target`. Dynamic class creation and anonymous classes are not listed.

Deriver evaluates operators without the diagnostics that newer host PHP versions add, such as the PHP 8.4 deprecation of raising zero to a negative power.

An active Xdebug lowers the host stack limit to its `xdebug.max_nesting_level`, so deep call chains are sealed earlier with a `STACK_LIMIT` frontier and results can be less precise; run analyses with `xdebug.mode=off` where possible.

`$session->declarations()` reads captured signatures and class metadata without autoloading. Captured signatures expose `static` for distinguishing static and instance methods, including composed trait methods. Function, method, class, property, and constant metadata carry the raw `docComment` text (an empty string when absent), so integrations can read annotations such as `@global wpdb $wpdb` themselves. Use `$session->comments($symbol)` for raw PHPDoc attached to statements and expressions within a callable or script, including `/** @var PDO $db */ global $db;`. Nested function, closure, and class declarations are excluded from the enclosing scope's comment list. Each `SourceComment` contains the raw text and the commented node's source range. Deriver never interprets PHPDoc, and doc comments do not change analysis results.

Missing source for an ancestor leaves method dispatch open, including `$this`, `self`, and `static` calls; it does not establish that a method is absent. Supply the ancestor declaration or a call model when its behavior is needed. Source targeting PHP 8.4 features, including property hooks, remains outside the PHP 8.3 target.

Array construction evaluates key and value expressions without retaining every intermediate array. Signed integer keys follow the PHP 8.3 append-index rules. Unknown entries retain their known neighbors.

Partial formatting and array operations retain the structure they can establish. For example, an unknown middle part of `sprintf("SELECT * FROM $table WHERE id = %d", 5)` retains the `SELECT * FROM ` prefix. The unknown part may itself contain format directives, so the later `id = 5` text is not guaranteed. Known leading and trailing values around array unpacking can remain candidates alongside the unknown remainder; they do not make the whole array concrete.

An applied call model replaces source-body derivation. Its plan requests only the inputs it uses; `DemandModel` declares any inputs additionally needed to select the plan. An explicit model decline permits source expansion.

For questions about ordered execution and reachability, select `Configuration::forExecution()` explicitly. Candidate queries never switch to that contract automatically.

Queries, models, and result types are described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/).

## License

MIT License. See [LICENSE](LICENSE) for details.
