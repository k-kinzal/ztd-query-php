# Supported language and confidence contract

Semantic rules target the default parser releases (PostgreSQL 17.2, MySQL 8.4.7,
SQLite 3.47.2) with standard server defaults. The tree does not encode every
server/session setting; alternate behaviors such as MySQL REAL_AS_FLOAT need a
future explicit semantic environment.

The parser accepts far more syntax than the semantic analyzer. Parsing success
does not imply semantic support. Unsupported behavior raises `SemanticException`
with `reason = unsupported-syntax` (or `unsupported-coercion` for a type rule).
There is no success flag that can conceal a skipped clause.

## Declarations

Supported inputs are ordinary CREATE TABLE statements with named columns,
modeled built-in types, NOT NULL/NULL, DEFAULT syntax, and primary, unique,
foreign-key, or CHECK constraints. Primary key tuples imply NOT NULL in
PostgreSQL and MySQL. In SQLite, only the modeled INTEGER PRIMARY KEY rowid case
implies NOT NULL: ordinary primary keys can still contain NULL, including the
inline INTEGER PRIMARY KEY DESC exception. See [SQLite's constraint rules](https://www.sqlite.org/lang_createtable.html).

Columns and key tuples preserve declaration order. CHECK, DEFAULT, foreign-key
actions, and deferrability retain their original syntax; they are not executed
or fully validated. Column modifiers such as collation, identity/generated
expressions, table options, STRICT/WITHOUT ROWID, inheritance, CREATE AS/LIKE,
ALTER, and DROP are unsupported. Named SQLite constraints whose grammar separates
the name from its constraint body are also currently rejected.

The schema is an explicit snapshot, not a server catalog reflection service.
It validates duplicate declarations and local constraint columns, but does not
validate all server-specific DDL legality or foreign reference targets.

## Name resolution

Default schemas are `public` (PostgreSQL), `main` (SQLite), and an unnamed database
(MySQL). Pass `defaultSchema` to `Analyzer` for a different context. Unqualified
names use that one schema; PostgreSQL search paths, temporary-schema precedence,
catalog qualification, and server-specific schema search are not modeled.

PostgreSQL folds unquoted names and preserves quoted case. SQLite column and table
matching is case-insensitive. MySQL table/database matching assumes a case-sensitive
catalog (`lower_case_table_names=0`); table aliases follow the same case-sensitive policy, while column names and output aliases are case-insensitive. Alternate
MySQL catalog case policies and collation-sensitive identifier rules need an
explicit resolution policy before they can be supported.

Nested scopes, CTEs, derived tables, lateral/correlated references, table functions,
column alias lists, and aliases on join results are unsupported. An alias hides
the original relation name. Duplicate correlation names, ambiguous columns, and
missing declarations are errors.

## Queries and expressions

Supported query stages are FROM, ON, WHERE, projection, DISTINCT, ORDER BY,
LIMIT, and OFFSET. Join precedence comes from the syntax tree. Table aliases,
qualified references, stars, duplicate result labels, and order aliases/positions
are preserved. Full joins apply to PostgreSQL and SQLite; the MySQL parser rejects
them. An engine-generated result label is returned as `null` instead of invented.

Scalar support includes:

- Column references and decimal integer/string/NULL literals; supported boolean
  terminals and numeric literal categories from each lexer.
- Parameters with unknown type/nullability when no declaration is available.
- Parentheses, integer `+`, `-`, `*`, comparisons, AND/OR/NOT, and NULL tests.
- COALESCE with modeled common types and NULLIF with compatible input types.

Non-decimal numeric literals and unsupported coercions are rejected. Arbitrary
operator overloads, non-integer arithmetic, division, explicit casts, CASE,
collations, string functions, user-defined functions, and aggregates/windows
require additional semantic rules. SQLite arithmetic returns a dynamic type;
affinity is not a guarantee of a runtime integer.

GROUP BY, HAVING, set operations, DISTINCT ON, locks, SELECT INTO, and DML are
unsupported. PostgreSQL ON and WHERE require boolean-compatible expressions.
No semantic facts imply successful execution: conversion failures, overflow,
constraint violations, and other runtime errors are separate concerns.

## Confidence and consumers

Unknown parameters remain visible as `ExpressionKind::Parameter` with their
original token spelling. This version does not infer a complete parameter
signature across occurrences. NULL facts are conservative and do not incorporate
WHERE refinements or data statistics. Cardinality, uniqueness preservation, and
row existence are not proved. Rejected syntax is never downgraded to a columnless
or dependency-free successful query.

Literal spellings are preserved, not decoded into PHP values. COALESCE operands
can include inserted PostgreSQL implicit-cast nodes, which retain their original
source object. Consumers should traverse the semantic operands rather than assume
that every COALESCE child is a direct column.
