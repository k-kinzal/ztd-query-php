# Read-only migration inventory

The [SQLSEM-DESIGN-001 contract](read-only-design.md) is the acceptance boundary.
This inventory records migration work, not completed language coverage.

## Removed semantic update methods

These methods have no compatibility wrappers. Build a new root from explicit
inputs or analyze new SQL. Returning a new instance does not make an editor a
read-only API.

| Class | Removed method |
| --- | --- |
| `Connection\AttachDatabase` | `withFilename()` |
| `Expression\Conditional\SqliteCaseBranches` | `withOtherwise()` |
| `Expression\Conditional\SqliteSearchedCase` | `withBranches()` |
| `Expression\Conditional\SqliteSimpleCase` | `withBase()` |
| `Expression\Conditional\SqliteSimpleCase` | `withBranches()` |
| `Maintenance\VacuumInto` | `withDestination()` |
| `Mutation\SqliteDelete` | `withWhere()` |
| `Mutation\SqliteUpdate` | `withAssignments()` |
| `Mutation\SqliteUpdate` | `withWhere()` |
| `Projection\Fields` | `addField()` |
| `Query\Select` | `withFields()` |
| `Query\Select` | `withWhere()` |
| `Query\Select` | `withLimit()` |
| `Query\SqliteLimit` | `withCount()` |
| `Reference\CandidateColumn` | `withFallback()` |
| `Schema\Table` | `withColumn()` |
| `Transaction\MySql\Start` | `withAccess()` |
| `Transaction\Postgres\Begin` | `withIsolation()` |

## New-input construction in the current migration

`Query\Select` now takes a fixed `Schema\Catalog` and a complete
`Construction\Query\SelectDefinition`. Its old bound-`Fields` constructor is
removed. Scalar requests distinguish column uses, unary and binary operations,
ranges, membership, conversions, CASE forms, and fresh subqueries. Immutable
literal values can be reused; existing bound expressions cannot implement these
input roles. Exact concrete input dispatch rejects external interface
implementations before they can publish a graph.

`Query\ScopedSelect` denotes a correlated body at its lexical environment and is
not an `Operation`. Both root and body derive new occurrences and bound
expressions. This is initial construction, with no old-root input or edit
preservation condition. `InsertSelect` borrows only an independent root under the
same declaration snapshot.

Fixed profiles cover all twelve shipped grammar artifacts; this is configuration
coverage, not semantic-rule coverage. Declaration snapshots retain profile and
object identity and reject foreign profiles. Script analysis supplies the same
snapshot to each statement.

Operator spelling and explicit grouping now have bounded semantic rendering
values where expression-derived result names require them. These values cannot
carry operand SQL. Alias uses render their names instead of substituting target
expressions. Other output-name rules and complete correspondence checking remain
unfinished and are not implied by this change.

## Remaining obligations

- Finish replacing the remaining bound-graph constructors with typed new-input construction and explicit
  closed-root borrowing slots under the identical profile/context snapshot.
- Remove legacy grammar-model composition, map/rewrite, and update APIs; migrate
  consumers and their tests instead of preserving editing in another layer.
- Derive all expression facts at construction, rather than on first inspection.
- Connect actual source/model and actual rendered-output/model checks to rules.
- Complete relation shapes, stars, field lookup states, profiles, context/member
  completeness, and rule-specific precision requirements.
- Implement and review every reachable production and permitted composition in
  every SQL Faker grammar, including output and termination obligations.
- Complete broad grammar fuzz, independent database observations, full static
  checks, assertions-disabled boundaries, and green PR CI.

A graph audit and type registry only establish value-shape constraints; they do
not establish the missing semantic or correspondence obligations above.
