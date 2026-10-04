# Deriver

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/deriver.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/deriver)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-deriver-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Deriver derives PHP values, state, and dependencies from source code without executing application files or their autoloaders. Queries select return values, evaluated expressions, or storage observations. Results retain symbolic inputs, branch conditions, shared references and objects, and exceptional outcomes. Unsupported operations and exhausted analysis budgets leave explicit unresolved dependencies.

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

The default scope keeps parameters symbolic. Supply an entrypoint to derive the value for a specific invocation:

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

An object that enters from outside the analysis, such as the receiver of an entry method or an object argument, keeps the declared types of its properties, but their values stay symbolic: Deriver does not guess them from assignments elsewhere. A typed property may also be uninitialized, because PHP can create an object without running its constructor, so reading one keeps a possible `Error` outcome. Supply the receiver's initial property values when they describe the state at the entry. A declared default is not a fact about every existing instance: a constructor or an earlier method may have replaced it. Each explicit entry describes one invocation, and unspecified properties stay symbolic:

```php
$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('app.php', <<<'PHP'
<?php
final class UserRepository
{
    private string $order = 'name';

    public function __construct(private PDO $pdo) {}

    public function sql(): string
    {
        return 'SELECT id FROM users ORDER BY ' . $this->order;
    }
}
PHP),
]));

$default = $session->declarations()->class('UserRepository')->properties['order']->default;
$result = $session->derive(new ReturnQuery('UserRepository::sql', QueryScope::fromEntrypoints([
    new EntryPoint('UserRepository::sql', properties: ['order' => $default]),
    new EntryPoint('UserRepository::sql', properties: ['order' => Term::constant('email')]),
])));

foreach ($result->normalOutcomes as $outcome) {
    echo $outcome->values['return']->native(), "\n"; // ... ORDER BY name, then ... ORDER BY email
}
```

An entry with `properties: ['order' => Term::constant('name')]` asserts that value at the start of that invocation. It does not request a union of assignments found elsewhere. To analyze constructor effects, start from source that constructs the object and calls the method. For externally initialized objects, supply the relevant states or a model of their initialization. Include a symbolic entry when other initial states must remain possible.

Property names are resolved from the entry method's class. A name that is not a declared instance property, a value that violates the declared type, or properties on a static method or function entry throw `InvalidInputException`.

Use `symbolicArguments: true` on an `EntryPoint` to bind omitted arguments like a symbolic query, including enumeration of enum cases. Explicit positional or named arguments still take precedence. A closure entry also accepts `captures: ['table' => Term::constant('users')]`; omitted captures stay symbolic, and names not captured by the closure are rejected. Obtain its identity from `declarations()->symbols()` or a call observation's `callable`; Deriver does not execute an uncalled closure automatically.

Inspect the result's assessment, unresolved dependencies, and exceptional outcomes before treating a normal value as exhaustive. A symbolic value can be complete even when its input is unknown.

Analysis is bounded by a `Budget`. When more paths or outcomes than the budget allows reach one point, Deriver keeps exact candidates where possible and joins excess states into widened values instead of dropping them, and records a `BUDGET_EXCEEDED` frontier. At an overflowing loop header, it joins compatible paths together to bound the next iteration's branching. A widened string keeps the bytes its candidates start with, so a loop that appends conditions to a known query yields its exact unrollings and `concat('SELECT ... WHERE 1', <string>)`. Recursion over symbolic inputs forks at every level and is bounded by `Budget::$symbolicRecursion`; recursion over concrete values is bounded by `Budget::$recursion`.

`Budget` bounds reproducible logical work, not elapsed time. Set `resources: new \Deriver\Query\ResourceLimits(seconds: 2.0)` on `Configuration` for a cooperative time limit, independently of its logical budget. Resource checks also run during path joins and isolation proofs. Source capture precedes the query limits and is controlled by `Configuration::$sourceLimits`; a parser, custom model, or individual value operation cannot be preempted. Use a separate process if your application requires a hard deadline.

If execution stops before a value or tuple observation, Deriver can recover constants and concatenation structure from the already captured expression, with opaque gaps and an interruption frontier. Recovery can also retain the declared type of an immutable by-value parameter or receiver. It first checks the complete bounded owner graph for writes, reference escapes, catch bindings, and dynamic symbol-table effects; parameters are not treated as immutable merely because they have a type declaration. These are candidates with unresolved reachability. They are useful for partial reports and cannot pass `definite()`. An interrupted callee invalidates its reachable references, objects, globals and statics; unrelated caller locals retain their values.

Within a session, distinct queries can reuse bounded summaries of closed, isolated source functions. Reuse requires reference-free inputs and a proof that the call tree affects only local state and contains no requested observation. An unrelated unresolved call earlier in the query does not prevent reuse of a later closed function. Interrupted, warning-bearing, or effectful computations are excluded. Replays charge the original logical transfer cost, so warming the cache does not expand a query's budget. The cache retains at most 32 small specializations; it does not eliminate execution from application entrypoints for arbitrary call graphs.

Use `deriveTogether()` when several observations belong to the same symbolic callable. It executes the shared prefix once and returns results in request order:

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

All queries in a batch must have the same callable owner, symbolic scope, and identical `Budget` settings. One logical budget and one `Configuration::$resources` allowance cover the entire execution. Each result reports the batch's total work and frontiers, including boundaries encountered after an earlier observation; do not sum those statistics as separate executions. An interruption recovers each still-unreached supported expression separately. Results at different points are not mutually correlated; use a tuple for values at one point. `deriveMany()` continues to run independent queries with independent budgets. Batched results have separate identities and do not fill the independent-query cache.

A session holds at most 32 small recent results strongly. Results with more than 4,096 visited graph entries or one MiB of string payloads are not retained in that working set. Query lookup and explanations use weak references: retain the `DerivationResult` while using `explain($result->reference)`. Once the caller releases an older or large result, its reference may no longer be explainable and a repeated query may execute again. This bounds session ownership of result graphs; source snapshots, compiled graphs, and results retained by your application have separate lifetimes.

A closed assessment does not mean the result is a single fixed value. Reading an undefined variable is closed and concrete, yet it carries a `PHP_WARNING` frontier, because an error handler can turn the warning into an exception. When you need one value PHP always produces, use `definite()`. It returns the only normal outcome when every value is concrete and the result has no frontiers, exceptional outcomes or project diagnostics, and `null` otherwise:

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

Frontiers that depend on a variable name it in `knownDependencies`. Reading a variable that is never assigned is `null` with a `PHP_WARNING` frontier, and reading a global through `global $name` that the configuration does not supply is an external value with an `EXTERNAL_INPUT` frontier. A global variable is named `global:<name>`, the key that `Configuration::$environment` accepts, and a function-local variable is named `variable:<name>`. To analyze code with state that the application receives from outside, such as globals set by a framework bootstrap, read these names from the frontiers and supply the values:

```php
$session = (new Analyzer())->open($input, new Configuration(environment: [
    'global:table_prefix' => Term::constant('wp_'),
]));
```

Static locals start from their declaration initializer for a fresh entry. They are not a union over every possible earlier invocation. To analyze a later invocation, provide an explicit `Configuration::$environment` value such as `'static:App\\counter:n' => Term::constant(4)`, using the callable's captured identity and variable name.

Default request superglobals (`$_GET`, `$_POST`, `$_COOKIE`, `$_REQUEST`) contain strings, arrays, or absent values. Reading them at script scope preserves that domain. Explicit source assignments or injected environments can replace it. A generic `mixed` value may still be an object with effectful `__toString()`, so converting it can invalidate shared globals; use an input value or model when a narrower domain is known.

`callsTo()` lists call sites without running the application. A function or method name selects calls of that name, `Class::__construct` selects `new Class(...)` sites, which are reported with the `new` operation and the created class as the target, and `*` also includes dynamic function and method calls such as `$f()` with an empty `target`. Dynamic class creation and anonymous classes are not listed.

Deriver evaluates operators without the diagnostics that newer host PHP versions add, such as the PHP 8.4 deprecation of raising zero to a negative power.

An active Xdebug lowers the host stack limit to its `xdebug.max_nesting_level`, so deep call chains are sealed earlier with a `STACK_LIMIT` frontier and results can be less precise; run analyses with `xdebug.mode=off` where possible.

`$session->declarations()` reads captured signatures and class metadata without autoloading. Captured signatures expose `static` for distinguishing static and instance methods, including composed trait methods. Function, method, class, property, and constant metadata carry the raw `docComment` text (an empty string when absent), so integrations can read annotations such as `@global wpdb $wpdb` themselves. Use `$session->comments($symbol)` for raw PHPDoc attached to statements and expressions within a callable or script, including `/** @var PDO $db */ global $db;`. Nested function, closure, and class declarations are excluded from the enclosing scope's comment list. Each `SourceComment` contains the raw text and the commented node's source range. Deriver never interprets PHPDoc, and doc comments do not change analysis results.

Missing source for an ancestor leaves method dispatch open, including `$this`, `self`, and `static` calls; it does not establish that a method is absent. Supply the ancestor declaration or a call model when its behavior is needed. Source targeting PHP 8.4 features, including property hooks, remains outside the PHP 8.3 target.

Flat scalar literal arrays, including directly signed integer and float values, are constructed without retaining every intermediate array. Signed integer keys follow the PHP 8.3 append-index rules; float keys and expressions that may warn still use ordinary evaluation.

Partial formatting and array operations retain the structure they can establish. For example, an unknown middle part of `sprintf("SELECT * FROM $table WHERE id = %d", 5)` retains the `SELECT * FROM ` prefix. The unknown part may itself contain format directives, so the later `id = 5` text is not guaranteed. Known leading and trailing values around array unpacking can remain candidates alongside the unknown remainder; they do not make the whole array concrete.

Queries, models, and result types are described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/).

## License

MIT License. See [LICENSE](LICENSE) for details.
