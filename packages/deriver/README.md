# Deriver

Deriver finds value candidates for selected PHP expressions without executing application files or autoloaders. It starts at the observation, follows definitions, callers and property writes backwards, and simplifies the resulting dependency expression.

A candidate can be a concrete value or an expression containing identifiable inputs. `5 + Ref($amount)` is a normal result. An unavailable input or an expansion limit leaves the surrounding expression intact and records what remains unresolved. Unrelated calls do not have to finish, or even have source available, before an observation has candidates.

## Requirements and installation

- PHP 8.1+ on a 64-bit runtime, with JSON and Tokenizer.
- Captured source targets PHP 8.3 semantics. The host version does not change this target.

```sh
composer require k-kinzal/deriver
```

## Select an observation

```php
use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ValueQuery;

$input = new ProjectInput([
    new SourceFile('app.php', <<<'PHP'
<?php
function report(PDO $pdo, string $table): void {
    $unused = heavy();
    $sql = 'SELECT * FROM ' . $table;
    $pdo->query($sql);
}
function caller(PDO $pdo): void {
    report($pdo, 'users');
    report($pdo, 'orders');
}
PHP),
]);
$session = (new Analyzer())->open($input);

$site = $session->callsTo('query')[0];
$result = $session->derive(new ValueQuery($site->argument(0)));
foreach ($result->normalOutcomes as $candidate) {
    echo $candidate->values['value']->native(), "\n";
}
// SELECT * FROM users
// SELECT * FROM orders
```

Neither `heavy()` nor `PDO::query()` is expanded for this observation. Without `caller()`, the result is one concatenation expression with a reference to `report::$table`. That reference carries its source, scope, context, type and reason.

`ReturnQuery('functionName')` selects return expressions. `StateQuery` selects a local before or after an instruction. `TupleQuery` selects related expressions together, preserving branch and caller choices:

```php
use Deriver\Query\TupleQuery;

if ($site->receiver !== null) {
    $result = $session->derive(new TupleQuery($site->beforeInvocation(), [
        'receiver' => $site->receiver,
        'sql' => $site->argument(0),
    ]));
}
```

## Candidate scope

Candidates come from captured sources and registered models. Known callers contribute actual arguments; an arbitrary external caller is not automatically added to a known caller set. Missing inputs, unavailable source and unresolved dynamic calls remain explicit dependencies.

A property contributes its declaration initializer and corresponding writes in ordinary methods. For a class with `$table = 'ledger'` and an `archive()` method assigning `'ledger_archive'`, a query reading the property includes both values. This collection does not require a history proving that `archive()` ran. Local overwrites, declaration identity and receiver storage still matter.

Use `QueryScope::fromEntrypoints()` to supply explicit bindings. Bindings also flow backwards through callers and lexical captures:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Value\Term;

$scope = QueryScope::fromEntrypoints([
    new EntryPoint('report', ['table' => Term::constant('users')], symbolicArguments: true),
]);
$result = $session->derive(new ValueQuery($site->argument(0), scope: $scope));
```

These are source binding contexts. They do not certify reachability from application entries. See [candidate analysis](docs/candidate-analysis.md) for scope and dependency details.

## Read complete and partial results

- `normalOutcomes` contains small enumerated sets of named values. Each value is an immutable `Term`.
- `candidateGraph` retains choices, shared structure and residual operations, including choices too large to enumerate.
- `frontiers` identifies unresolved dependencies and expansion boundaries. Inspect `residual`, `at`, `knownDependencies` and `code`.
- `assessment.closure` distinguishes a completed origin search from stopped expansion. A closed search can still contain external inputs.
- `contract` is `candidates`, `reachability` is `not-assessed`, and coverage is `source-candidates`.

Use `Term::isConcrete()` before `native()`. `definite()` is a convenience for a single concrete outcome without frontiers, exceptions or project diagnostics. Under this contract it does not prove runtime reachability or exhaustiveness outside the captured source world. Do not discard other results merely because `definite()` returns `null`.

`toJson()` serializes the graph in the shared `values` table; `candidateGraph` references its root. Confidential values are redacted by default. Candidate multiplicity, external inputs and interrupted expansion are separate facts.

## Bound expansion and retention

```php
use Deriver\Project\Configuration;
use Deriver\Query\Budget;

$configuration = new Configuration(candidateCacheEntries: 2048, retainedResults: 32);
$session = (new Analyzer())->open($input, $configuration);
$site = $session->callsTo('query')[0];
$result = $session->derive(new ValueQuery($site->argument(0), budget: new Budget(maxDepth: 2)));
$session->release();
```

`maxDepth` defaults to 64 and counts reference-origin steps per dependency branch. Constants and syntax within an already exposed expression do not consume this depth. At depth zero, a variable stays a typed `deferred` reference; literal observations remain concrete. Stopping one dependency does not erase constants or siblings.

`partitions` bounds eager choice products; the remaining product stays in the graph with `ENUMERATION_LIMIT`. `recursion` and `iterations` bound recursive and loop-carried definitions. Logical work limits and cooperative `ResourceLimits` leave residuals with stopping reasons. Source capture has separate `SourceLimits`. Individual parser, model and scalar operations are not preemptible.

The dependency cache is session-local and bounded by entry count. It separates scope, call context, depth and enumeration/recurrence bounds, and excludes interrupted dependencies. The default result working set retains at most 32 small results. Set either retention capacity to zero to disable it. `release()` clears session-owned evaluations and recent results; caller-owned immutable results remain usable and explainable. Captured source indexes have the session's lifetime.

`deriveMany()` and `deriveTogether()` share dependency results between queries. Each candidate query has its own budget; batch statistics are per query. Use `TupleQuery` when values must retain correlation. For streaming consumption, call `derive()` in a loop and release results you no longer need.

## Replace a call's derivation

Register a `CallModel` that returns a declarative `SemanticPlan`. An applied model replaces source expansion even when source is present. Plan parameter and state expressions request only their dependencies. A constant plan does not evaluate unused arguments or expand the source body.

Implement `DemandModel` when selecting the plan requires actual inputs. Its `demand()` returns the parameter names needed by `describe()`. Ordinary `CallModel::describe()` receives formal handles. An explicit decline permits source expansion; a handled but partial plan does not fall back to source. Registered state slots use the same dependency mechanism, including receiver-specific writes.

See [migration](docs/migration.md) for model precedence and result compatibility changes.

## Other APIs and validation

`callsTo()` and `declarations()` inspect captured metadata without running application code. `comments()` exposes raw PHPDoc; it does not infer types from annotations. `explain()` uses a retained result's dependency evidence. The [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/) describes query, model and result types.

The earlier analysis contract remains available through `new Configuration(analysisContract: 'execution')` or `Configuration::forExecution()`. It is an explicit alternative for ordered execution state and reachability questions. See [execution analysis](docs/execution.md).

[Validation and measurements](docs/validation.md) distinguish acceptance tests, language regressions, benchmark conditions and applications not verified by this change.

## License

MIT. See [LICENSE](LICENSE).
