# Integrating a value catalog

Deriver is an algorithmic PHP analyzer. It follows dependencies backwards from a query and propagates values, effects, and constraints forwards through captured source. It does not promise complete evaluation of every application. Keep symbolic fragments, alternative values, exceptional outcomes, and unresolved dependencies in a catalog instead of converting every incomplete result to an empty list.

The supported language target is PHP 8.3, independently of the host runtime. PHP 8.4 property hooks require a different language target; a model cannot repair a file that fails syntax validation.

## Choose the execution scope

A symbolic query asks about valid inputs to the selected callable. It does not invent callers, constructor arguments, or a history of arbitrary method calls. Closure captures in that scope are symbolic inputs. To obtain actual captured values, promoted property values, and values assigned by preceding methods, supply the application entries that construct and invoke those objects:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;

$call = $session->callsTo('query')[0];
$result = $session->derive(new ValueQuery(
    $call->argument(0),
    scope: QueryScope::fromEntrypoints([new EntryPoint('runApplication')]),
));
```

`$session->declarations()->symbols()` lists captured callable identities, including scripts and lexical closures. `callsTo('*')` inventories statically named call sites, with their containing callable in `Observation::$callable`. These facts allow an integration to select entries or build a candidate caller graph. They are not a complete dynamic call graph: variable function names, callbacks, inheritance, and external callers require evaluation or explicit models. A callable with no indexed caller is not necessarily a runtime entrypoint.

A query inside a loop observes successive visits, subject to the analysis budget. A finite loop can therefore produce several values from one source call site. Unknown iteration counts still require approximation. Do not interpret `Budget` as a fixed number of iterations or map another analyzer's depth/step limits directly onto it.

Conditions are evaluated. Impossible branches are excluded and repeated conditions stay correlated. PHP leaves some operand orders unspecified; Deriver explores both orders for interacting binary operands, preserving distinct effects and coalescing equivalent resulting states. Budget exhaustion remains explicit. See the PHP manual on [evaluation order](https://www.php.net/manual/en/language.operators.precedence.php).

## Supply external state explicitly

An unset variable in a captured script follows PHP's undefined-variable behavior. Deriver does not assume that every script has been included from an unspecified caller. Declare externally supplied globals in `Configuration::$environment`:

```php
use Deriver\Project\Configuration;
use Deriver\Value\Term;

$configuration = new Configuration(environment: [
    'global:db' => Term::parameter('application.database', 'Database'),
    'global:_GET' => Term::fromNative(['table' => 'users']),
    'constant:TABLE_PREFIX' => Term::constant('app_'),
]);
```

These inputs are available to scripts, `global $db`, and superglobal reads. Supply source or stub declarations and call models for the corresponding classes. A type bound permits known implementations; a non-final class can still have an unresolved subclass alternative.

PHPDoc annotations and framework-specific globals are integration policy. A provider can translate trusted `@global`/`@var` information or a framework contract into these explicit inputs; Deriver does not implicitly trust every annotation as runtime truth.

An unknown string conversion or offset operation invalidates reachable effects without discarding unrelated local parameters. An unresolved symbol-table write has a wider effect: unknown variable-variable assignments, `include`, `eval`, and unmodeled `extract` invalidate local values, including subsequently encountered names. Known variable-variable names use ordinary storage and alias rules. Concrete `define()` names are stored in analysis memory, not the host process; dynamic definitions remain an explicit boundary.

## Inspect a receiver with its arguments

An observation exposes `operation` and an optional `receiver` expression. Derive the receiver and arguments together at the invocation point to retain their correlation and avoid reevaluating a receiver with side effects:

```php
use Deriver\Query\TupleQuery;

$call = $session->callsTo('query')[0];
if ($call->receiver !== null) {
    $result = $session->derive(new TupleQuery($call->beforeInvocation(), [
        'receiver' => $call->receiver,
        'sql' => $call->argument(0),
    ]));
}
```

The receiver's value distinguishes a known object class, a type bound, and an unresolved receiver. A nullable receiver can also produce a null alternative or an exception, depending on the operation and path.

For named functions, `callsTo()` resolves namespace fallback against captured declarations and registered models. A namespaced declaration shadows the global function. Method observations remain candidate sites indexed by their written method name; `Observation::$target` alone does not identify the runtime method implementation. Use the receiver and evaluation result for classification. Models receive the selected implementation in `CallDescription::$symbol`.

## Describe application and framework semantics with models

Use a `CallModel` when application behavior is absent, intentionally abstracted, or depends on a framework contract. A model returns a declarative `SemanticPlan`; Deriver evaluates its arguments, state operations, and effects with the same machinery as source code.

The model selection context exposes:

| Member | Meaning |
| --- | --- |
| `symbol` | Selected implementation identity |
| `receiverType` | Called class for a static call, including late static binding; receiver class information for an instance call |
| `source` | Captured call-site `SourceRef`, suitable for associating a prepared handle with its preparation site |
| `declarations` | Read-only captured class and signature metadata |
| `arguments` | Formal bindings during preparation, evaluated bindings during invocation |

To replace a source callable while retaining its argument names, reference modes, variadics, and default expressions, set both `replaceSource: true` and `useSourceSignature: true` on `ModelDescriptor`. If no source declaration exists, the descriptor's explicit signature is used. Source default expressions still run through the evaluator; an unevaluated metadata value is not substituted for them.

```php
use Deriver\Model\ModelDescriptor;

$descriptor = new ModelDescriptor(
    'application.query',
    '1',
    'Repository::query',
    replaceSource: true,
    useSourceSignature: true,
);
```

`declarations()->class('User')` exposes the class name, parent, interfaces, direct traits, composed methods, properties, constants, and class flags without exposing parser nodes. Inherited metadata remains on the parent; follow `parent` when a model needs inherited members. A property's absent default is PHP `null` in the metadata API, whereas a literal null default is `Term::constant(null)`. Nonliteral initializers remain `UNEVALUATED_INITIALIZER` rather than being guessed from arbitrary methods. `declarations()->signature($symbol)` follows the same rule for inspecting defaults.

Application source declarations take precedence over `SourceFile(..., declarationsOnly: true)` stubs. Duplicate application declarations remain invalid. Models still need explicit replacement precedence; loading a stub does not implicitly authorize replacing source behavior.

Laravel, WordPress, and other integrations should express their dispatch and state rules through these contracts. Deriver does not infer an Eloquent table by guessing across unrelated assignments or by executing framework autoloaders. A captured PHP shim is also possible when its own language operations are supported or modeled.

## Interpret partial results and unresolved dependencies

A concrete fragment does not by itself prove a closed search. For example, an unmodeled call before `query('SELECT 1')` may throw or change global state, affecting whether that observation is reachable. Such a boundary can remain relevant even though it does not alter the literal argument. Keep value precision, reachability, and search closure separate; do not delete frontiers solely because the selected value is constant.

Finite array reads with an unknown key retain candidate slots and the missing-key alternative. Superglobal reads retain their external input dependency. `implode()` over a known array shape can retain known string fragments around symbolic elements. A directly typed enum parameter can enumerate captured cases within the partition budget. These operations need not produce a single concrete value to be useful.

Built-in models cover a bounded set of target semantics. The construction and string models include `array_fill`, `str_repeat`, `strval`, `intval`, `vsprintf`, `ucfirst`, `lcfirst`, `ltrim`, and `rtrim`. Allocation limits and unsupported inputs leave explicit boundaries. This is not a claim of complete standard-library coverage. Environment-sensitive formatting such as `date`, recursive `json_encode` behavior, array-pointer operations such as `end`, and reflection helpers such as `method_exists`/`class_uses` still require suitable explicit models or further core support. Class metadata is available directly to framework models. Float-to-string conversion retains `FLOAT_STRING_CONFIGURATION` when the target formatting configuration is unresolved; the host process's precision is not used as an application fact.

`echo`, standalone blocks, and `exit` have control-flow support; `exit` prevents later observations, including through calls and `finally`. `goto` remains unsupported. An unsupported operation must remain visible in the result rather than silently certifying the following statements.

A catalog migration should preserve these distinctions in its own report format. An old label such as `conditions: not-evaluated`, an unconditional “all branches” promise, or matching sinks only by spelling does not describe Deriver's execution semantics. Adapters for another analyzer's model API and reporting format belong in that integration.
