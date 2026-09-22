# Limitations

The database release is the language support boundary, matching sql-faker and
sql-parser. All SQL constructs in a selected release are in scope. A missing
semantic rule is an implementation defect, not an excluded statement category.
The [README](../README.md#support-syntax) lists the available releases.

## Catalog and inference

| Area | Current limitation and representation |
|------|---------------------------------------|
| Catalog completeness | The package uses the supplied schema snapshot; it does not discover server objects, search paths, function signatures, extensions, or session state from a live connection. `analyze()` retains unresolved relations, column references, and wildcard markers with diagnostics. |
| Strict binding | `bind()` raises known semantic errors. A successful bind is not a certificate that a server will execute the SQL: absent runtime information can still produce an `unknown` type or NULL fact. |
| Type inference | Parameters without declared types, unknown function signatures, and value-dependent operations can retain unknown facts. Operations, arguments, and known dependencies remain available. |
| NULL inference | NULL facts describe successfully evaluated values. They do not establish row existence or freedom from execution errors. WHERE predicates are retained but do not currently refine output nullability. |
| SQLite types | Declared types and affinity are available; SQLite's runtime storage class can differ for each value. |
| Schema representation | Tables and columns have semantic declaration objects. Auxiliary catalog objects and some dialect-specific options remain in `Schema::statements`, declaration attributes, and source nodes instead of dedicated semantic catalog classes. |
| General command details | Structured effects are available for writes, settings, and table declarations. Additional utility-command details remain in source and clause nodes; there is no complete canonical execution-plan model for every administrative operation. |
| Script context | `bindAll()` preserves statement boundaries but binds against one schema snapshot. It does not execute preceding DDL or SET effects to change the context of later statements. Use `SchemaBuilder` for ordered schema evolution. |

An incomplete catalog can prevent a consumer from knowing a result's width or
type. It must inspect diagnostics and unknown facts before assuming a complete
shape. Retaining source syntax does not substitute for a missing semantic rule;
report cases where a consumer cannot obtain required meaning as defects.

## Construction, editing, and SQL output

| Area | Current limitation |
|------|--------------------|
| Construction | Public models expose readonly properties, and semantic model constructors validate structural invariants. They are not a general SQL builder: manually assembled objects are not automatically parsed or rebound, and readonly fields alone do not establish SQL validity. Use the binder to obtain validated snapshots. |
| Safe edits | `replaceExpression()` supports replacing one owned expression in an existing statement. It is not an API for arbitrary insertion, deletion, or rearrangement of every node. The replacement is parsed and strictly rebound; unresolved references can therefore prevent an edit from succeeding. |
| SQL output | `toSql()` writes the retained parser source. SQL-derived snapshots round-trip, and expression edits produce a new source tree. Constructing a different semantic graph around an old source does not cause `toSql()` to serialize those changes. There is no standalone serializer for structures built from scratch. |
| Statement lists | The accepted script syntax comes from the selected sql-parser grammar. `bindAll()` is not a client command processor and does not implement client directives such as MySQL's `DELIMITER`. |

## Execution and fixture generation

The package does not execute SQL, maintain rows, evaluate functions, apply
transactions or triggers, check permissions, or enforce constraints against data.
It also does not solve predicates or generate fixtures. It supplies the
declarations, expressions, conditions, dependencies, and effects needed by those
consumers. A known type mismatch can be diagnosed statically; whether a predicate
matches existing rows or can be satisfied by generated data requires additional
evaluation or solving.

Value lineage alone is insufficient for fixture generation. Even `SELECT 1 FROM
users WHERE score > 0` depends on rows and conditions despite its constant output.
Consumers must also traverse joins, filters, grouping, and nested query graphs.

## Verification bounds

The [fuzz targets](../fuzz/README.md) use sql-faker's complete statement rules for
every declared release, with no statement exclusions or allowed exceptions.
They check structure, SQL round-tripping, deterministic analysis, and known-schema
properties. A finite mutation budget and input-size limit provide evidence for
these properties, not an exhaustive proof over all possible SQL strings. Unit
tests assert concrete semantics and retain regressions in the implementation's
test suites.
