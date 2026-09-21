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
branches. A data-modifying CTE retains its mutation targets and exposes its
RETURNING columns to subsequent references. Aliases and column alias lists
establish the visible relation shape.

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
input queries, and returned output columns. Configuration commands expose named effects and typed values. Other utility
commands retain their source operation, argument expressions, and embedded queries. `Binder::bindAll()` keeps script boundaries.

A fixture generator must traverse declaration constraints, input relations,
nested queries, join conditions, filters, groups, and output requirements. It owns
candidate generation and solving; the semantic package does not execute SQL or
claim that a satisfiable fixture exists. SQL forms cannot be excluded merely
because a consumer needs additional semantic information.

The package layers are checked by Deptrac: `Ast` reads declarations and navigates
syntax, `Binding` assembles semantic graphs, and `Schema`, `Model`, and `Type`
provide result objects. Public entry points accept SQL strings and preserve the
selected parser release throughout construction and binding.

## Storage and configuration effects

An INSERT's `insertion` identifies its target and ordered destination expressions.
The order is the SQL column-list order, which can differ from declaration order.
It also distinguishes explicit columns from inferred destinations, DEFAULT VALUES,
and columns omitted from the input. `rows` and `queries` retain the input values;
`ExpressionKind::DefaultValue` is a storage instruction, not a function call.
Unknown destinations retain their qualified reference and receive diagnostics.

`writes` keeps assignments in source order. Each assignment has destination
expressions and a scalar, row, or subquery value. Tuple assignments retain their
shared value. The existing `assignments` map remains a convenience view; consumers
that execute writes should use the ordered `writes` collection.

`conflicts` separates index inference, partial-index predicates, and conditional
conflict updates from the main statement. A conflict-update WHERE is not the
INSERT's row filter. Predicate and storage validation share the same rules used
by queries and ordinary writes.

Array subscripts and record fields remain structured storage accesses with a
column root. Multi-table DELETE separates named targets from read-only inputs.
UPDATE FROM and DELETE USING keep the target in their relational input tree.

MERGE exposes a `merge` plan with a target, data source, matching predicate, and
ordered `actions`. Each action retains its match state, additional predicate,
operation, and branch-local writes or insertion. An absent source and an absent
target are distinct states with different name visibility. These effects are not
flattened into unconditional assignments.

SET, RESET, and PRAGMA expose `settings`: qualified names, scopes, actions, and
ordered typed values. Unquoted setting values are configuration values rather than
column references. Local, session, global, user-variable, and transaction lifetimes
remain explicit. DEFAULT and FROM CURRENT remain distinguishable operations.
RESET PERSIST retains the optional IF EXISTS behavior and represents an omitted
variable name as all persisted settings.

CREATE TABLE additionally exposes `definitions`, associating bound DEFAULT,
generated-column, and CHECK expressions with the table declaration. Consumers can
follow column lineage without interpreting the declaration's concrete syntax tree.

## Construction and editing invariants

Model construction validates output positions, relation ownership, declaration
membership, reference states, dialect consistency, and collection element types.
A resolved column requires its declaration binding; unresolved references retain
their names and unknown types. Literal and parameter spellings must agree with
their source. Invalid construction raises `InvalidStructure`. These checks run in
normal library use, independently of the fuzz harness. SQL validity diagnostics
remain separate so that invalid SQL can still have a well-formed analysis graph.

`BoundStatement::toSql()` reproduces the SQL represented by a parsed snapshot.
`Binder::replaceExpression()` replaces an expression owned by that snapshot,
checks that the replacement stays within one expression, preserves precedence
and surrounding source, then parses and binds the whole result against the schema.
It returns a new snapshot with updated types, scopes, and lineage. The old snapshot
is unchanged. A replacement that introduces an invalid reference or predicate is
rejected. This source-based editing path does not require a general SQL serializer.
