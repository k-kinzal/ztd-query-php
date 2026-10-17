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

## Selecting files

Use `Finder` to select paths before analysis:

```php
use Deriver\Finder;

$files = (new Finder())
    ->in(__DIR__ . '/src')
    ->include('**/*.php')
    ->exclude('**/vendor/**', '**/*Test.php')
    ->files();
```

`files()` returns sorted, unique absolute path strings. It does not read, parse or execute file contents. `in()` accepts one or more local directories and can be called repeatedly. Overlapping roots do not duplicate results. Linked files and directories below a root are skipped.

Include and exclude patterns match the entire path relative to each search root, with case-sensitive matching:

- `*` matches within one path component: `src/*.php` selects files directly in `src`.
- `?` matches one character within a component.
- `**` crosses directories: `src/**/*.php` includes PHP files directly in `src` and in its descendants.
- Multiple includes are combined. Exclusions always take precedence. An excluded directory skips its whole subtree; `**/vendor/**` excludes vendor directories at any depth.

With no include patterns, every regular file is selected. There are no implicit exclusions. With no `in()` call, the search root is the current directory. An unmatched pattern returns no files; invalid roots and empty or non-relative patterns raise `InvalidInputException`. The returned paths can also be assembled directly or supplied by another finder.

## Deriving candidates

For an application file containing:

```php
<?php
function query($table) {
    if ($table === null) {
        $table = 'default_table';
    }
    return 'SELECT * FROM ' . $table;
}
```

Pass the selected paths and a query to `Analyzer::analyze()`:

```php
use Deriver\Analyzer;
use Deriver\Query\ReturnQuery;

$analyzer = new Analyzer();
$candidates = $analyzer->analyze($files, new ReturnQuery('query'));

foreach ($candidates as $candidate) {
    // type is 'analyzed' or 'partials'; type_name is the inferred PHP type.
    // result is a native value when concrete, otherwise a Term.
    // term provides a uniform value representation; evidence retains the derivation.
    var_dump($candidate->type, $candidate->result);
}
echo $candidates->toJson();
```

`analyze()` reads exactly the supplied files on each call, without executing them. It accepts file paths; directory traversal and wildcard expansion belong to the finder. Changed contents are captured on the next call, even when the same `Analyzer` is reused. Duplicate paths, unreadable files and malformed PHP raise `InvalidInputException`.

This query retains both `SELECT * FROM default_table` and a concatenation with the unresolved formal parameter `table`. A formal parameter with no captured caller is unresolved, even when its declaration has a default. At an actual call that omits the argument, the default applies.

Every candidate has its own nonempty `evidence` list. Equal values share one candidate record, while their different derivations remain separate evidence alternatives. Concrete values alone do not prove that a runtime execution reaches them: Deriver collects the origins in the captured source and supplied models.

## Selecting a target

- `ReturnQuery('name')` selects a function or method's return value.
- `ParameterQuery('name', 'parameter')` selects a formal parameter.

`ValueQuery`, `TupleQuery` and `StateQuery` operate on snapshot-specific references supplied by the lower-level analysis contracts. `ReturnQuery` and `ParameterQuery` can be constructed directly from names before calling `analyze()`.

To bind a specific input, supply an entrypoint:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Value\Term;

$candidates = $analyzer->analyze($files, new ReturnQuery('query', QueryScope::fromEntrypoints([
    new EntryPoint('query', [Term::constant('users')]),
])));
echo $candidates->candidates[0]->result; // SELECT * FROM users
```

## Replacing functions and expressions

Rules select an implementation before expanding its inputs. A constant replacement for `count` therefore does not derive the expression passed to it:

```php
use Deriver\Project\Expansion\Rule;
use Deriver\Project\Configuration;
use Deriver\Value\Term;

$configuration = new Configuration(expansionRules: [
    Rule::constantFunction('count-one', '1', 'count', Term::constant(1)),
]);
$analyzer = new Analyzer($configuration);
$candidates = $analyzer->analyze($files, new ReturnQuery('query'));
```

A `Rule` callback receives a `Request` and can demand only the operands it needs through `input($key)`. Return `null` to decline and continue normal expansion; return a residual `Term` to record an unsupported selected case. `constantExpression()` replaces a syntax operation such as `binary` with operator `+`. Rules can also select an exact source path and byte range. Priorities resolve competing matches; duplicate identities or selectors at equal priority are rejected. Change a rule's version when its semantics change: identity and version are part of the snapshot manifest and replay contract.

Models of PHP functions and framework calls are part of the library, not of its public API: the standard library models are registered for every session, and `Configuration` carries no model objects. Expansion rules are the supported way to replace a function or expression from the outside. Failures inside trusted model code surface as `ModelContractException`.

## Bounds and partial results

`Budget` controls reference depth, recursion, loop expansion, work, value nodes, evidence nodes and candidate enumeration. `maxCandidates` includes the final partial record retaining any unenumerated alternatives. Choices remain nested in the shared graph; independent branches do not require materializing their full Cartesian product. Reaching a limit preserves a residual with its stopping reason and the known expression structure.

Set `Configuration(resources: new ResourceLimits(seconds: 2.0))` for a cooperative time limit. Source capture is separately bounded by `sourceLimits`. Parsing, trusted callbacks and individual PHP operations cannot be preempted; use a separate process for a hard deadline. Resource-interrupted results are not cached as completed dependencies.

Global inputs can be supplied through `Configuration::$environment`, for example `'global:table_prefix' => Term::constant('wp_')`. Static locals with no known invocation history remain partial; an explicit `'static:App\\counter:n'` environment value or a known call sequence supplies that history. Target constants and conversion settings come from `TargetProfile` and the environment, never implicitly from the analyzing process. Float-to-string conversion needs an explicit `TargetProfile(floatPrecision: 14)`.

An `Analyzer` retains bounded parsing and lowering caches across calls. Each `analyze()` call creates a fresh source snapshot and query session. Returned candidates own their evidence and remain usable and serializable after the call or after the analyzer is released.

## How a query is answered

`analyze()` first captures the file contents, validates and parses them, indexes declarations, registers models and fixes the snapshot identity. It then selects the query target, expands its dependencies and collects candidate values. Application code and autoloaders are never executed.

## Evidence and revalidation

Normal JSON is an array of candidate records with exactly `type`, `type_name`, `result`, and `evidence`. Partial expressions and non-JSON-native PHP values use a lossless value graph. The [candidate schema](resources/schema/candidates-v2.json) documents the format. Secret values remain redacted unless `toJson(includeSecrets: true)` is requested explicitly.

Each evidence alternative owns a derivation root, a context root, all referenced DAG nodes, and a snapshot manifest. Nodes describe definitions, argument binding, operations, choices, calls, models and expansion stops. Source locations retain the file hash, byte range and line/byte-column coordinates. `$candidates->forCaller('callerName')` projects the set by caller context without choosing one value.

`Deriver\Replay::verify($json, $input, $configuration, $query)` rederives the candidate set from captured bytes and compares the complete export. It detects changed candidates, omitted derivations and changed source or model manifests. It is deterministic revalidation by this analyzer, not an independent proof of PHP semantics. Keep the source bytes, query, target profile and versioned rule implementations with an exported result.

## Migration from execution-shaped results

The public API is `Finder`, `Analyzer`, `AnalysisSession`, `Replay`, `ExecutionSession`, the inputs under `Project` and `Query`, the outputs under `Result`, `Reference`, `Evidence` and `Diagnostic`, `Value\Term`, `Value\Projection`, and the exceptions. Classes moved to match that surface:

| Former name | Name |
| --- | --- |
| `Deriver\Analysis\ExecutionSession` | `Deriver\ExecutionSession` |
| `Deriver\Analysis\Candidates\Replay` | `Deriver\Replay` |
| `Deriver\Query\ResourceLimits`, `Deriver\Query\CancellationToken` | `Deriver\Project\ResourceLimits`, `Deriver\Project\CancellationToken` |
| `Deriver\Model\Expansion\Rule`, `Deriver\Model\Expansion\Request` | `Deriver\Project\Expansion\Rule`, `Deriver\Project\Expansion\Request` |
| `Deriver\Model\Metadata\*`, `Deriver\Model\Signature\*` | `Deriver\Project\Declaration\*` |
| `Deriver\Result\Candidates\Candidate`, `Deriver\Result\Candidates\CandidateCollection` | `Deriver\Result\Candidate`, `Deriver\Result\CandidateCollection` |
| `Deriver\Result\Evidence\Node` | `Deriver\Evidence\Node` |
| `Deriver\Result\Evidence\Alternative` | `Deriver\Result\Alternative` |
| `Deriver\Result\Frontier` | `Deriver\Diagnostic\Frontier` |
| `Deriver\Result\DerivationResult` and the other execution results | `Deriver\Result\Execution\*` |

`Configuration` no longer accepts `models`, `providers`, `domains`, `intrinsics` or `stateSlots`; those are contributed by the library. `ObservationProvider::queries()` receives a `CapturedProject`, the read-only view every session implements.


`Analyzer::open()` has been removed. Pass a list of file paths and a query to `Analyzer::analyze($files, $query)`, which returns `CandidateCollection`. Supply configuration through `new Analyzer($configuration)`. File discovery belongs to `Finder`; callers no longer need to construct a `ProjectInput` to analyze files. Iterate its candidates or read `candidates`; replace `normalOutcomes[*]->values['return']` with each candidate's `result` or `term`. Inspect each candidate's evidence instead of a global `frontiers` or `candidateGraph` field. Normal JSON no longer includes execution states, normal/exceptional outcomes or an assessment wrapper.

Applications intentionally using ordered execution analysis can construct `Deriver\ExecutionSession($input, $configuration)` explicitly. That API retains `DerivationResult` and `ExecutionResultSet`; `Configuration::forExecution()` and the `analysisContract` option have been removed. The candidate engine never switches to execution analysis automatically.

## License

MIT License. See [LICENSE](LICENSE) for details.
