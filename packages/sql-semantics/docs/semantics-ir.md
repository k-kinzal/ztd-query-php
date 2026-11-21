# Complete SemanticsIR values

`SqlSemantics\SemanticsIR` and `SqlSemantics\IrAnalyzer` are shared interfaces. The database package supplies the operation classes, operand types, scope checks, and SQL representation. All three database packages provide a checked projection over zero or one external table. Each implementation fixes its own language profile and operand roles.

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$sqlite = new Semantics();
$accounts = $sqlite->analyze('CREATE TABLE accounts (Id INTEGER, balance INTEGER)');
$ir = $sqlite->ir()->analyze(
    'SELECT id, accounts.id, balance - ?2 AS remaining FROM accounts',
    [$accounts],
);

assert($ir->columns[0]->expression->slot === $ir->columns[1]->expression->slot);
echo $ir->toString();
```

The source has an absolute schema and table name. Its ordered slots carry only the names of the referenced columns. Both occurrences of `id` refer to one slot. The output name `Id` is a separate value, and the two occurrences remain separate positions in the result. Interpreting or rendering the value requires no parser or declaration context. Evaluation supplies the same external objects and SQLite storage values and binds the numbered parameter at slot 2.

You can build the same structure directly with classes in `SqlSemantics\Platform\Sqlite\Ir`. Use `TableSource` to declare the external column slots, `ColumnReference` to address a slot, `ResultColumn` to associate an expression with its output name, and `Select` to close the scope. A direct source is an assertion about the external schema supplied for evaluation. Analysis establishes that assertion against the supplied declaration.

## Supported SQLite scope

- One plain SELECT with explicit result expressions and at most one base table source. A named source must resolve to a complete declaration. Column expressions resolve to explicitly declared table columns.
- Integer literals in the signed 64-bit range, NULL, numbered SQLite parameters, column references, and binary addition, subtraction and multiplication.
- Result order, result names, SQLite storage classes and values, repeated rows, and numeric parameter slots and labels. The result rows form a multiset because this domain has no ordering clause.
- The default UTF-8 profile with short column names enabled and full column names disabled. The engine uses the standard SQLite 3.47.2 limits. The initial IR domain also bounds identifier length, the number of result columns, and expanded expression size.

Other clauses, expressions and statement families fail through the complete entry point. The legacy `Semantics::analyze()` continues to provide `Operation` for declaration extraction, diagnostic facts and the broader syntax domain. Those values may have unresolved dependencies. Existing callers can continue using that API. To start consuming complete values, switch query call sites to `$sqlite->ir()->analyze()`, handle `SqlSemantics\AnalysisException`, and use concrete SQLite value classes for traversal. Use the legacy API to prepare declarations.

## MySQL and PostgreSQL projections

Call `ir()->analyze()` on the MySQL or PostgreSQL `Semantics` object configured for the target release. Their database-owned `Ir` namespaces provide direct construction with the same invariant checks.

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

The table describes the initial SQLite domain. The server projection record specifies the additional MySQL and PostgreSQL conditions and observations. The full scope and status of the remaining obligations are recorded in the [contract ledger](../assurance/contract-v1.json) and the [evidence guide](../assurance/README.md).
