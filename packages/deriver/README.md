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

An object that enters from outside the analysis, such as the receiver of an entry method or an object argument, keeps the declared types of its properties, but their values stay symbolic: Deriver does not guess them from assignments elsewhere. A typed property may also be uninitialized, because PHP can create an object without running its constructor, so reading one keeps a possible `Error` outcome. Supply the receiver's initial property values when you know them, for example from the declared defaults. Each entry is one candidate, and unspecified properties stay symbolic:

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

Property names are resolved from the entry method's class. A name that is not a declared instance property, a value that violates the declared type, or properties on a static method or function entry throw `InvalidInputException`.

Inspect the result's assessment, unresolved dependencies, and exceptional outcomes before treating a normal value as exhaustive. A symbolic value can be complete even when its input is unknown.

Analysis is bounded by a `Budget`. When more paths or outcomes than the budget allows reach one point, Deriver keeps the first ones exactly and joins the rest into one widened value instead of dropping them, and records a `BUDGET_EXCEEDED` frontier. A widened string keeps the bytes its candidates start with, so a loop that appends conditions to a known query yields its exact unrollings and `concat('SELECT ... WHERE 1', <string>)`. Recursion over symbolic inputs forks at every level and is bounded by `Budget::$symbolicRecursion`; recursion over concrete values is bounded by `Budget::$recursion`.

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

`callsTo()` lists call sites without running the application. A function or method name selects calls of that name, `Class::__construct` selects `new Class(...)` sites, which are reported with the `new` operation and the created class as the target, and `*` selects every call and creation whose name is written in the source.

Deriver evaluates operators without the diagnostics that newer host PHP versions add, such as the PHP 8.4 deprecation of raising zero to a negative power.

An active Xdebug lowers the host stack limit to its `xdebug.max_nesting_level`, so deep call chains are sealed earlier with a `STACK_LIMIT` frontier and results can be less precise; run analyses with `xdebug.mode=off` where possible.

`$session->declarations()` reads captured signatures and class metadata without autoloading. Function, method, class, property, and constant metadata carry the raw `docComment` text (an empty string when absent), so integrations can read annotations such as `@global wpdb $wpdb` themselves. Deriver never interprets PHPDoc, and doc comments do not change analysis results.

Queries, models, and result types are described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/).

## License

MIT License. See [LICENSE](LICENSE) for details.
