# The semantic phase of a database front end

A syntax tree says what was written. A database must additionally determine what
each name denotes, which operation an expression invokes, what values it can
produce, which rows participate, and which integrity conditions constrain those
rows. A fixture generator needs much of the same information even when it never
executes a physical query plan.

## Required information

| Concern | Needed for evaluation | Initial representation / extension boundary |
| --- | --- | --- |
| Resolution environment | Dialect/version, schema declarations, case rules, parameter declarations | Schema records dialect, grammar release, default namespace, and explicit declarations |
| Declarations | Table/column identity, ordinal, type modifiers, defaults, generated values | Ordered declarations, primary/unique/foreign/CHECK constraints and default syntax; generated expressions are a future extension |
| Binding | Relation occurrences, aliases, scopes, correlation, visibility | Bound table/column uses in one scope; nested scopes and correlation must be added together |
| Expression meaning | Operation, ordered arguments, overload, coercions, result type | Small explicit scalar operation set; modeled PostgreSQL COALESCE conversions; no invented function signatures |
| NULL behavior | Declared allowance, operator propagation, outer-join extension, predicate refinements | Four-state NULL facts and introducing join IDs; refinements are conservative |
| Value provenance | Which bound column occurrences and transformations produce a value | Expression graph and occurrence-aware leaf lineage |
| Row provenance | Which sources and conditions make a row exist | Join tree with match predicates, separate WHERE predicate |
| Multiplicity | Bag semantics, duplicate elimination, grouping, set operations, uniqueness proofs | Bag semantics by default; DISTINCT and pagination; no cardinality estimates or inferred keys |
| Ordering | Sort expressions, output aliases/ordinals, direction, NULL placement, collation | Ordered keys with direction and explicit NULL placement; unsupported collation operations fail |
| Effects | Read/write targets, assignment coercions, defaults, generated values, RETURNING | Read-only SELECT; writes require a separate mutation model |
| Evaluation properties | Volatility, side effects, errors, aggregate/window evaluation stage | Unsupported functions fail; no claim that NotNull means error-free |
| Uncertainty | Unknown facts, unresolved bindings, unsupported semantics, locations | Explicit unknown facts and source-bearing semantic exceptions |

This vocabulary follows the division between parsing, semantic transformation,
and planning in a database. Physical access paths, estimated cost, and execution
algorithms belong after semantic analysis and are not needed to explain a query.
PostgreSQL similarly separates its [parser stage](https://www.postgresql.org/docs/17/parser-stage.html)
from planning and execution.

## Identity and scope

A `TableDefinition` is reusable. Each `TableUse` denotes one occurrence in a
query. `ColumnBinding` links both an occurrence ID and a declaration. Consequently,
`child.id` and `parent.id` can share a declaration while denoting different values.
The expression graph does not use table name strings as a substitute for binding.

An alias hides the table's original name. An unqualified column must resolve to
exactly one visible input. Unknown and ambiguous names raise separate errors.
The ON namespace contains only that join's operands: a preceding comma-separated
table does not leak into a PostgreSQL join condition. The source object on each
binding operation gives downstream tools the exact original syntax to highlight.

`scopeId` is currently `s0`. The next step for CTEs and derived tables is a scope
graph with parent/visibility edges and relation columns that can refer to query
outputs, followed by recursive CTE type stabilization. Flattening their names
into the current table list would destroy correlation semantics, so these forms
are rejected until that graph exists.

## NULL facts have an evaluation stage

A declaration's NULL allowance does not change during binding. Each expression
receives the allowance that applies where it is evaluated. A join's ON predicate
is bound before that join adds null-extended rows. Expressions above a LEFT JOIN
see its right-side columns as nullable. An ancestor outer join may add another
cause; `nullExtendedBy` is therefore a list rather than one optional join.

A COALESCE with a definitely non-null alternative is definitely non-null. Its
result has no null-extension cause, while its nullable input still records the
join that introduced NULL. NULLIF can introduce NULL without any outer join.
Boolean operations use conservative three-valued logic rather than treating
`NULL OR TRUE` as a strict scalar function. The query retains WHERE independently
because [ON and WHERE act at different stages](https://www.postgresql.org/docs/17/queries-table-expressions.html).

Facts are upper bounds over successfully evaluated rows:

- `NotNull`: NULL is excluded.
- `MaybeNull`: NULL is permitted by the modeled semantics.
- `AlwaysNull`: every successfully evaluated value is NULL.
- `Unknown`: binding lacks the input information needed to classify it.

No predicate-based narrowing is currently performed. For example, filtering an
outer-joined column with `IS NOT NULL` may leave a conservative `MaybeNull` result.
A consumer must not use a nullable classification to assert that a NULL row exists.

## Lineage is not a complete fixture plan

`Expression::lineage()` gives unique value dependencies. It deliberately keeps
separate occurrences of the same table. It does not imply a one-to-one row
mapping, equate a join predicate with a foreign key, or claim that every operand
will execute: COALESCE may short-circuit.

Consider `SELECT 1 FROM users WHERE score > 0`. Its output has no column lineage,
but its rows depend on users and the predicate. Fixture generation must traverse
both the expression graph and the relational tree. It should distinguish:

1. Storage constraints on candidate base rows.
2. Join match and nonmatch requirements.
3. Post-join filter truth requirements (only SQL TRUE retains a row).
4. Output value requirements and duplicate elimination.

Foreign-key column order and unique-key tuples remain explicit. CHECK and default
syntax is preserved for a future declaration-expression binder; this version
does not solve those predicates, apply default expressions, validate foreign-key
target existence, or prove that the full DDL would execute on a server.

## Types and future function resolution

`TypeDescriptor` is a database identity with declared modifiers, not a PHP scalar
name. SQLite additionally exposes affinity because its [typing rules](https://www.sqlite.org/datatype3.html)
do not generally restrict stored values to the declared type. Unknown parameters
retain their token identity and unknown facts until a caller supplies a future
parameter environment.

The current scalar models are intentionally finite. General function support
needs a registry of overload signatures, argument coercions, return-type rules,
NULL behavior, volatility, and scalar/aggregate/window/set-returning category.
A rule that every function inherits its first argument's type or NULL allowance
would be incorrect. Similarly, CASE requires branch type resolution and control
flow, and aggregates require a grouping scope plus empty-input behavior.

For typed COALESCE, result type resolution and NULL propagation are separate
operations, as in the [PostgreSQL conditional-expression rules](https://www.postgresql.org/docs/17/functions-conditional.html).
Inserted implicit conversion nodes retain their input source, operands, and
lineage. They describe conversion; they do not evaluate or validate literal
contents as a server would.

## Package boundaries

`sql-parser` owns syntax and source positions. `sql-semantics` owns binding and
semantic facts. `sql-fixture` can consume those facts to plan and solve data.
`sql-catalog` can attach them to a recovered statement and keep any semantic
failure as a finding. Neither consumer is a runtime dependency of this package.

The public API follows the semantic phase's inputs and output:

1. `SchemaBuilder::build(string ...$sql): Schema` parses CREATE TABLE declarations
   and establishes their dialect, grammar release, and default namespace.
2. `new Binder($schema)` establishes the binding environment for statements.
3. `Binder::bind(string $sql): BoundSelect` parses a SELECT, resolves each name,
   checks expression rules, inserts modeled coercions, and derives NULL facts.

`BoundSelect` is a semantic intermediate representation. Its table occurrences,
column bindings, expressions, and row predicates describe what the statement
means before optimization or execution. It is the phase result a planner,
evaluator, or fixture generator consumes.

Within the package, `Ast` selects the syntax parser, navigates grammar productions,
and reads declarations. `Binding` resolves scopes and occurrence identities and
binds typed expressions and SELECT clauses. `Schema`, the declaration classes,
`Model`, and `Type` are immutable results. Deptrac checks these dependencies.
