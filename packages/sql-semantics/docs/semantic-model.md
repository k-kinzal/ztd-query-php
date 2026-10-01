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
returned by `analyze()`, and could not survive edits to that statement. The separate
public `Binder` is removed: schema-free and schema-aware analysis both belong to
`Semantics::analyze()`.

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

SQL is written from these values. Source locations may accompany diagnostics, but
the writer must not read source text or a parser tree. Formatting, redundant
parentheses, and keyword synonyms are not semantic identities.

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

## Specification anchors

- [PostgreSQL table expressions](https://www.postgresql.org/docs/17/queries-table-expressions.html):
  relation occurrences, alias visibility, joins, and NULL extension.
- [PostgreSQL ordering](https://www.postgresql.org/docs/16/queries-order.html):
  output names and positions in ORDER BY.
- [MySQL identifier qualifiers](https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html):
  qualified and unqualified column references.
- [SQLite SELECT](https://www.sqlite.org/lang_select.html):
  input relations, projection, joins, ordering, and result shape.

## Implementation status

The generated grammar models and the separate public `Binder` have been removed.
The current concrete statements are `Select`, `InsertRows`, `InsertSelect`, and single-table `Delete`.
See [statement models](statements.md#current-implementation-boundary) for the
remaining language coverage. These gaps remain failures in broad grammar fuzzing;
this implementation does not claim complete semantic support.
