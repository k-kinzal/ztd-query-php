# Capability manifest

This manifest describes the initial **PHP 8.3, 64-bit semantic profile** and standard model revision `1`. The library host supports PHP 8.1–8.5; running it on a newer host does not change the target. The [language guide](language.md) explains individual rules. The [acceptance matrix](acceptance.md) links the design requirements to executable checks.

“Supported” means the listed forms have transfer rules and regression evidence. It does not promise exact results for arbitrary combinations or inputs. Logical budgets, open dispatch, partial inputs, and unsupported operations can introduce frontiers. Always inspect each result's assessment, normal outcomes, exceptional outcomes, and project diagnostics.

## Language and state

| Surface | Supported forms | Evidence and boundaries |
| --- | --- | --- |
| Scalar expressions | Constants, parameters, scalar operators, target integer conversion, symbolic concatenation, Boolean conditions, short circuit evaluation | [Boundary tests](../tests/Semantic/BoundarySemanticsTest.php), [integer corpus](../tests/Fake/Programs/IntegerPrograms.php), [increment corpus](../tests/Fake/Programs/IncrementPrograms.php). Float string formatting dependent on runtime configuration stays opaque. Unspecified operand order is explicit. |
| Control flow | If/elseif, switch fallthrough, match, loops, break/continue levels, return, throw, catch, finally | [Concrete tests](../tests/Semantic/ConcreteSemanticsTest.php), [generated corpus](../tests/Fake/GeneratedPrograms.php). General loops use widening; interrupted work retains residual completions. |
| Arrays and offsets | Ordered keys, union, spread, copy semantics, reference elements, string offsets, captured `ArrayAccess` methods | [Offset corpus](../tests/Fake/Programs/OffsetPrograms.php). Unknown offset protocols keep possible effects and throws. |
| Iteration | Known array values and reference iteration, deletion, append, replacement, retained final reference, unset | [Iteration corpus](../tests/Fake/Programs/IterationPrograms.php). Arbitrary iterator protocols require a supported body or model. |
| Calls | Source functions, methods, constructors, defaults, named arguments, variadics, array unpack, reference arguments and returns | [Argument corpus](../tests/Fake/Programs/ArgumentPrograms.php), [method tests](../tests/Semantic/MethodSemanticsTest.php). Unknown argument unpack sequences and unavailable bodies retain boundaries. Array construction preserves symbolic array unpack; unresolved Traversable execution retains effects and exceptional exits. |
| Objects | Allocation identity, aliases, properties, typed/readonly properties, shallow clone and clone hooks, global/static state | [Property tests](../tests/Semantic/PropertySemanticsTest.php), [mutation tests](../tests/Semantic/PropertyMutationTest.php), [state differential tests](../tests/Differential/StateAgreementTest.php). Destructor lifetime is explicitly unsupported. |
| Closures and callables | Value/reference captures, bound and static closures, first-class callable acquisition and visibility, known callable pairs | [Callable corpus](../tests/Fake/Programs/CallablePrograms.php). Unresolved callable validation can require autoload or visibility effects and stays a boundary. |
| Inheritance and traits | Captured method visibility and inheritance, magic method dispatch, trait precedence/adaptations and consuming class scope | [Method tests](../tests/Semantic/MethodSemanticsTest.php), [generated corpus](../tests/Fake/GeneratedPrograms.php). An incomplete hierarchy retains open dispatch. |
| Constants and enums | Captured class/interface/trait constants, visibility and typed constants, enum case identity and backing expressions | [Constant corpus](../tests/Fake/Programs/ConstantPrograms.php). Native enum `cases`, `from`, and `tryFrom` have no bundled model. Object-based dynamic class-constant access has a language boundary. |
| String protocols | Explicit string casts and concatenation invoke captured `__toString` with effects, exceptions, and its implicit return contract | [Conversion corpus](../tests/Fake/Programs/ConversionPrograms.php). Weak argument/property/return coercion, loose object comparisons, and other object casts retain explicit boundaries. |
| Exceptions | Normal and exceptional completion, pre-throw state, pending completion through finally, captured throwable subclasses | [Observation tests](../tests/Semantic/ObservationSemanticsTest.php), [serialization tests](../tests/Integration/SerializationContractTest.php). Native stack details and generated messages remain opaque. |
| Queries and reports | Return, value, state, tuple, projections, branch guards, evidence, reachable storage including aliases/cycles | [Acceptance tests](../tests/Semantic/AcceptanceContractTest.php), [schema tests](../tests/Integration/SchemaContractTest.php). Storage identities belong to one alternative and snapshot. |

Generators, fibers, dynamic includes/eval, arbitrary runtime class loading, reflection protocols, serialization hooks, and language operations without transfer rules are not advertised as precise. An encountered unsupported operation produces `UNSUPPORTED_LANGUAGE_FEATURE`; an unresolved external call uses the appropriate call/model/dispatch frontier. The analyzer never executes those operations in the host application environment.

The source index rejects syntax newer than the target, including property hooks, asymmetric visibility, unparenthesized `new` dereferencing, pipe operators, void casts, first-class callable constants/defaults, and partial function application. Parsing is not a complete replacement for PHP's compile-time program validation.

## Bundled standard functions

The authoritative parameter names, defaults, reference modes, and variadic declarations are in [Library](../src/Standard/Library.php). Every invocation goes through ordinary signature binding before model evaluation. Known invalid argument forms can therefore throw before reaching the cases below. Unsupported model cases retain `UNSUPPORTED_MODEL_CASE`, possible effects, and exceptional alternatives.

| Functions | Implemented cases | Boundaries |
| --- | --- | --- |
| `strlen`, `strtolower`, `strtoupper`, `trim` | Byte strings; target ASCII case conversion; known trim character list | Partial inputs retain symbolic relationships. Unknown or malformed trim character lists are unsupported; malformed ranges never emit host warnings. |
| `substr`, `explode` | Concrete strings and offsets/limits; nullable substring length; empty explode separator error | Unresolved offsets, lengths, separators, or limits can remain unsupported. |
| `implode`, `join` | One-array or separator/array form with closed arrays of scalar pieces; symbolic scalar pieces retain concatenation | Invalid concrete overloads produce `TypeError`. Open arrays, unresolved overloads, object/array element conversions, unconstrained pieces, and configuration-dependent float formatting are unsupported. |
| `sprintf` | `%s`, `%d`, `%%`, positional substitutions, scalar values | Other formatting flags/specifiers, unknown format strings, and object/unconstrained conversions are unsupported. |
| `str_replace` | Concrete supported search/replacement/subject forms, with correlated reference count | Unsupported partial or aggregate forms retain the result and count boundary together. |
| `count` | Known arrays, recursive mode, captured `Countable::count(): int` | Unresolved mode/protocol and an omitted source `count` return declaration are unsupported. |
| `array_keys`, `array_values`, `array_merge`, `array_key_exists`, `in_array` | Ordered known array structures and supported search modes; invalid key types | Partial shapes and unresolved comparisons may retain symbolic or unsupported results. |
| `array_map`, `array_filter`, `array_reduce` | One closed array, known synchronous callback, nullable callback where allowed, known filter mode; callback effects and throws | Multi-array map, unresolved callback protocol, unknown filter mode, and open arrays are unsupported. |
| `sort` | Concrete scalar arrays, flags `SORT_REGULAR`, `SORT_NUMERIC`, `SORT_STRING`, `SORT_STRING \| SORT_FLAG_CASE`; reference write and reindexing | Locale/natural sorting and partial/nested arrays are unsupported. |
| `is_array`, `is_string`, `is_int`/`is_integer`, `is_float`/`is_double`, `is_bool`, `is_null`, `is_object`, `is_numeric`, `is_scalar` | Concrete values and supported finite primitive type bounds | Unproven predicates stay symbolic. |
| `is_callable` | Known one-argument predicate forms, source closures/functions and proven public callable pairs | Syntax-only mode, reference name output, and unresolved visibility/autoload protocols are unsupported. |
| `get_class` | Known receiver runtime classes; symbolic class relationship | Omitted-argument form is unsupported. |
| `getenv` | Named/all environment lookups, local-only distinction, explicitly supplied environment facts | Host environment is never read as application input. |
| `time`, `microtime`, `rand`, `mt_rand`, `random_int` | External input identities, stable saved values, random bounds and supported errors | Values stay symbolic; entropy failure remains possible for nondegenerate `random_int`. |

[Standard unit tests](../tests/Unit/Standard/), [native overload tests](../tests/Integration/NativeOverloadContractTest.php), the [generated differential corpus](../tests/Fake/GeneratedPrograms.php), and the [conversion corpus](../tests/Fake/Programs/ConversionPrograms.php) check these contracts. A function absent from this list needs a source body or an explicitly registered model.

Native `Exception`, `Error`, SPL throwable subclasses, and `ErrorException` have target definitions for construction and supported property/getter behavior. See [native throwable objects](language.md#native-throwable-objects). They do not use host reflection or instantiate application classes.

## Models, convergence, and reuse

The public SDK supports signatures, ordered plans, callbacks, locations, abstract state slots, intrinsics, domains, declaration/entry/environment/dispatch/observation providers, and refinement models. [Model contract tests](../tests/ModelContract/) exercise independent Policy, MiniBuilder, and MiniContainer implementations. Domain laws and footprint validation reject known contract violations; trusted extension implementations remain responsible for their semantic correctness.

Source IR is reused as a parameterized state computation and evaluated with the current receiver, arguments, aliases, and heap. Completed summary outcomes are memoized only when isolation has been established for the supported return-query scope. General stateful calls reuse IR but evaluate their effects again. The package does not transplant a cached concrete heap into another call context. Recursive dependencies use the demand table, component tracking, inclusion checks, and explicit residual sealing.

Source/IR caches are bounded and shared by an `Analyzer`. Query results and explanations are retained by their immutable session for its lifetime. Applications processing unbounded streams of unrelated queries should release completed sessions. See [resource and cache controls](api.md).
