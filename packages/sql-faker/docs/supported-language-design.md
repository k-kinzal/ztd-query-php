# Supported-Language Design Principles

This document specifies how `sql-faker` should decide what belongs in its supported language.

It complements [algorithm.md](./algorithm.md).
`algorithm.md` defines how generation works once a supported grammar exists.
This document defines how broad that supported grammar should be, why that breadth matters, and how dialect-specific rewrites should be judged.

## 1. Design Context

`sql-faker` has two simultaneous responsibilities.

First, it is an independent package.
As a package, its primary responsibility is to provide a broad, verifiable, grammar-driven SQL generator for MySQL, PostgreSQL, and SQLite.
That means supported-language breadth is a first-order package property, not an incidental implementation detail.

Second, inside this repository its primary use case is fuzzing the ZTD stack.
That use case strongly influences how supported-language sufficiency is evaluated.
In practice, the generator is only useful if its supported language is broad enough to expose real ZTD bugs before users report them.

These two responsibilities must both remain true:

- `sql-faker` must remain a standalone SQL-generation package rather than a ZTD-specific helper.
- the supported language must still be shaped with bug-finding power in mind, because that is the package's most demanding real-world use case in this repository

## 2. Primary Design Goal

The primary design goal is:

> maximize supported-language breadth while preserving deterministic generation, termination, and contract-level verifiability

This goal has a strict priority order.

1. Preserve broad syntax-shape coverage of the supported language.
2. Preserve enough lexical/value diversity to exercise name-sensitive and literal-sensitive behavior.
3. Preserve deterministic, bounded, inspectable generation.

The first priority is the most important.
If a rewrite removes a syntax family, narrows an alternative family, or collapses a previously distinct shape into one canonical form, it reduces the package's expressive power.
That kind of reduction must be treated as a design decision, not as harmless cleanup.

The second priority also matters.
A generator with wide statement-shape coverage but trivial identifier and literal domains is still weak for fuzzing.
For example, identifier-like domains, string-like domains, numeric-like domains, and keyword-adjacent values all affect real parser, classifier, and rewriter behavior.
Breadth therefore means both:

- structural breadth: many distinct reachable syntax shapes
- lexical breadth: enough varied rendered values to stress the layers that consume those shapes

Structural breadth comes first, but lexical breadth is not optional.

## 3. What "Breadth" Means

Breadth is not the same as "the upstream grammar in full".
`sql-faker` intentionally compiles a supported language from upstream grammar snapshots.
The supported language may be narrower than the upstream grammar as long as that narrowing is justified, explicit, and reviewable.

For this package, breadth should be judged at three levels.

### 3.1 Entry-Point Breadth

Publicly important statement and fragment families should remain reachable through explicit entry rules.

Examples:

- top-level DML families
- top-level DDL families
- transaction-control families
- statement families that are historically important for ZTD
- fragment families that are consumed directly by providers or tests

If a family matters to users or to fuzzing, it should have a stable, named path into the supported grammar rather than surviving only incidentally.

### 3.2 Alternative Breadth

Within a statement family, distinct reachable alternatives matter.

Examples:

- `INSERT ... VALUES` versus `INSERT ... SELECT`
- `UPDATE` variants with joins, conflict clauses, or dialect-specific options
- `CREATE TABLE AS SELECT`
- `RETURNING`
- `ON CONFLICT`
- qualified names versus unqualified names

If two alternatives stress different parser, planner, classifier, or rewriter paths, collapsing them into one shape is a real loss.

### 3.3 Lexical Breadth

Rendered identifiers and literals must vary enough to exercise downstream behavior.

Examples:

- quoted versus unquoted identifier forms where supported
- one-part versus multi-part names where meaningful
- string values with varied lengths and contents
- bounded but non-trivial numeric domains
- domains that expose keyword-adjacent and delimiter-adjacent behavior

Infinite lexical domains do not need to remain infinite.
They do need to remain meaningfully broad enough to avoid turning whole syntax families into nearly single-shape witnesses.

### 3.4 Coverage Claims Must Name Their Unit

Statements such as "100% coverage" are only meaningful if the unit of coverage is explicit.

For this package, coverage claims should always identify what is being covered.
Examples include:

- public entry-rule coverage
- reachable alternative coverage
- statement-family coverage
- historical-issue witness-family coverage
- lexical-domain coverage for a designated symbol family

Bare coverage claims are discouraged.
In practice, the most important coverage discussions in this package are about reachable syntax shapes and historically bug-relevant witness families, not just about counting generated strings.

## 4. Supported Language Versus Semantic Validity

`sql-faker` does not promise that every generated SQL string is semantically valid against a live database state.
It promises something narrower and more useful for testing:

- the generated SQL belongs to a documented supported language
- the supported language is derived from upstream grammar snapshots by an explicit rewrite program
- the resulting generator is deterministic and contract-verifiable

That means semantic validity is not the acceptance criterion for supported-language design.
The acceptance criterion is whether the supported language preserves enough breadth to be valuable for grammar-driven testing and fuzzing.

## 5. ZTD As The Primary Sufficiency Oracle

Although `sql-faker` must remain package-independent, this repository still needs a concrete way to judge whether the supported language is broad enough.
For this repository, the strongest oracle is ZTD bug-finding power.

The practical question is:

> if the fuzz harness had been running long enough, should it have been able to discover this class of bug before the issue was filed?

That does not mean every historical issue must be generated by a single standalone SQL statement.
Historical issues fall into different witness classes.

### 5.1 Single-Statement Witnesses

Some bugs can be triggered by generating one specific statement family.

Examples include support gaps around features such as `RETURNING` or `INSERT ... SELECT`.

For these, the supported language itself must contain the required syntax family.

### 5.2 Stateful Witnesses

Some bugs require schema setup or prior data state.

Examples include uniqueness conflicts, empty-result behaviors, or CTAS-like interactions that depend on existing relations or rows.

For these, `sql-faker` must at least generate the relevant statement shapes.
The fuzz harness is responsible for providing the schema, prior writes, and execution order needed to surface the bug.

### 5.3 Prepared Or Parameterized Witnesses

Some bugs require prepared statements, parameter binding, or execution through a specific adapter path.

For these, `sql-faker` must generate the SQL shape and enough lexical breadth for the shape to remain meaningful.
The fuzz harness is responsible for choosing prepared execution, binding values, and comparing behavior across execution modes.

## 6. Historical-Issue Rediscoverability

Historical issues are not merely regression tests.
They are also evidence about which syntax families matter in practice.

Therefore, supported-language sufficiency should be judged partly by historical-issue rediscoverability:

- if a past issue was triggered by a syntax family that belongs in a broad SQL generator, that family should remain representable in the supported language
- if a past issue required state, parameters, or adapter mode, the necessary SQL shape should still remain representable even if the full scenario lives in the fuzz harness
- if a rewrite would remove or collapse a historical bug-triggering shape, that rewrite needs an explicit, documented justification

This is especially important for issues in which the bug was not "the SQL is malformed", but "the SQL is valid and reveals a deeper ZTD problem".

Representative examples from this repository include:

- PostgreSQL `RETURNING` support gaps
- `CREATE TABLE AS SELECT` behaviors that depend on empty results
- SQLite `INSERT ... ON CONFLICT DO NOTHING` interactions with shadow-state uniqueness
- prepared-statement cases where PostgreSQL user-defined functions appear inside `WHERE`

Those examples show why syntax-family preservation matters even when the full failing witness is stateful or adapter-specific.

## 7. Rewrite Acceptance Rules

Every dialect-specific rewrite should be judged against the following rules.

### 7.1 Rewrites That Are Usually Acceptable

These are generally acceptable when documented explicitly.

- bounding infinite lexical domains to a finite but non-trivial domain
- bounding list arity to preserve termination
- introducing wrapper rules that make important statement families explicit
- replacing underconstrained or implementation-poisonous forms when an equivalent bug-finding shape still remains available
- normalizing purely superficial spelling differences when the affected shapes do not exercise materially different downstream behavior

### 7.2 Rewrites That Require High Scrutiny

These are dangerous because they can silently reduce supported-language breadth.

- filtering out whole alternatives
- canonicalizing identifier-like or name-like rules to a single form
- collapsing many option cross-products into one small canonical family
- replacing broad statement families with only one safe exemplar
- removing ambiguous or difficult syntax purely because it is inconvenient to render

These rewrites are not forbidden.
They require an explicit argument that one of the following is true:

- the removed forms are outside the intended supported language for a documented reason
- the removed forms do not materially increase syntax-shape coverage
- the removed forms are replaced by another family that preserves the same bug-finding surface
- the removed forms make deterministic bounded generation impossible or disproportionately harmful

### 7.3 Rewrites That Should Normally Be Rejected

These should normally not be accepted.

- rewrites whose main effect is to make generated SQL "look cleaner"
- rewrites that remove historically bug-relevant statement families without replacing them
- rewrites that collapse distinct syntax paths that are known to stress different ZTD behavior
- rewrites that turn a broad family into a single canonical witness without a strong termination or safety reason

## 8. Responsibility Boundary

Supported-language design is easier to judge when the package boundary is explicit.

`sql-faker` is responsible for:

- grammar snapshots
- supported-language compilation
- explicit public entry rules
- broad syntax-shape generation
- non-trivial lexical/value generation
- deterministic generation semantics

The fuzz harness is responsible for:

- schema creation
- database state setup
- multi-statement sequencing
- prepared execution versus direct execution
- parameter binding
- cross-engine or cross-adapter comparison
- reduction from generated witnesses to bug reports

This boundary is important because not every historical issue needs to be reproducible by `sql-faker` alone.
What `sql-faker` must preserve is the SQL surface area needed by the harness to construct those witnesses.

## 9. Implications For Contract Design

This design document has direct consequences for future claims and contract checks.

The contract system should increasingly be able to express and verify:

- that important statement families remain reachable
- that critical alternatives remain distinct rather than silently collapsed
- that bounded lexical domains remain intentionally broad rather than trivial
- that historical bug-relevant shapes remain present
- that rewrite programs document why any excluded family is excluded

Not every one of these properties belongs in the same claim catalog.
Some belong in grammar claims, some in generation claims, and some in higher-level fuzz-harness assertions.
But this document defines the design standard those checks should serve.

## 10. Review Checklist

When reviewing a supported-language change, ask the following:

1. Does this change preserve or improve syntax-shape breadth?
2. Does it preserve enough lexical breadth to matter in practice?
3. Is the change necessary for bounded deterministic generation or for a clearly documented exclusion?
4. Does it remove a statement family or collapse alternatives that may matter to users or to ZTD fuzzing?
5. Would this change make any historical issue harder to rediscover?
6. If breadth is reduced, is the reason explicitly documented and defensible?

If those questions cannot be answered clearly, the rewrite is not yet well specified.
