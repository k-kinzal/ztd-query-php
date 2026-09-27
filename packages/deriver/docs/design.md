# Semantic contract

Deriver answers questions about PHP values and state without running the application. A query identifies a callable or expression, an observation point, a scope of inputs, and a finite work budget. Results retain symbolic inputs, partial structures, correlated alternatives, and reasons why a dependency could not be expanded.

The library runs on PHP 8.1 and later. The initial semantic target is **PHP 8.3 with 64-bit integers**. The runtime executing Deriver and the runtime described by a query are separate. Accepting syntax in the parser does not establish support for its execution semantics.

## Source and world

An analysis session captures immutable source bytes. Paths and content hashes, target semantics, model versions, environment assumptions, and the world contract identify the snapshot. Reading another revision requires opening another session. Files with syntax errors remain project diagnostics; they do not automatically make unrelated functions opaque.

The analyzer never includes an application's files. Composer autoloaders, bootstrap files, PHP configuration, attribute constructors, and service providers are source data. Trusted model implementations run in the analyzer process and are a separate trust boundary. Registering a model does not sandbox its PHP implementation.

**Symbolic scope** considers valid inputs to the selected callable. A parameter remains symbolic even if it has a default or only one caller appears in the project. **Entrypoint scope** uses explicitly supplied entrypoints and initial inputs. Defaults apply only to omitted arguments at an actual call.

An open world retains possible external implementations. A closed world is an explicit assumption about the supplied source and entrypoints. PHPDoc alone cannot establish that an implementation is impossible.

## Queries and observations

- `ReturnQuery` asks for normal and exceptional completion of a callable.
- `ValueQuery` asks for the value produced when an expression is evaluated.
- `StateQuery` asks for storage immediately before or after an identified instruction.
- `TupleQuery` asks for several values at one observation point and preserves their correspondence.

A call argument is observed after its evaluation and before invocation. Observing `$x++` yields its expression result, while observing `$x` after evaluation yields the updated value. An observation does not execute the expression a second time.

`deriveMany()` batches independent queries. It does not introduce correlations between them. Use a tuple when a key and value, statement and bindings, or other related values must remain paired.

Public source ranges use half-open byte offsets `[start, end)` and one-based display lines and columns. References belong to one snapshot. They are not persistent identities across edits, parser nodes, or host object identifiers.

## Values, references, and memory

Values are immutable terms. Constants, parameters, external inputs, operations, array shapes, object identities, closures, and residual expressions remain distinguishable. A nonconstant symbolic expression can be an exact and complete answer.

The evaluator separates expression registers from storage:

- Ordinary array assignment copies an ordered value. It preserves references and object identities stored inside that value.
- PHP references share storage cells. Rebinding a variable and updating a referenced cell are different operations.
- Object assignment shares an object identity. Reassigning one variable does not replace another variable's object.
- `clone` copies the outer object's slots and invokes a known clone hook. Nested objects and reference cells remain shared.
- Closure value captures record values when the closure is created. Reference captures retain cells. Creating or storing a closure does not execute its body.
- Global and static storage persist across calls within an analyzed entry context.

Array keys follow the target PHP profile. Unresolved nested storage reads retain every path component, so an unknown `outer` value does not erase a later `inner` selection. Absence, an uninitialized value, `null`, an unknown value, and absence of reachable states are different conditions. Ordered union, unpack, and `array_merge` have separate transfer rules. Merging a symbolic or open array retains an `array-merge` expression; later insertions retain `array-set` expressions instead of discarding possible keys. Symbolic unpack also retains a possible append-overflow `Error` and a `WIDENED` diagnostic.

## Control flow and calls

Source lowering produces basic blocks, explicit control transfers, and SSA expression registers. Conditional evaluation, including short circuit operators, coalescing, nullsafe access, and match arms, evaluates only the selected expressions.

Return, throw, break, and continue are distinct completions. A finally block receives a pending completion and can resume or replace it. A return expression is evaluated before finally. Exception handlers receive the state after effects completed before the throw.

Operator precedence does not establish operand evaluation order. Expressions with effects whose order cannot be justified retain an explicit `UNSPECIFIED_EVALUATION_ORDER` boundary. See the [PHP operator manual](https://www.php.net/manual/en/language.operators.precedence.php).

The core binds positional and named arguments against the selected signature, retains variadic keys and reference addresses, and evaluates defaults on omission. Unknown unpacked arguments remain a residual sequence; they are not treated as an empty array. Available source bodies supply ordinary function semantics without requiring a model for every helper.

Unresolved calls are not assumed pure. They may modify reachable receivers, object arguments, reference arguments, escaped captures, globals, and statics. Normal residual returns and exceptional residuals preserve those effects. An unrelated private local is retained when the unknown call cannot reach it.

## Correlation and convergence

A path carries its guard, values, and memory together. Branches choosing a table and column, or a policy and its tags, do not become independent sets whose Cartesian product adds false combinations.

Finite known iterations can be evaluated precisely. General loops use an abstract inclusion check and widening over finite type facts. A widened header is stable only when the next propagated state is included in its approximation. A budget interruption is separate: it retains a residual for unexplored behavior and a `BUDGET_EXCEEDED` reason.

Candidate limits must never retain only a prefix of possible results. A merged result must contain every affected alternative and report lost correlation. Recursive analysis must terminate with a justified approximation or an explicit residual. An in-progress computation is never returned as `null`, an empty successful result, or proven unreachability.

## Models

A `CallModel` supplies metadata and a `SemanticPlan`. Plans declare ordered state effects, callback invocations, branches, normal returns, and exceptions. They are lowered to the same evaluator instructions used for source bodies.

Models receive normalized call metadata. They do not walk the application's AST or implement PHP reference binding themselves. Pure intrinsics receive immutable abstract values and must handle partial inputs conservatively. Custom abstract domains define inclusion, join, widening, and projection separately from application operations such as policy composition or overriding a field.

Model decisions distinguish handled, declined, and unsupported cases. Equal-rank conflicting models are errors. Replacing known source semantics requires explicit intent. Model identities and versions participate in snapshot identity; changing a model or adding a previously missing one cannot reuse an incompatible result.

## Result assessment

The result exposes independent axes:

| Axis | Values | Meaning |
| --- | --- | --- |
| Closure | `closed`, `open` | Whether required dependencies reach supported semantics or normal input boundaries |
| Precision | `exact-symbolic`, `abstract`, `opaque` | How much value and relationship information remains |
| Correlation | `preserved`, `relaxed` | Whether queried values remain paired |
| Coverage | `over-approximation`, `unavailable` | Whether the supported contract contains possible behaviors under the assumptions |
| Enumeration | `finite-exhaustive`, `not-enumerated`, `truncated` | Whether reported candidates exhaust the returned abstract result |

An over-approximation is relative to the supported language semantics, correct registered models, and explicit assumptions. It is not proof that every guarded candidate can occur. An unsupported feature whose effects cannot be represented has unavailable coverage.

Frontiers identify root causes, source positions, affected projections, known dependencies, and residuals. `EXTERNAL_INPUT` and a justified `WIDENED` result are not model failures. Missing models, unsupported language semantics, open dispatch, incomplete source, conflicting models, invalid programs, and exhausted budgets remain different reasons.

## Serialization and confidentiality

JSON has a versioned schema and shared value references. PHP array entries preserve key types and order. Binary strings, 64-bit integers, and nonfinite floating-point values use tagged lossless encodings. The library does not use PHP object serialization for result data.

Explicitly supplied secrets retain a secrecy attribute through operations, including custom intrinsic results, array selection, references, and iteration. A confidential aggregate labels every selected value; a confidential lookup key labels the selected result. Existence tests preserve confidential absence, and conditional expressions retain the label of their selecting condition. Reports redact confidential values unless the caller explicitly requests their inclusion. Host environment variables are not silently substituted for symbolic application inputs.

## Evidence and validation

The semantic contract is checked by unit, semantic, model contract, integration, and differential tests. Differential tests execute only independently generated test fixtures in a separate target PHP process; the analysis API never executes its input project.

For a concrete input, the observed runtime value, state, and completion must be included in the derived result. These tests find counterexamples; finite samples do not prove soundness for every PHP program. Capability claims must identify their tests, and known wrong constants or silently missing alternatives block release.

The reference rules for storage and completion are the PHP manuals for [references](https://www.php.net/manual/en/language.references.whatdo.php), [object identity](https://www.php.net/manual/en/language.oop5.references.php), [cloning](https://www.php.net/manual/en/language.oop5.cloning.php), [closures](https://www.php.net/manual/en/functions.anonymous.php), [foreach](https://www.php.net/manual/en/control-structures.foreach.php), [arrays](https://www.php.net/manual/en/language.types.array.php), [arguments](https://www.php.net/manual/en/functions.arguments.php), and [exceptions](https://www.php.net/manual/en/language.exceptions.php).

Callable lookup and memoization distinguish named PHP functions and methods from synthetic graph identities. Named callables normalize ASCII case and a leading namespace separator. Script and closure identities preserve the captured path; initializer identities preserve constant, property, and parameter names. Queries and model selection use the same normalization as graph lookup, so selecting a fully qualified name observes the resolved body and separately named initializers cannot reuse each other's results.
