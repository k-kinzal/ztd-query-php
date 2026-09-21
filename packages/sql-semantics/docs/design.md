# Semantic information for SQL consumers

`sql-parser` owns syntax and source locations. `sql-semantics` binds that syntax
against declarations and describes values, rows, and effects. `sql-fixture` can
use these facts both to generate data from DDL and to satisfy SELECT requirements.
The [versioned support contract](support.md) applies to every SQL construct.

## Declarations and occurrences

A `Schema` is an immutable snapshot of ordered DDL. `TableDefinition` and
`ColumnDefinition` describe storage declarations. Defaults, generated expressions,
column attributes, constraints, and source syntax remain available. Schema
construction applies declarations in order, including query-derived tables and
views and table alterations.

A `TableUse` identifies one occurrence in a query scope. Self joins therefore
have different relation identities even when they share a declaration. A derived
relation also carries its bound query. Column references retain that relation
identity and link derived values back to their defining expressions.

## Scope and relational stages

The identity allocator is shared across nested queries; scope, relation, and join
IDs are deterministic within a statement. Correlated queries carry a parent
namespace. A local name shadows its outer equivalent. CTEs are made visible in
declaration order. A recursive CTE has an anchor declaration and retained compound
branches. Aliases and column alias lists establish the visible relation shape.

The graph separates the FROM/join tree, WHERE, grouping, HAVING, projection,
compound-query operands, ordering, and pagination. Join ON conditions are bound
before null extension. USING and NATURAL joins also define a merged output
namespace. Each nested query keeps its own modifiers and predicates.

Scalar subqueries are expressions with a `query`; derived relations and CTE uses
are relations with a `query`. Their output expressions provide value lineage,
while their retained query graphs provide row dependencies. Neither can replace
the other when constructing fixture data.

## Expressions and uncertainty

Expressions expose their operation kind, symbol, operands, type, NULL fact,
source object, and bound column references. Scalar, aggregate, window, CASE,
cast, and subquery operations remain distinguishable. Function arguments and
window expressions remain reachable even when a catalog signature is unknown.

NULL facts are conservative bounds on successfully evaluated values:

- `NotNull`: NULL is excluded.
- `MaybeNull`: NULL is possible.
- `AlwaysNull`: the value is NULL.
- `Unknown`: the available information does not determine the fact.

These facts do not prove row existence or successful execution. SQLite affinity
is separate from runtime storage classes. Unknown types are facts about the
available environment, not permission to omit syntax or dependencies. Conflicting
built-in types and invalid name bindings still produce semantic diagnostics.

## Statements and consumers

`BoundStatement` provides the common immutable result. `BoundSelect` identifies a
query. Mutation results additionally retain targets, assignments, VALUES tuples,
input queries, and returned output columns. Utility commands retain their source
operation and embedded queries. `Binder::bindAll()` keeps script boundaries.

A fixture generator must traverse declaration constraints, input relations,
nested queries, join conditions, filters, groups, and output requirements. It owns
candidate generation and solving; the semantic package does not execute SQL or
claim that a satisfiable fixture exists. SQL forms cannot be excluded merely
because a consumer needs additional semantic information.

The package layers are checked by Deptrac: `Ast` reads declarations and navigates
syntax, `Binding` assembles semantic graphs, and `Schema`, `Model`, and `Type`
provide result objects. Public entry points accept SQL strings and preserve the
selected parser release throughout construction and binding.
