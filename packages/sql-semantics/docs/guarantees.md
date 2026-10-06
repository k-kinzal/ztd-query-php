# Guarantees

This page states what SQL Semantics promises, under which conditions, what backs each promise, and where the known limits are. The promises are enforced by typed PHP rules and by checks that run on every analysis. They are not a machine proof of agreement with a database server, and the package does not claim that the model behaves exactly like the server in every case.

## Conditions

Every promise holds for a fixed **language profile** (database, grammar release, lexical settings such as the MySQL modes, parameter style, rule revision) and an explicit, immutable **declaration context**. Nothing is inferred from the host, a connection, the clock or a mutable setting. Analysis of a finite input is expected to finish given enough memory and time; reaching a resource limit is an operational failure, never an "unsupported" answer.

A well-formed model is not "a statement that succeeds on the server". A SELECT of a column that does not exist is a well-formed model when the model records that the column is missing.

## The guarantees

| ID | Guarantee | What backs it |
|----|-----------|---------------|
| G1 | SQL accepted by the grammar of the selected release is structured into a concrete semantic model. | Lowering rules that dispatch on the exact productions of the shipped grammars; a production without a rule fails with `ImplementationGap`, never with a generic node or a skipped clause. |
| G2 | The model does not drop the operations, operands or relations of the input. | Leaf embedding and token correspondence between the input and the published model (see [Rendering](rendering.md#checks-before-publication)). |
| G3 | Resolutions, types and NULL facts follow the documented rules of the database for the given context, and missing information is stated, not guessed. | Derivation rules per construct, the closed fact types, and fact completeness: every expression, relation and query receives exactly one fact. |
| G4 | Published values are deeply immutable, and their internal references are consistent. | Final classes with readonly properties, constructor validation, the value audit of every published graph, and the refusal of cloning, dynamic properties and serialization by the `Snapshot` trait, which the value audit requires of every class it admits. |
| G5 | The SQL rendered for an operation requests what the model describes. | Rendering by typed pieces only; layouts that may only re-spell the rendered tokens; then parsing the text with the same release and comparing the result with the model operand by operand. |
| G6 | `new Operation(...)` publishes only a well-formed new root. | The same checks as `analyze()` except those against an input text. A construction has no relation to any earlier operation. |
| G7 | References reach the declaration objects of the context, and missing information is distinguished from missing implementation. | Resolutions hold the supplied objects; `Dependent` facts name the missing input; an unwritten rule is an `ImplementationGap`. |

What is preserved: the requested operation, the position and names of inputs and outputs (in SQLite and MySQL also the written text that unaliased result columns are named after, see [Spelled regions](rendering.md#spelled-regions)), duplicates, bindings, the relation to declarations, the meaning of types, NULL, comparison and conversion, the evaluation structure the language defines (for example a CASE, or the difference between a scalar subquery and EXISTS), and the requests of DML, DDL and transaction statements.

What is not promised: whitespace, comments, keyword case and other spellings listed in [Rendering](rendering.md#what-the-rendered-sql-does-not-preserve); row order the language leaves open; the wording of server error messages; execution plans.

## Failure classes

Each outcome has its own class, so a caller can tell them apart:

| Outcome | Class |
|---------|-------|
| The text is outside the grammar of the release | `AnalysisException` |
| The SQL is grammatical but semantically wrong | a successful operation with diagnostics |
| A declaration, signature, parameter value or session value is missing | a successful operation with `Dependent` facts naming it |
| A rule is not implemented | `ImplementationGap` |
| A constructor input is outside its documented domain | `InvalidConstruction` |
| A check before publication fails | `InvariantViolation` |
| A configured work limit is reached | `ResourceLimitExceeded` |

`ImplementationGap` and `InvariantViolation` are defects of the library. They are not converted into an unknown value or a partial model.

## Trust boundary

The checks reduce what must be trusted, but they do not remove it. Trusted are:

- the PHP runtime;
- [SQL Parser](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-parser/README.md) and its grammar and keyword artifacts, which each profile pins by SHA-256 digest; a profile refuses artifacts with another digest;
- the lowering and derivation rules and the primitives they rely on, as written from the database manuals;
- the checks themselves: value audit, fact completeness, spellings, structure equivalence, leaf embedding and token correspondence, and the noise tables they use.

Ordinary misuse of the API, such as a value of a foreign class or a node at two positions, is detected and refused. Deliberately breaking private boundaries, for example with Reflection, is outside the boundary: the package does not sandbox itself against such code.

Structure equivalence compares the model with the model read back from its own rendering, under the same rules. It establishes that the rendering is faithful to the model; whether the model reflects what the server would do rests on the rules. Token correspondence ties the rendered text to the input token by token, except for the positions the noise tables declare.

## Rule records

Every lowering rule and derivation collaborator carries a rule record in its class documentation: a rule ID (for example `SQLITE-SELECT-001`), the grammar scope, the source in the database manual, and a status:

| Status | Meaning |
|--------|---------|
| `Specified` | The contract is written; the implementation is incomplete, or the construct fails explicitly. |
| `Implemented` | The rule is implemented against its contract. |
| `ContractReviewed` | The contract and the argument for the implementation were reviewed. |

Passing tests never upgrade a status. In this release nearly all rules are `Implemented`, a few are `Specified` (for example MySQL optimizer hints), and none is `ContractReviewed` yet. The rule records are part of the API documentation of each database package.

## What tests and fuzzing establish

Tests look for defects in rules and in how they are connected; they are not a substitute for the guarantees.

- **Unit tests and examples.** Every source class has unit tests, and every `@example` in the API documentation is executed as a test. Tests include constructions that must be refused, such as operands that would be re-associated when rendered.
- **Grammar coverage.** Each database package lists every production of every shipped grammar release in `resources/productions`. The production ledger (`bin/production-ledger.php`, run as `composer ledger` in each database package and in CI) fails when a production has no lowering rule that dispatches on it, or when a rule names a production no release has. The ledger shows that a rule exists for each production, not that it is right; the fuzz targets and tests look for that. The MySQL package also checks that every statement production of its nine releases is routed to a rule family, and several noise tables are checked to name only productions and token positions that exist.
- **Grammar-based fuzzing.** Each database package has fuzz targets that generate statements from the official grammar with [SQL Faker](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-faker/README.md), analyze them, analyze the rendered text again, and require the same structure and a stable rendering. A schema target does the same for table definitions and the declarations they provide. A scheduled workflow runs these targets.

What they do not establish: that a fact agrees with the server for every input, that every server error is reported as a diagnostic, or that untested combinations of constructs are right. The package does not run statements against a database server. Comparison with real servers, where it is done, is evidence outside the package and never a runtime dependency.

## Known limitations

### All databases

- A version 1 context declares relations only. Routines, data types and session state cannot be declared; facts that need them are `Dependent`, also in a complete context.
- Not every error the server would report is a diagnostic: an operation without diagnostics may still fail on the server.
- `ResourceLimitExceeded` is reserved. No work limit is configured in this release; very large or deeply nested inputs are bounded only by PHP memory and time.
- A context built with the public `AnalysisContext` constructor takes its search path and name comparison from its arguments, not from the rules of the database; `Semantics::context()` applies those rules.
- A declaration holds a relation kind and columns, but no view query, generation expression, index, constraint or trigger. Checks that depend on them are not made; each database package lists the ones that matter.

### MySQL

- Optimizer hints (`/*+ ... */` after `SELECT`, `INSERT`, `REPLACE`, `UPDATE` or `DELETE`) are not analyzed: the parser delivers them as a comment, so from 5.7 on a statement with a hint fails with `ImplementationGap` instead of being read without it. In 5.6 such a comment is an ordinary comment.
- Version comments (`/*!80000 ... */`) are read as the selected release reads them, and the rendered SQL writes that reading without the comment markers.
- Table and database names are compared exactly, as with `lower_case_table_names=0`; column names are compared without regard to ASCII case only.
- Without a search path, the current database is unnamed, and facts that would show its name depend on it as session state.
- The name of an unaliased select item whose text depends on `character_set_client` or on a character set conversion of the server is undecided: the field has no name and its slot lists the input (`OutputSlot::$unnamed`).
- Writes into views that cannot be updated, and foreign keys that reference a view, are not reported.

See the [MySQL package](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md#limitations).

### PostgreSQL

- In an open or partial context, a type name written as an identifier, such as `text` or `date`, is `Dependent`, because a relation of `pg_temp` could define a row type with that name and would be found first. Type names the grammar reads as keywords, such as `integer`, are not affected. A column of such a type in a CREATE TABLE analyzed in an open context gets a `NamedOnPath` type. Use a complete context, or `[]`, when the built-in types are meant.
- The profile fixes `standard_conforming_strings = on` and a UTF-8 server encoding.
- A declaration does not tell a partitioned table from a plain one, so checks that depend on partitioning are not reported.
- The server stops at the first error, while the analysis reports every diagnostic it finds.

See the [PostgreSQL package](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-postgres/README.md#limitations).

### SQLite

- INSERT, UPDATE and DELETE on a view are not reported, because whether SQLite accepts them depends on `INSTEAD OF` triggers, which contexts do not hold.
- A virtual table is declared as a base table, so what SQLite refuses only for virtual tables is not reported.
- A search path must start with `main`; `temp` is always searched first.

See the [SQLite package](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-sqlite/README.md#limitations).
