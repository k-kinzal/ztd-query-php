# Models and providers

A call model contributes a signature and an ordered `SemanticPlan`. Source bodies and plans use the same argument binder, memory, control flow, and exception handling. Models describe an API's behavior without walking application syntax or executing application objects.

## Call models

Implement `CallModel::descriptor()` and `CallModel::describe()`. The descriptor supplies a stable ID, semantic version, target symbol, and `Signature`. Descriptors are captured at registration. They cannot change during a session.

Return one of these decisions:

- `ModelDecision::handled($plan)` supplies the call's semantics.
- `ModelDecision::declined()` lets an available source body supply them.
- `ModelDecision::unsupported($reason, $fallbackToSource)` identifies an unsupported case. Without an available permitted fallback, it produces a frontier with possible normal returns, exceptions, and mutable effects.

A model for `key(int $id): string` can return this plan:

```php
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Value\Term;

$plan = new SemanticPlan([
    Action::returns(Expression::binary(
        '.',
        Expression::literal(Term::constant('user:')),
        Expression::parameter('id'),
    )),
]);
```

Register the model in `Configuration::models` when opening an analysis session.

## Normalized inputs

`Signature` declares parameter names, types, defaults, variadic and reference arguments, surplus-argument behavior, and reference returns. The core enforces argument order, types, reference eligibility, and defaults. A plan parameter expression reads the resulting binding after those checks.

`CallDescription` supplies the selected symbol, signature, target profile, receiver type, `ArgumentBindings`, and the explicitly captured `Configuration::dependencyVersions` map. Each `BoundArgument` exposes its abstract value, supplied/omitted status, variadic status, and an optional formal `LocationRef` for a reference parameter. Named variadic keys remain intact. Unknown unpacking has a separate `remainder`.

Call preparation happens before evaluating arguments. During preparation, `arguments->evaluated` is `false`, values are symbolic parameter handles, and `supplied` is `null`. At invocation, it is `true` and values describe the completed argument expressions **before parameter type coercion**. Omitted literal defaults are available as metadata; argument-order errors are separate from values. Models cannot recursively demand evaluation through these handles.

Keep model selection and reference modes stable between preparation and invocation. Describe value-dependent behavior with `Action::choice()` and parameter expressions. Using `Term::native()` to decline every nonconstant argument would lose the behavior of valid symbolic calls. An applicable source fallback must agree with the model's argument reference modes.

## Expressions and actions

Expressions are immutable descriptions. `parameter()`, `receiver()`, `literal()`, `binary()`, `state()`, and `read()` cover bound values and storage. The expression constructor also exposes the validated `unary`, `cast`, `array-read`, `array-set`, `external`, and registered `intrinsic` operations. PHP operators use target semantics.

| Action | Behavior |
| --- | --- |
| `returns($value)` | Complete normally with a value |
| `throws($exception)` | Throw after retaining all completed effects |
| `write($slot, $value, $receiver)` | Update a registered abstract object slot |
| `assign($location, $value)` | Write a parameter, local result, slot, or array element |
| `alias($destination, $source)` | Rebind the destination to the source's reference cell |
| `returnReference($location)` | Return a cell; requires `Signature::byReference` |
| `invoke($result, $callable, $arguments, $byReference)` | Invoke synchronously through the core binder |
| `callback($result, $callable, $expressions)` | Shorthand for one synchronous positional invocation |
| `allocate($result, $class, $arguments)` | Allocate a fresh object and run its known constructor |
| `choice($condition, $yes, $no)` | Keep correlated guarded action sequences |
| `havoc($locations, $reason, $mayThrow)` | Mark listed storage and its reachable mutable values unresolved |

Actions execute in list order. A callback exception stops subsequent actions while preserving completed writes. Invocation resolves the target before evaluating arguments, and evaluates each argument once. Allocation applies source property defaults and constructor behavior, including exceptions.

An invocation's result is a value unless `byReference: true` explicitly retains its returned reference. Use local result names beginning with `@` to distinguish temporary plan bindings in examples. They are ordinary private bindings within that modeled invocation.

### References and arguments

`LocationRef::parameter('value')` names a normalized parameter or prior result. `LocationRef::state('example.slot')` names receiver state. `LocationRef::element($parent, $key)` selects an array element; omitting the key selects append storage. Locations do not expose mutable solver objects or cell identifiers.

`CallArgument` retains positional, named, and unpack modes. The callee's signature selects value or reference passing:

```php
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Plan\CallArgument;

$invoke = Action::invoke('@result', Expression::parameter('callback'), [
    new CallArgument(Expression::literal(Term::constant(4)), 'delta'),
    new CallArgument(LocationRef::parameter('value'), 'item'),
]);
```

For a modeled caller's reference parameter, declare `new Parameter('value', byReference: true)` and include `parameter:value` in the plan's write footprint when the plan writes or exposes that location. Copy parameters remain local. Callback effects on captured objects and references flow through the core automatically.

`havoc()` adds an `UNSUPPORTED_MODEL_CASE` frontier. It retains an exceptional alternative by default. Set `mayThrow: false` only when the API contract guarantees normal completion of that effect. Storing a callback does not run it. Deferred execution needs an explicit lifecycle model; unavailable execution effects can be represented by a declared havoc on the captured callback location.

## Abstract object state

Register each slot with `Configuration::stateSlots`:

```php
use Deriver\Project\Configuration;
use Deriver\Model\State\StateSlot;

$configuration = new Configuration(stateSlots: [
    new StateSlot('example.builder.table', 'string', Term::constant('')),
    new StateSlot('example.cache.entries', 'array', Term::array([]), clone: 'reset'),
]);
```

Slot names are domain-qualified. Types use the finite scalar/array/object type vocabulary, including unions. An incompatible initializer, invalid lifecycle mode, or repeated slot ID is a configuration error.

- `initial` applies separately to each new object. Omitting it supplies a symbolic state input. An existing external receiver starts with symbolic state, even when new objects have a concrete initializer.
- `clone: 'copy'` shallow-copies the current slot. Ordinary values become independent; explicit reference cells and nested object identities remain shared.
- `clone: 'reset'` uses the declared initializer for the clone.
- `invalidation: 'havoc'` invalidates state when an unavailable call can reach the receiver.
- `invalidation: 'preserve'` declares that such a call cannot change the slot. This is a trusted API invariant and part of snapshot identity.

Slots have separate storage from PHP properties. Reading a slot does not trigger `__get()`, and a PHP property with the same spelling cannot replace it. Type constraints remain active when a slot escapes by reference.

List direct slot reads in `SemanticPlan::reads` and writes/reference exposure in `SemanticPlan::writes`. Nested element updates also read the containing array. Unregistered slots and undeclared direct effects fail plan validation.

Use `Projection::stateSlot('example.builder.table')` with a `StateQuery` or `ValueQuery` to observe a slot without invoking a getter. A further path selects an entry inside the slot.

## Selection and versioning

Source implementations are preferred unless the model explicitly sets `replaceSource`. A signature-only external declaration can receive model semantics without replacing a source implementation.

Models matching one symbol need unambiguous precedence. Declare `replaces` for replacement intent, or distinct priorities. Equal maximal candidates and replacement cycles are configuration errors. Replacing a standard model requires its `php.<function>` ID in `replaces`. Registration order does not select the winner.

Change a model's version when its behavior changes. Model signatures, slot contracts, dependency versions, domains, intrinsics, and providers all contribute to the captured snapshot. For example, pass dependency versions from a captured lock-file export; Deriver does not execute Composer to discover them.

## Providers and external declarations

| Contract | Contribution |
| --- | --- |
| `DeclarationProvider` | Captured PHP declarations, generated source, or signature-only files |
| `DispatchProvider` | Target alternatives, conditions, and an explicit exhaustiveness contract |
| `EntryPointProvider` | Application entry invocations |
| `EnvironmentProvider` | Explicit symbolic or concrete environment values |
| `DomainProvider` | Registered abstract domains |
| `ObservationProvider` | Named queries built with the public session API |
| `RefinementModel` | Additional guaranteed predicates for supported branches |

A declaration provider returns `ProjectInput`. Use `SourceFile::declarationsOnly` when implementations are unavailable:

```php
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;

$declarations = new ProjectInput([
    new SourceFile('external.stub.php', <<<'PHP'
<?php
function external(int &$value): int {}
PHP, declarationsOnly: true),
]);
```

This records names, inheritance, visibility, parameter defaults, reference modes, and types. It does not interpret the empty stub body as a pure operation returning `null`. Without a model, the core binds arguments, evaluates omitted defaults, and retains unavailable body effects and both completions. Executable default expressions create fresh objects on each invocation. Top-level stub statements are not application entry scripts.

A provider's single dispatch target is not implicitly exhaustive. Complete dispatch requires the provider to declare that it has exhausted possible targets under its assumptions.

## Pure intrinsics and domains

Use `PureIntrinsic` for a pure transformation over immutable terms. Its descriptor supplies an operation name, version, arity, and operand dependencies. Preserve dependencies and partial information for unknown inputs.

Use `AbstractDomain` when an extension needs its own inclusion, join, widening, and projection operations. `DomainFact` holds an immutable `Term` representation. The normal versioned value graph serializes that representation, including sharing, binary strings, numeric values, and confidentiality; a plugin does not supply executable serialization hooks. Registered domain and operation versions identify its semantics.

`DomainLaws` checks finite samples. These checks supplement the author's semantic contract; they cannot prove arbitrary plugin code correct. A failed inclusion or widening contract cannot establish a closed result. Analysis `join` contains alternatives; application composition or override belongs in a separate intrinsic.

## Trust and validation

Models and providers are trusted PHP extensions running inside the analyzer. Application files remain data. The SDK does not download extensions or sandbox their implementation.

Failures in trusted model implementations propagate to the caller as their original exception, preserving the stack and cause. This includes programming errors such as `TypeError`. Operational failures may use `Deriver\Exception\ModelException`. They are not converted to unknown values, unsupported decisions, or exceptions in the analyzed application.

An invalid plan or domain operation raises `Deriver\Exception\ModelContractException`. An unsupported application operation instead uses `ModelDecision::unsupported()` or a declared residual. These cases have distinct handling: fix the model for a contract violation, or inspect the result's frontier for missing analysis semantics. Plans have finite registration limits and use the same query budgets as source. Model contract fixtures cover [locations and invocation](../tests/ModelContract/LocationContractTest.php), [slot lifecycles](../tests/ModelContract/StateSlotContractTest.php), and [external declarations](../tests/ModelContract/DeclarationContractTest.php).

## Native scalar coercion and overloads

Built-in models use the PHP 8.3 internal-function rule for explicitly supplied null in a non-nullable scalar parameter. Weak calls coerce it to the scalar default and record the target deprecation as `PHP_WARNING`; strict calls retain the type error. Source functions and third-party model signatures keep ordinary source binding rules.

`count()` accepts arrays and `Countable` receivers. A known source `Countable::count(): int` body runs with its receiver state and exceptions. An invalid mode is rejected before the method runs. An untyped count implementation or unresolved count mode retains `UNSUPPORTED_MODEL_CASE`.

The ordinary one-argument `is_callable()` query uses the captured declaration world. Its `syntax_only` and reference-output overloads currently retain `UNSUPPORTED_MODEL_CASE`, an opaque result, and possible updates to the reference output. They are accepted by the signature and are not rejected as surplus arguments.
