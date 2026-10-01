# Semantic model

SQL Semantics structures the meaning of SQL. A successful analysis identifies the
operation, its inputs and outputs, the scopes in which names are resolved, and the
relationships between those values. Parsing is an input boundary, not the public
data model. Grammar productions, tokens, punctuation, and source text cannot stand
in for a semantic operation.

For `SELECT foo FROM bar`, analysis must identify a SELECT, a relation occurrence
named `bar`, and a projected column reference named `foo`. Without a catalog, `bar`
is a candidate owner and the column type is undetermined because its declaration
is unavailable. With a closed catalog, the reference resolves to the actual table
and column objects, is missing, or is ambiguous. These are different outcomes.

## What went wrong

The previous `Semantics::analyze()` lowered each parser production into a generated
class. Removing forwarding productions and assigning PHP types did not change the
abstraction: its fields still represented grammar positions. The separate `Binder`
resolved a limited set of SELECTs, but its facts were absent from the statement
returned by `analyze()`, and could not survive edits to that statement. Schema-free and schema-aware analysis must both belong to
`Semantics::analyze()`. There is no separate public Binder phase.

The fuzz oracle compared formatter-normalized input and output. A grammar-shaped
copy could satisfy this without identifying a single column or dependency. That
property checks reconstruction, not semantic structuring.

## Values, references, and changes

Statements and their reachable values are immutable. A SELECT and each INSERT
source form have distinct concrete types. There is no generic statement with a
kind flag and optional bags of properties, no raw-SQL fallback, and no unknown
statement or unknown property.

A declaration and a relation occurrence are different objects. Two occurrences
in a self join may share a declaration; references must still identify the correct
occurrence. Projection order and duplicate result names are preserved. Looking up
a duplicate name cannot silently select the first column.

Constructors and persistent update methods express their invariants with PHP
`assert()`, following the library's design-by-contract model. Assertion execution
is enabled for verification. This redesign does not change that policy. Adding a field resolves it through the owning
scope. A field from a different scope cannot be transplanted with stale bindings.
Updates return a new statement; the original remains valid and unchanged. Changing
an INSERT source form requires constructing the other statement type.

A resolved projection alias retains its target field and underlying expression.
Replacing the projection does not silently rebind an existing predicate to a new
expression with the same alias. SQLite can reconstruct that predicate by writing
the resolved expression at its use site. When a missing table declaration leaves
both an input-column and an alias interpretation possible, a projection edit must
preserve the alias alternative until that lookup can be decided.

Structural invariants and database validity are distinct. Grammar-valid input can
refer to a missing column or supply incompatible row widths. It still identifies
an operation and its operands. Such contradictions belong to derived semantic
diagnostics on that concrete operation, not an unknown statement or a discarded
syntax fragment. An invariant ensures that a reference belongs to its scope and
that its facts match the represented operands; it must not conceal a language
coverage gap by refusing every statement with a database-level semantic error.

SQL is written from these values. Source locations may accompany diagnostics, but
the writer must not read source text or a parser tree. Formatting, redundant
parentheses, and keyword synonyms are not semantic identities.

## Expression results

An expression owns its semantic operands and exposes their declaration
dependencies. Its result facts follow from those operands and the database's
rules. A SQLite string literal has text storage, whereas arithmetic may produce
either integer or real storage depending on input values and overflow. That
numeric alternative is a known result domain, not an unimplemented or unknown
type. NULL facts are separate from the non-NULL storage domain.

NULL propagation also belongs to each operation. A NULL-safe comparison always
returns a truth value; ordinary equality can return NULL. A range test evaluates
its subject once, so it must not become two independently evaluated comparisons.
SQLite's empty membership list has a constant result even for a NULL or unresolved
subject. These distinctions follow the
[SQLite expression rules](https://sqlite.org/lang_expr.html).

Literal values are decoded without first rounding through a PHP floating-point
number. A numeral can be grammatically valid but exceed the database's literal
range; its value and range diagnostic remain available in the structure. Clock
expressions describe requests that the database evaluates, without sampling the
clock during analysis.

A computed output name is part of the result shape. SQLite can derive that name
from the expression's written form. Reconstruction may therefore need an explicit
result alias when it normalizes expression grouping. The stored name is an output
identifier; the expression itself is still written from its typed operands.

## Uncertainty and implementation defects

An unknown fact must name the missing information, such as an absent catalog or a
parameter type. A known catalog with no matching column produces a missing-column
diagnosis, not an unknown type that pretends resolution succeeded. Several known
matches produce ambiguity. In an open catalog, candidate ownership is not a claim
that the column exists.

An unimplemented SQL construct is an implementation gap. Analysis must report it
explicitly and fuzz must record it as a failure. It must never become a successful
unknown value, a retained syntax fragment, an ignored clause, or a skipped fuzz
case. Grammar coverage measures inputs exercised; it does not prove semantic
coverage.

## Verification

Verification has three independent obligations:

1. Every generated statement must lower into the semantic model, with no syntax
   fallback or ignored portion. A structural audit rejects parser nodes, tokens,
   generated grammar values, and mutable state in the result graph.
2. SQL reconstructed from the model must parse and reanalyze to the same semantic
   structure. Formatter-normalized reconstruction remains an additional check
   where the normalization preserves semantic distinctions. It is not proof of
   name resolution or type correctness.
3. Semantic relationships must agree with independent observations. Small generated
   schemas permit database execution comparisons for projection, aliasing,
   qualification, and immutable edits. Renaming an alias consistently must preserve
   declaration identity; adding a projection must preserve existing fields and
   append the expected database value. Self joins, missing and ambiguous names,
   scope leakage, and INSERT widths need explicit combination scenarios.

Round trips can reproduce the same mistake twice. Their role is preservation.
Database observations and specification-backed scenarios establish the meaning.
Do not replace broad grammar generation with a restricted generator to make a
semantic coverage gate pass.

The reconstruction comparison treats immutable identifier values by value while
preserving declaration and relation-occurrence identities. Whether two equal
identifier objects happened to share a PHP allocation is not SQL meaning. A
resolved SQLite alias and its substituted expression are equivalent at that use
site; their output names and table-column dependencies must still agree.

## Specification anchors

- [PostgreSQL table expressions](https://www.postgresql.org/docs/17/queries-table-expressions.html):
  relation occurrences, alias visibility, joins, and NULL extension.
- [PostgreSQL ordering](https://www.postgresql.org/docs/16/queries-order.html):
  output names and positions in ORDER BY.
- [MySQL identifier qualifiers](https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html):
  qualified and unqualified column references.
- [SQLite SELECT](https://www.sqlite.org/lang_select.html):
  input relations, projection, joins, ordering, and result shape.

## Completion criterion

The supported surface is every syntactically valid SQL statement generated by
sql-faker from each supported database grammar. Supporting a subset, reporting
remaining forms as unsupported, or merely preserving their syntax does not meet
this criterion. An unimplemented construct must remain a failing case while the
migration is in progress; it is not an exemption from language coverage.

A finite smoke run alone cannot establish this coverage. Each reachable grammar
production must have a lowering rule that constructs a semantic operation, or a
proved structural role such as forwarding, optionality, or list composition. The
coverage inventory must distinguish these cases from missing lowerings. Recursion
and combinations remain subject to broad generation and database-backed or
specification-backed semantic checks.

## Declarations are context, not simulated state

Analysis does not execute or simulate SQL. Supplied declarations describe the
objects names may refer to. Their order is not a sequence of schema transitions.
A CREATE TABLE declaration provides its own table and columns. An ALTER TABLE or
DROP TABLE statement represents the requested operation; supplying it as context
does not modify or remove the CREATE TABLE declaration.

For example, analyzing `SELECT foo FROM bar` against a declaration of `bar(foo)`
and an `ALTER TABLE bar RENAME COLUMN foo TO other` must retain the reference to
the original `foo` declaration. It must not construct an altered table snapshot.
Permuting or adding non-declaration operations to that context leaves those
references unchanged. Conflicting declarations require a resolution diagnosis;
there is no last-statement-wins execution rule.

The same boundary applies to scripts, conditional DDL, defaults, generated
columns, and writes: analysis represents what the operation requests and what its
expressions refer to. It does not maintain a simulated session or evaluate a
history of writes. Query-local visibility such as CTE scopes remains semantic
analysis of the query, not execution of earlier statements.

## Migration status

This contract describes the required result, not a completed coverage claim. The
initial prototype covered SELECT, basic INSERT source variants, and single-table
DELETE. That subset did not meet the completion criterion. The migration is being
integrated with the current main branch, including its language settings, typed
declarations, and consumers. Existing syntax-based round-trip success is not
credited as semantic coverage.
