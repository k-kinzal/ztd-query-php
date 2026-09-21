# Versioned SQL support contract

The support boundary is the database release, as it is in `sql-faker` and
`sql-parser`. Every SQL statement and construct in a selected release is in
scope. There is no second allowlist of statement kinds, clauses, expressions,
types, or table options. A missing semantic rule is a defect to fix, not grounds
for declaring that SQL unsupported.

## Database releases

| Database | Grammar releases | Default |
| --- | --- | --- |
| MySQL | `mysql-5.6.51`, `mysql-5.7.44`, `mysql-8.0.44`, `mysql-8.1.0`, `mysql-8.2.0`, `mysql-8.3.0`, `mysql-8.4.7`, `mysql-9.0.1`, `mysql-9.1.0` | `mysql-8.4.7` |
| PostgreSQL | `pg-17.2` | `pg-17.2` |
| SQLite | `sqlite-3.47.2` | `sqlite-3.47.2` |

Pass `grammarVersion` to `SchemaBuilder`. The resulting `Schema` retains that
release and the binder uses it too. Syntax introduced after the selected release
is a version error. Invalid syntax, unresolved names, ambiguous references, and
incompatible built-in types remain errors; accepting the language does not mean
accepting invalid SQL.

## Declarations and fixture inputs

`SchemaBuilder::build()` processes DDL in declaration order. Tables expose ordered
columns, types, nullability, defaults, generated expressions, column attributes,
and integrity constraints. Table options and auxiliary declarations remain in
the original source; `Schema::statements` preserves the declaration sequence.
CREATE AS and view outputs can supply column declarations, LIKE can copy an
existing declaration, and ALTER/DROP update the snapshot.

Do not remove declarations because they contain generated values, identities,
collations, dialect-specific types, named constraints, or table options.
`sql-fixture` needs these facts to generate data from table definitions.

## Queries and fixture inputs

`Binder::bind()` returns a `BoundStatement`; a query returns its `BoundSelect`
subtype. `bindAll()` retains script statement boundaries. Query results expose:

- Ordered outputs and their typed expression graphs.
- Relation occurrences, aliases, derived queries, CTEs, and correlated scopes.
- Join predicates and null extension, including merged USING/NATURAL columns.
- WHERE, grouping, HAVING, compound-query branches and their set operation.
- Scalar, aggregate, window, conditional, cast, and subquery expressions.
- Ordering, pagination, and FETCH WITH TIES.
- Mutation targets, assignments, VALUES tuples, input queries, and RETURNING.

Nested queries remain reachable through relation and expression `query` fields.
CTEs and compound branches retain their own relational stages. A consumer must
traverse those stages as well as `Expression::lineage()`: a constant projection
can still depend on rows and predicates.

`sql-fixture` must be able to consume SELECT requirements to generate candidate
data. CTEs, grouping, functions, or other query forms are not reasons to exclude
that use case. The semantic phase describes the requirements; the consumer owns
data generation and predicate solving.

## Facts and uncertainty

Language support and the certainty of a fact are different concepts. A parameter
without a declaration, a catalog function without its signature, or an operation
whose result depends on runtime values may have an `unknown` type or NULL fact.
The operation, original syntax, arguments, and known dependencies must still be
retained. Unknown facts must not erase a clause, fabricate a result column, or
turn a query into a dependency-free success.

`NotNull` concerns successfully evaluated values; it does not guarantee execution
or the existence of a row. SQLite affinity is distinct from a runtime storage
class. Literal spellings, defaults, CHECK conditions, generated expressions,
foreign-key actions, and source locations remain available to consumers.

Regression tests should verify semantic facts for valid SQL and diagnostics for
invalid SQL. Tests must not enshrine an implementation gap as an intentional
language restriction.
