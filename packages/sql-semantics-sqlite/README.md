# SQL Semantics for SQLite

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-sqlite

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE main.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM main.accounts', $context);

echo $ir->toString();
```

The checked complete domain includes projections, joins, predicates, ordering, values, compound queries, selected table changes and transaction control. Table declarations include STRICT, WITHOUT ROWID with a resolved primary key, native INT and ANY types, literal defaults and per-column NOT NULL policies. Table declarations also cover ordered unique keys and explicitly named CHECK constraints over declared columns. Declarations also cover VIRTUAL and STORED generated columns with checked scalar expressions, including forward references to declared columns. The default search order gives temporary tables precedence over main.

For an ordinary ADD COLUMN operation, provide the target table declaration to `context()` and pass that context to `analyze()`. The resulting SQLite `AddColumn` records the resolved schema and table, the prior column order, the table's STRICT mode and the new column. It supports the standard declared types, ordinary untyped columns, literal defaults, builtin collations and NOT NULL conflict policies. SQLite evaluates restrictions that depend on whether the table has rows. For example, adding a NOT NULL column with no default succeeds on an empty SQLite 3.47.2 table and fails on a populated one.

For a generated addition, `analyze()` returns SQLite `AddGeneratedColumn`. The generated expression binds to the resolved prior column ordinals, including prior generated columns. SQLite 3.47.2 supports adding a VIRTUAL column to a populated table and adding a STORED column to an empty table.

Both addition types can carry explicitly named inline CHECK constraints, such as `CONSTRAINT positive CHECK (total > 0)`. A CHECK can read the appended column as well as prior columns. The completed value keeps the constraints in declaration order. SQLite validates existing rows during the addition and uses the first failed constraint's name in diagnostics for later writes. An unnamed check can compare a declared column with a nonnegative decimal integer. The IR stores the checked comparison spelling so SQLite can still derive the diagnostic label when that column is renamed. Within one inline column definition an explicit name applies to later unnamed checks.

Both table declarations and ordinary column additions can carry `ColumnDefaultExpression` with a checked expression. SQLite applies the expression when an insert omits the column. An addition to a populated table also follows SQLite's existing-row fallback rules. Arithmetic such as `DEFAULT (1 + 2)` succeeds when adding to an empty table and fails when adding to a populated table.

A column removal returns `DropColumn` with the resolved absolute table, the prior declared heading and the ordinal to remove. Build the context from the table's CREATE TABLE definition so the analyzer can check keys, surviving CHECK and generated expressions, and the column layout. SQLite evaluates schema-wide effects, such as a separate index or view referring to the affected column, when the IR is executed.

For `CREATE INDEX`, `analyze()` resolves the indexed table through the supplied context and returns `CreateIndex`. The value records the owning schema, ordered `IndexTerm` operands, direction, uniqueness and any partial predicate. It retains native empty membership in a partial predicate, including the numbered parameters that SQLite registers in its discarded operand. Indexed CAST expressions retain the decoded type name when SQLite uses its exact bytes for matching. Binary IFNULL also keeps its native identity. Forced index queries can distinguish these forms from casts with the same affinity or from COALESCE.

For `DROP INDEX`, include the indexed table and its `CREATE INDEX` declaration in `context()`, in that order. The returned `DropIndex` carries the selected absolute index name and owner, or the exact verification scope of a known absence under `IF EXISTS`. A complete context establishes absence. An unqualified lookup checks temp, main and then the configured attached schemas. Table declarations establish the automatic UNIQUE and PRIMARY KEY index names as well. A present constraint index is protected from removal. A guarded absence can still be resolved when the declared key and schema information establishes it.

`REINDEX` rebuilds all indexes, indexes that use one builtin collation, all indexes of a declared relation, or one declared index. Unqualified names match builtin collations first, then relations, then indexes. A qualified name bypasses collation lookup. The returned `Reindex` keeps the selected absolute relation or index and its owner.

`ANALYZE` collects SQLite planner statistics. With no selector it skips the temporary schema. An unqualified name selects a known schema first, then an index, then a relation. `Analyze` retains that selected role and shares the absolute relation and index coordinates with `Reindex`.

Connection-flag PRAGMAs such as `foreign_keys`, `query_only`, and `writable_schema` return `FlagPragma`. The value exposes the selected `FlagName`, the optional resolved schema qualifier, and the requested `FlagChange`. A read has no change. `writable_schema=RESET` also reloads the schema cache. SQLite applies writes during preparation, including the rule that a foreign key setting cannot change inside a transaction.

`table_info`, `table_xinfo`, `index_list`, and `foreign_key_list` return `TableInspection`. Its target identifies the resolved table, view or builtin schema catalog. An explicit missing target requires a complete declaration context and produces the native named empty result. `table_list` returns `TableListing` with its optional schema and name filter. The filter can match several schemas and uses the catalog key such as `sqlite_master` even when SQLite displays the preferred name `sqlite_schema`.

`index_info` and `index_xinfo` return `IndexInspection`. SQLite searches for a named index before looking for a matching WITHOUT ROWID table. The table-name form and the native automatic index name produce the same absolute index coordinate when they select the same primary storage. An empty selection preserves any explicit database qualifier.

`foreign_key_check` returns `ForeignKeyAudit`. With no argument it checks the selected schema and defaults to main. A named relation is resolved in SQLite's native search order. The database reports violations using the declared foreign-key ordinal and a rowid, or NULL for a WITHOUT ROWID child.

`integrity_check` and `quick_check` return `IntegrityAudit`. An integer argument sets the report limit. A named argument carries a resolved table, view or catalog. An unqualified audit retains SQLite’s access to all attached databases, even when the named target belongs to one database. An explicit database name limits the access to that database.

The database header pragmas `application_id`, `user_version`, `schema_version`, `freelist_count` and `data_version` return `HeaderPragma`. The value records the selected database, the exact field, and the normalized signed operand for a write. A missing database qualifier selects main.

The pager pragmas `page_size`, `page_count` and `max_page_count` return `PagePragma`. The value records the resolved pager and the typed setting, when present. A page-size request can set the pending size for a later `VACUUM`. An upper page-count request takes effect when SQLite steps the statement.

The reclamation pragmas return `AutoVacuum` or `IncrementalVacuum`. An automatic mode request can affect a later file `VACUUM`. An incremental request keeps its effective positive limit and can return one zero-column row per reclaimed page.

`VACUUM` and `VACUUM INTO` return `Vacuum` with a resolved database and, where applicable, a source-free filename expression. SQLite skips rebuilding the temporary database and evaluating its filename. The IR retains any numbered slots and marker names that SQLite registers in that expression.

`ATTACH` and `DETACH` return typed connection commands. A constant detachment name resolves against the analysis context. Numbered selector parameters keep their binding slots. The IR keeps the filename and key operands in order and carries no connection state.

Connection inventory reads return `InventoryPragma` with an `InventoryKind` for databases, collations, compile options, functions, modules or active pragmas. The result reads the current connection registry. An explicit schema qualifier remains part of the operation, including native activation of the temporary database.

A single INNER, CROSS, LEFT, RIGHT, or FULL join between two declared base tables can use USING or NATURAL. The IR resolves the comparison operands in left-to-right order and keeps both inputs addressable by qualified names. An unqualified merged name selects the right slot for RIGHT and the left slot for INNER, CROSS, and LEFT. FULL combines the left and right slots, keeping the first non-NULL value. SQLite also resolves USING and NATURAL in longer INNER, CROSS, and LEFT chains. Each equality refers to its actual earlier input, and each step retains its own right occurrence. The rendered SELECT uses explicit ON and projection clauses.

INSERT and UPDATE keep their explicit conflict strategy or inherit the target schema's policy. REPLACE is an INSERT with an explicit REPLACE strategy. Execution uses the target's constraint and trigger definitions. INSERT with VALUES or SELECT can record a targetless ON CONFLICT DO NOTHING action.

Concrete types are under `SqlSemantics\Platform\Sqlite\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
