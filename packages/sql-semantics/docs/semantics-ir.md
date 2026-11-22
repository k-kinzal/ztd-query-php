# Complete SemanticsIR values

`SqlSemantics\SemanticsIR` and `SqlSemantics\Analyzer` are shared interfaces. The database package supplies the operation classes, operand types, scope checks, and SQL representation. All three database packages provide checked projections. Each package supports joined base-table occurrences in its checked projection domain. Each implementation fixes its own language profile and operand roles.

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$sqlite = new Semantics();
$catalog = $sqlite->context(['CREATE TABLE accounts (Id INTEGER, balance INTEGER)']);
$ir = $sqlite->analyze(
    'SELECT id, accounts.id, balance - ?2 AS remaining FROM accounts',
    $catalog,
);

assert($ir->columns[0]->expression->slot === $ir->columns[1]->expression->slot);
echo $ir->toString();
```

The source has an absolute schema and table name. Its ordered slots carry only the names of the referenced columns. Both occurrences of `id` refer to one slot. The output name `Id` is a separate value, and the two occurrences remain separate positions in the result. Interpreting or rendering the value requires no parser or declaration context. Evaluation supplies the same external objects and SQLite storage values and binds the numbered parameter at slot 2.

You can build the same structure directly with classes in `SqlSemantics\Platform\Sqlite\Ir`. Use `TableSource` to declare the external column slots, `ColumnReference` to address a slot, `ResultColumn` to associate an expression with its output name, and `Select` to close the scope. A direct source is an assertion about the external schema supplied for evaluation. Analysis establishes that assertion against the supplied declaration.

## Supported SQLite scope

- One SELECT with explicit result expressions and up to 64 base-table occurrences. A source must resolve to a complete declaration. Each column resolves to an explicit member of a specific occurrence. Two aliases of the same table have different occurrence indices.
- INNER, CROSS, LEFT, RIGHT and FULL joins with optional ON, and filtering with WHERE. The supported predicates include scalar truth conversion, comparisons and AND or OR. Each ON clause sees its join prefix. The filter sees the completed joined input.
- ORDER BY on one-based output positions, including ASC, DESC and either NULL placement. Rows with equal keys can appear in any relative order.
- SELECT ALL and SELECT DISTINCT. DISTINCT uses the collation of each output expression and preserves an eligible typed representative of each equivalent row.
- LIMIT and OFFSET with signed decimal integers or numbered parameters. The limit records count and offset separately. The comma form keeps the lexical order of parameter labels. With a bound cutting into an ordering tie, the engine can choose any eligible rows in that tie.
- Signed 64-bit integer literals and hexadecimal integers, decimal REAL literals, UTF-8 TEXT, BLOB, NULL, numbered SQLite parameters, and resolved column slots. REAL operands retain their decimal components so the selected SQLite engine determines rounding and signed zero. TEXT keeps decoded characters and BLOB identifies bytes by hexadecimal pairs.
- Binary addition, subtraction, multiplication, comparisons, AND, and OR compose as scalar expressions in projections, filters, join predicates, VALUES rows, and assignments.
- Scalar CASE retains its optional base, ordered decisions and results, and optional fallback. The engine evaluates conditions and results lazily. Simple CASE uses the affinity and collation of its operands. Numbered parameters retain their lexical registration even in skipped branches.
- Result order, result names, SQLite storage classes and values, repeated rows, and numeric parameter slots and labels. Without an ordering clause, the result is a multiset. The ordering contract fixes the relative order of rows with different keys.
- The default UTF-8 profile with short column names enabled and full column names disabled. The engine uses the standard SQLite 3.47.2 limits. The initial IR domain also bounds identifier length, the number of result columns, and expanded expression size.
- Top-level DELETE of one declared base table. The IR records the absolute target and checks any WHERE predicate against its slots. Unqualified RETURNING fields and star expansion read the deleted row with resolved column names and order. Expression and qualified RETURNING inputs, WITH and explicit index choices remain open. The DELETE changes rows when the caller executes its output. Execution supplies the same target definitions and trigger rules.
- Top-level VALUES with fixed-width, nonempty rows of scalar expressions evaluated without table inputs. Its result names are column1 through columnN, and each written row contributes one result row.
- Compound queries combine SELECT and VALUES arms through UNION ALL, UNION, INTERSECT and EXCEPT. Arms have equal width and keep their individual scopes. The compound exposes the first arm’s result labels and supports ordinal ORDER BY and LIMIT when the last arm is SELECT.
- A projection from one ordinary CTE whose definition is VALUES with explicit column names. The IR carries the definition and its ordered column slots in a `LocalSource`. The CTE supplies the rows even when the database contains a same-named base table. Materialization hints and joins with CTE inputs remain open.
- Grouped SQLite queries retain each column slot and its effective BINARY, NOCASE or RTRIM collation. COUNT can count input rows, nonnull field values or distinct values under a resolved collation. A grouped key retains the selected representative’s value and storage class. Custom collations, other aggregate functions and nonconstant group input filters remain open.
- One recursive CTE with named slots, an independent VALUES or scalar SELECT anchor, and a single self-fed SELECT arm. The definition holds the ordered successor expressions, the optional filter, and the UNION duplicate policy. The consumer reads the same scoped slots. Recursive joins, multiple arms, recursive queue limits and queue ordering remain open.
- Top-level INSERT of checked VALUES rows with explicit writable declared columns, including plain INSERT, OR ABORT and OR IGNORE. The IR preserves operand order and an absolute table identity. Existing defaults, affinities, generated values, constraints and triggers are evaluated using the same external table. Other conflict policies, WITH, UPSERT and RETURNING remain open.
- An SQLite INSERT can take a checked SELECT input. Its projection order matches the declared writable slots. The target policy, typed rows, source filters, DISTINCT, ordering, bounds and parameter markers remain part of the complete value.
- Top-level UPDATE with scalar assignments and optional WHERE on one resolved base table. Assigned expressions read the old row and refer to the same local slots used by the assignment targets. Written order preserves repeated assignments and numbered parameter registration. The current domain includes inherited, ABORT and IGNORE policies. It leaves WITH, FROM, row assignments, index choices and RETURNING open.
- DROP TABLE for a resolved ordinary table or a known schema-qualified absence under IF EXISTS. The absolute name preserves the target, including when a temporary table shadows a main table. SQLite applies the matching schema's foreign key rules during execution.
- CREATE TABLE for an ordinary rowid table with ordered columns, optional NOT NULL, and optional declared INTEGER, TEXT, REAL or BLOB types. The IR resolves the creation schema, preserves IF NOT EXISTS, and keeps an untyped column distinct from a declared BLOB column. Checked literal defaults retain their storage role and distinguish explicit SQL NULL from an omitted clause. Keys, computed defaults, additional types, table options and constraints remain open.
- BEGIN with its effective lock mode, COMMIT, ROLLBACK, SAVEPOINT, RELEASE and ROLLBACK TO. The value identifies the transition and carries the decoded checkpoint label when needed. The same connection is responsible for its transaction stack and lock state. RELEASE and ROLLBACK TO address the most recent matching checkpoint at execution.

`Semantics::analyze()` returns the complete value. Handle `SqlSemantics\AnalysisException` for malformed, unresolved or unsupported input. `analyzeAll()` applies the same contract to each statement against the same explicit context.

Use `Semantics::context()` to provide a catalog of relation definitions for name resolution. Each call to `analyzeAll()` binds against the same context. The result contains the complete statements in script order.

Wildcards expand into the same explicit result columns as individually written fields. A wildcard needs a closed declaration layout. The result retains each output name and its exact input occurrence. A qualifier on a self join selects the specific occurrence.

## MySQL and PostgreSQL statements

Call `analyze()` on the MySQL or PostgreSQL `Semantics` object configured for the target release. Their database-owned `Ir` namespaces provide direct construction with the same invariant checks.

- The projection supports zero or one ordinary base-table input or a binary join tree of external and independent derived inputs. It supports explicit result expressions, signed 32-bit integer literals and column slots, truth values, NULL, and signed addition, subtraction and multiplication. Truth values and literal NULL occupy result positions. Signed arithmetic takes integer expressions.
- CROSS, INNER, LEFT and RIGHT joins preserve operand grouping and scopes. PostgreSQL also supports FULL joins with signed integer equality between opposite inputs. ON sees the leaves of that specific join. WHERE sees the full source and keeps only true rows. Expressions refer to each occurrence by its ordinal and local column slot, including both sides of a self join.
- MySQL records the named database and uses the native default lexical settings, UTF-8 through `utf8mb4`, `lower_case_table_names=0`, and empty `sql_mode`. An unqualified table requires a declaration context with a named current database. MySQL reports signed INT table values, integer arithmetic, and the NULL result with their native result types.
- PostgreSQL binds integer slots to `int4` declarations, preserves `ONLY`, and emits `pg_catalog`-qualified integer operators. Unqualified input operators require `pg_catalog` ahead of user schemas in the relevant search path. An explicitly qualified builtin operator can supply its identity directly.
- Top-level DELETE supports one resolved ordinary target and an optional Boolean, NULL or signed-integer comparison filter. PostgreSQL preserves ONLY and includes descendant tables when ONLY is absent. The IR records the target and the filter slots. The database applies deletion and triggers when the SQL runs.
- DISTINCT compares complete projected rows. ORDER BY uses one-based output ordinals and fixes direction and NULL placement for each key. Apply LIMIT and OFFSET after ordering. PostgreSQL accepts a standalone OFFSET. A row bound may cut through a tie, where each choice remains valid within the original bag of typed rows.
- Top-level INSERT writes one or more checked VALUES rows into one resolved table. The ordered targets are explicit writable signed integer columns. A supplied cell contains an integer or SQL NULL. Omitted columns use the same external defaults. The database applies constraints and triggers on execution.
- A query INSERT assigns each output of an independent signed SELECT to its explicit target column by position. It retains the source filter, duplicate policy and NULL values. PostgreSQL emits an integer-typed NULL when the write supplies the result type.
- Top-level UPDATE preserves the ordered assignment list and optional filter over one resolved base table. Assignment operands are signed integer literals, SQL NULL, or a read from that row. MySQL reads the current row as assignments proceed. PostgreSQL reads the old row and supports ONLY.
- Plain BEGIN, COMMIT, ROLLBACK, SAVEPOINT, RELEASE and ROLLBACK TO describe a connection-local transition. A named command stores the decoded checkpoint identifier. MySQL explicitly completes with NO CHAIN and NO RELEASE. The analyzed MySQL statement uses the `completion_type=0` profile for omitted options.
- DROP TABLE keeps the ordered, absolute identities of persistent table targets and the missing-table policy. PostgreSQL also retains CASCADE or RESTRICT. MySQL uses its release-specific effect and implicit commit rules.
- CREATE TABLE fixes a persistent table identity, an ordered signed integer column layout, nullability, literal defaults and IF NOT EXISTS. The native profiles select InnoDB or heap. Additional storage and column options remain open.
- A compound query combines two checked SELECT arms. UNION, INTERSECT and EXCEPT preserve their tree grouping and row multiplicity policy. The leftmost arm supplies output names. MySQL 5.6 and 5.7 use a left-associated UNION tree. Arm ordering, bounds and WITH remain open.
- An explicit native integer cast on an output parameter fixes its position and target role. MySQL uses the lexical order of the question marks in a projection. PostgreSQL preserves numbered references, including repetitions and lexical reordering. The caller supplies the binding values. Other parameter positions and types remain open.
- A local projection reads resolved output ordinals from an independent derived SELECT or ordinary one-use CTE. The definition can itself be a local projection. Each layer owns its labels and can reorder or repeat its immediate input slots. MySQL 5.6 materializes the input and later profiles use `derived_merge=on`. MySQL integer values carry the effective width used when the server materializes a field.
- Filters and join conditions compose AND, OR, NOT, scalar IS NULL and IS NOT NULL. MySQL also has XOR. Each owning query, join or mutation checks predicate references in its visible layout. Only TRUE selects a row. PostgreSQL FULL joins require the checked opposite-side equality.
- A signed recursive series holds one initial literal, an increment, a strict continuation bound and the resolved reads of its working column. MySQL 8.0 and later and PostgreSQL emit the recursive binding from those operands. The output names follow the requested projection.
- A join leaf can hold a checked independent SELECT with signed integer fields. A consumer references the leaf by occurrence and reads the definition by ordinal. The leaf owns its SELECT and keeps its multiplicity, DISTINCT and filters. Derived leaves with ordering, bounds, dependent scopes or computed result fields remain open.
- A grouped projection partitions one resolved table by named signed slots or uses one global group. Each ordered output reads a grouping key or a native COUNT, SUM, MIN or MAX. COUNT distinguishes all admitted rows from nonnull argument values. DISTINCT applies within the aggregate, and SUM keeps the dialect’s native exact result type.
- Additional relation and predicate forms, grouping, expression ordering, other row bounds, further DML, further DDL and advanced transaction effects remain open in the native domains. Use the [scope and evidence guide](../assurance/README.md) for the implementation and engine coverage.

## Construction and evidence

| Requirement | Mechanism in the initial SQLite domain |
| --- | --- |
| P01 | The root identifies the language profile. Every operator has a closed enum case. Table identity and numbered parameters are explicit. Column references index local source slots. |
| P02 | Final classes close operand alternatives. Checked list construction copies arrays by value. The SELECT constructor checks scope, count and expanded expression limits before returning. Analysis uses the same constructor. |
| P03 | The root holds only a projection and its semantic source identity. A result name derived from SQL spelling is carried as the observed label and emitted as a quoted alias. |
| P04 | Each arithmetic node stores ordered operands. Expressions can be read or reconstructed by matching their concrete types. The source and references resolve locally in the root. |
| P05 | Every published property is readonly. The operand types and list element classes are closed. `Snapshot` blocks cloning, dynamic properties and PHP serialization and restoration. |
| P06 | The emitter visits every expression alternative, writes grouping explicitly, quotes identifiers, and emits a result name for every projection. Independent execution checks the observations on SQLite 3.47.2 within the recorded domain. |

The table applies to the checked projection roles. The SQLite [relational evidence record](../assurance/sqlite-relational-v1.json) adds checked join, predicate and ordering roles. The [result selection record](../assurance/sqlite-result-v1.json) covers duplicate equivalence, limit and offset composition, and allowed results when bounds cut through a tie. The [server projection record](../assurance/server-projection-v1.json), [server join record](../assurance/server-join-v1.json) and [server result record](../assurance/server-result-v1.json) specify the native conditions and observations. The full scope and status of the remaining obligations are recorded in the [contract ledger](../assurance/contract-v1.json) and the [evidence guide](../assurance/README.md).
