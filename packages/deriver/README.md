# Deriver

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/deriver.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/deriver)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-deriver-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Deriver returns the candidate values of a selected PHP expression or definition without running application files or their autoloaders. It opens references backwards into their definitions and inputs, retaining alternatives as a shared dependency graph. Expansion stops when a candidate is concrete, a dependency cannot be opened, or a configured limit is reached. An unresolved dependency leaves a partial expression with its known operands intact.

## Requirements

- PHP 8.1+ with JSON and Tokenizer, on a 64-bit runtime
- Captured source targeting PHP 8.3 semantics; the host version does not select the target

```bash
composer require k-kinzal/deriver
```

## Deriving candidates

```php
use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;

$input = new ProjectInput([new SourceFile('app.php', <<<'PHP'
<?php
function query($table) {
    if ($table === null) {
        $table = 'default_table';
    }
    return 'SELECT * FROM ' . $table;
}
PHP)]);
$session = (new Analyzer())->open($input);
$candidates = $session->derive(new ReturnQuery('query'));

foreach ($candidates as $candidate) {
    // type is 'analyzed' or 'partials'; type_name is the inferred PHP type.
    // result is a native value when concrete, otherwise a Term.
    // term provides a uniform value representation; evidence retains the derivation.
    var_dump($candidate->type, $candidate->result);
}
echo $candidates->toJson();
```

This query retains both `SELECT * FROM default_table` and a concatenation with the unresolved formal parameter `table`. A formal parameter with no captured caller is unresolved, even when its declaration has a default. At an actual call that omits the argument, the default applies.

Every candidate has its own nonempty `evidence` list. Equal values share one candidate record, while their different derivations remain separate evidence alternatives. Concrete values alone do not prove that a runtime execution reaches them: Deriver collects the origins in the captured source and supplied models.

## Selecting a target

- `ReturnQuery('name')` selects a function or method's return value.
- `ParameterQuery('name', 'parameter')` selects a formal parameter.
- `ValueQuery($reference)` selects an expression. Obtain the reference with `$session->expression($path, $start, $end)`, using exact, zero-based byte offsets with an exclusive end.
- `callsTo('query')` returns call observations; `$site->argument(0)` selects an argument and `$site->beforeInvocation()` identifies its observation point.
- `TupleQuery` selects related values at one observation point, preserving their common choices.
- `StateQuery` selects storage at a specified point.

```php
use Deriver\Query\TupleQuery;

$queries = [];
foreach ($session->callsTo('query') as $site) {
    $queries[] = new TupleQuery($site->beforeInvocation(), [
        'table' => $site->argument(0),
    ]);
}
$results = $session->deriveTogether($queries)->results;
```

`deriveMany()` and `deriveTogether()` return results in request order and share dependency evaluations. Separate queries do not imply correlation; use a tuple for that purpose. Malformed source and invalid target ranges raise `InvalidInputException`.

To bind a specific input, supply an entrypoint:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Value\Term;

$candidates = $session->derive(new ReturnQuery('query', QueryScope::fromEntrypoints([
    new EntryPoint('query', [Term::constant('users')]),
])));
echo $candidates->candidates[0]->result; // SELECT * FROM users
```

## Replacing functions and expressions

Rules select an implementation before expanding its inputs. A constant replacement for `count` therefore does not derive the expression passed to it:

```php
use Deriver\Model\Expansion\Rule;
use Deriver\Project\Configuration;

$configuration = new Configuration(expansionRules: [
    Rule::constantFunction('count-one', '1', 'count', Term::constant(1)),
]);
$session = (new Analyzer())->open($input, $configuration);
```

A `Rule` callback receives a `Request` and can demand only the operands it needs through `input($key)`. Return `null` to decline and continue normal expansion; return a residual `Term` to record an unsupported selected case. `constantExpression()` replaces a syntax operation such as `binary` with operator `+`. Rules can also select an exact source path and byte range. Priorities resolve competing matches; duplicate identities or selectors at equal priority are rejected. Change a rule's version when its semantics change: identity and version are part of the snapshot manifest and replay contract.

For signatures, reference effects, constructor initialization or model state, the existing `CallModel`/`DemandModel` plan API remains available. A selected plan replaces the source implementation for all its outputs. Model callbacks are trusted host code; their exceptions become `ModelContractException`.

## Bounds and partial results

`Budget` controls reference depth, recursion, loop expansion, work, value nodes, evidence nodes and candidate enumeration. `maxCandidates` includes the final partial record retaining any unenumerated alternatives. Choices remain nested in the shared graph; independent branches do not require materializing their full Cartesian product. Reaching a limit preserves a residual with its stopping reason and the known expression structure.

Set `Configuration(resources: new ResourceLimits(seconds: 2.0))` for a cooperative time limit. Source capture is separately bounded by `sourceLimits`. Parsing, trusted callbacks and individual PHP operations cannot be preempted; use a separate process for a hard deadline. Resource-interrupted results are not cached as completed dependencies.

Global inputs can be supplied through `Configuration::$environment`, for example `'global:table_prefix' => Term::constant('wp_')`. Static locals with no known invocation history remain partial; an explicit `'static:App\\counter:n'` environment value or a known call sequence supplies that history. Target constants and conversion settings come from `TargetProfile` and the environment, never implicitly from the analyzing process. Float-to-string conversion needs an explicit `TargetProfile(floatPrecision: 14)`.

Within a session, matching dependency expansions share a bounded cache. `release()` clears session-owned results and evaluations. Caller-owned candidates and their evidence remain usable and serializable after release. `explain($result->reference)` requires retaining the result object. The declaration index and compiled graphs have the session's lifetime.

## Evidence and revalidation

Normal JSON is an array of candidate records with exactly `type`, `type_name`, `result`, and `evidence`. Partial expressions and non-JSON-native PHP values use a lossless value graph. The [candidate schema](resources/schema/candidates-v2.json) documents the format. Secret values remain redacted unless `toJson(includeSecrets: true)` is requested explicitly.

Each evidence alternative owns a derivation root, a context root, all referenced DAG nodes, and a snapshot manifest. Nodes describe definitions, argument binding, operations, choices, calls, models and expansion stops. Source locations retain the file hash, byte range and line/byte-column coordinates. `$candidates->forCaller('callerName')` projects the set by caller context without choosing one value.

`Deriver\Analysis\Candidates\Replay::verify($json, $input, $configuration, $query)` rederives the candidate set from captured bytes and compares the complete export. It detects changed candidates, omitted derivations and changed source or model manifests. It is deterministic revalidation by this analyzer, not an independent proof of PHP semantics. Keep the source bytes, query, target profile and versioned rule implementations with an exported result.

See [candidate dependency guarantees](docs/candidate-dependencies.md) for correlation, storage, partial expressions and the scope of verification.

## Migration from execution-shaped results

`Analyzer::open()->derive()` now returns `CandidateCollection`. Iterate its candidates or read `candidates`; replace `normalOutcomes[*]->values['return']` with each candidate's `result` or `term`. Inspect each candidate's evidence instead of a global `frontiers` or `candidateGraph` field. Normal JSON no longer includes execution states, normal/exceptional outcomes or an assessment wrapper.

Applications intentionally using ordered execution analysis can construct `Deriver\Analysis\ExecutionSession($input, $configuration)` explicitly. That API retains `DerivationResult` and `ExecutionResultSet`; `Configuration::forExecution()` and the `analysisContract` option have been removed. The candidate engine never switches to execution analysis automatically.

## License

MIT License. See [LICENSE](LICENSE) for details.
