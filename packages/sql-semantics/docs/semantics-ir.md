# Complete SemanticsIR values

`SqlSemantics\SemanticsIR` and `SqlSemantics\Analyzer` are shared interfaces. The database package supplies the operation classes, operand types, scope checks, and SQL representation. All three database packages provide checked projections. SQLite also supports a chain of joined base-table occurrences. Each implementation fixes its own language profile and operand roles.

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
- Result order, result names, SQLite storage classes and values, repeated rows, and numeric parameter slots and labels. Without an ordering clause, the result is a multiset. The ordering contract fixes the relative order of rows with different keys.
- The default UTF-8 profile with short column names enabled and full column names disabled. The engine uses the standard SQLite 3.47.2 limits. The initial IR domain also bounds identifier length, the number of result columns, and expanded expression size.
- Top-level DELETE of one declared base table. The IR records the absolute target and checks any WHERE predicate against its slots. This domain covers DELETE without WITH, RETURNING or an explicit index choice. The DELETE changes rows only when the caller executes its output. Execution supplies the same target definitions and trigger rules.
- Top-level VALUES with fixed-width, nonempty rows of scalar expressions evaluated without table inputs. Its result names are column1 through columnN, and each written row contributes one result row.
- Compound queries combine SELECT and VALUES arms through UNION ALL, UNION, INTERSECT and EXCEPT. Arms have equal width and keep their individual scopes. The compound exposes the first arm’s result labels and supports ordinal ORDER BY and LIMIT when the last arm is SELECT.
- A projection from one ordinary CTE whose definition is VALUES with explicit column names. The IR carries the definition and its ordered column slots in a `LocalSource`. The CTE supplies the rows even when the database contains a same-named base table. Recursion, hints and joins with CTE inputs remain open.
- Top-level INSERT of checked VALUES rows with explicit writable declared columns, including plain INSERT, OR ABORT and OR IGNORE. The IR preserves operand order and an absolute table identity. Existing defaults, affinities, generated values, constraints and triggers are evaluated using the same external table. Other conflict policies, WITH, UPSERT and RETURNING remain open.
- Top-level UPDATE with scalar assignments and optional WHERE on one resolved base table. Assigned expressions read the old row and refer to the same local slots used by the assignment targets. Written order preserves repeated assignments and numbered parameter registration. The current domain includes inherited, ABORT and IGNORE policies. It leaves WITH, FROM, row assignments, index choices and RETURNING open.
- DROP TABLE for a resolved ordinary table or a known schema-qualified absence under IF EXISTS. The absolute name preserves the target, including when a temporary table shadows a main table. SQLite applies the matching schema's foreign key rules during execution.
- CREATE TABLE for an ordinary rowid table with ordered columns, optional NOT NULL, and optional declared INTEGER, TEXT, REAL or BLOB types. The IR resolves the creation schema, preserves IF NOT EXISTS, and keeps an untyped column distinct from a declared BLOB column. Keys, defaults, additional types, table options and constraints remain open.
- BEGIN with its effective lock mode, COMMIT, ROLLBACK, SAVEPOINT, RELEASE and ROLLBACK TO. The value identifies the transition and carries the decoded checkpoint label when needed. The same connection is responsible for its transaction stack and lock state. RELEASE and ROLLBACK TO address the most recent matching checkpoint at execution.

`Semantics::analyze()` returns the complete value. Handle `SqlSemantics\AnalysisException` for malformed, unresolved or unsupported input. `analyzeAll()` applies the same contract to each statement against the same explicit context.

Use `Semantics::context()` to provide a catalog of relation definitions for name resolution. Each call to `analyzeAll()` binds against the same context. The result contains the complete statements in script order.

## MySQL and PostgreSQL projections

Call `analyze()` on the MySQL or PostgreSQL `Semantics` object configured for the target release. Their database-owned `Ir` namespaces provide direct construction with the same invariant checks.

- The projection supports one ordinary base-table input, explicit result expressions, signed 32-bit integer literals and column slots, truth values, NULL, and signed addition, subtraction and multiplication. Truth values and literal NULL occupy result positions. Signed arithmetic takes integer expressions.
- MySQL records the named database and uses the native default lexical settings, UTF-8 through `utf8mb4`, `lower_case_table_names=0`, and empty `sql_mode`. An unqualified table requires a declaration context with a named current database. MySQL reports signed INT table values, integer arithmetic, and the NULL result with their native result types.
- PostgreSQL binds integer slots to `int4` declarations, preserves `ONLY`, and emits `pg_catalog`-qualified integer operators. Unqualified input operators require `pg_catalog` ahead of user schemas in the relevant search path. An explicitly qualified builtin operator can supply its identity directly.
- Parameterized server projections, joins, filters, additional query clauses, DML, DDL and transaction effects remain open in the complete domains. Use the [scope and evidence guide](../assurance/README.md) for the implementation and engine coverage.

## Construction and evidence

| Requirement | Mechanism in the initial SQLite domain |
| --- | --- |
| P01 | The root identifies the language profile. Every operator has a closed enum case. Table identity and numbered parameters are explicit. Column references index local source slots. |
| P02 | Final classes close operand alternatives. Checked list construction copies arrays by value. The SELECT constructor checks scope, count and expanded expression limits before returning. Analysis uses the same constructor. |
| P03 | The root holds only a projection and its semantic source identity. A result name derived from SQL spelling is carried as the observed label and emitted as a quoted alias. |
| P04 | Each arithmetic node stores ordered operands. Expressions can be read or reconstructed by matching their concrete types. The source and references resolve locally in the root. |
| P05 | Every published property is readonly. The operand types and list element classes are closed. `Snapshot` blocks cloning, dynamic properties and PHP serialization and restoration. |
| P06 | The emitter visits every expression alternative, writes grouping explicitly, quotes identifiers, and emits a result name for every projection. Independent execution checks the observations on SQLite 3.47.2 within the recorded domain. |

The table applies to the checked projection roles. The SQLite [relational evidence record](../assurance/sqlite-relational-v1.json) adds checked join, predicate and ordering roles. The [result selection record](../assurance/sqlite-result-v1.json) covers duplicate equivalence, limit and offset composition, and allowed results when bounds cut through a tie. The server projection record specifies the additional MySQL and PostgreSQL conditions and observations. The full scope and status of the remaining obligations are recorded in the [contract ledger](../assurance/contract-v1.json) and the [evidence guide](../assurance/README.md).
